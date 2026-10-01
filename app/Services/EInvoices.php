<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Mailer;
use Alien\Core\Money;
use Alien\Core\Settings;
use Alien\EInvoice\InvoiceData;
use Alien\EInvoice\XmlBuilder;

final class EInvoices
{
    public const STATUSES = [
        'generated' => 'Da inviare', 'sent' => 'Inviata a SdI', 'delivered' => 'Consegnata', 'undeliverable' => 'Non recapitata (nel cassetto fiscale)',
        'rejected' => 'Scartata da SdI', 'accepted' => 'Accettata', 'refused' => 'Rifiutata dal cliente', 'expired' => 'Accettata (termini scaduti)', 'error' => 'Errore',
    ];
    public const VALID = ['generated', 'sent', 'delivered', 'undeliverable', 'accepted', 'expired'];

    public static function enabled(): bool
    {
        return Modules::on('einvoice');
    }

    public static function dir(): string
    {
        $d = ROOT . '/storage/einvoice/out';
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    public static function find(int $id): ?array
    {
        return DB::row('SELECT * FROM einvoices WHERE id = ?', [$id]);
    }

    public static function forOrder(int $orderId): array
    {
        return DB::all('SELECT * FROM einvoices WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public static function log(int $id, string $message): void
    {
        $row = self::find($id);
        DB::update('einvoices', ['log' => trim(($row['log'] ?? '') . "\n[" . now() . '] ' . $message)], 'id = ?', [$id]);
    }

    public static function issue(array $order, string $type = 'TD01', ?array $related = null): array
    {
        $existing = $type === 'TD01' ? DB::row("SELECT * FROM einvoices WHERE order_id = ? AND doc_type = 'TD01' ORDER BY id DESC", [$order['id']]) : null;
        if ($existing && in_array($existing['status'], self::VALID, true)) {
            return ['ok' => true, 'invoice' => $existing, 'errors' => []];
        }
        $errors = array_merge(InvoiceData::sellerErrors(InvoiceData::seller()), InvoiceData::customerErrors(InvoiceData::customer($order)));
        if ($errors) {
            return ['ok' => false, 'invoice' => null, 'errors' => $errors];
        }
        $order = Invoices::assign($order);
        $number = $type === 'TD04' ? Invoices::nextNumber() : ($existing['number'] ?? $order['invoice_number']);
        $date = $existing['issue_date'] ?? date('Y-m-d');
        $customer = InvoiceData::customer($order);
        $dec = Money::factor($order['currency']);

        if ($existing) {
            $id = (int)$existing['id'];
        } else {
            $id = DB::insert('einvoices', [
                'order_id' => (int)$order['id'], 'doc_type' => $type, 'number' => $number, 'issue_date' => $date, 'progressivo' => 'TMP' . bin2hex(random_bytes(3)),
                'file_name' => 'tmp-' . bin2hex(random_bytes(5)), 'status' => 'generated', 'customer_name' => mb_substr($customer['name'], 0, 190),
                'customer_vat' => $customer['vat'], 'customer_cf' => $customer['cf'], 'recipient' => $customer['sdi'] ?: $customer['pec'],
                'currency' => $order['currency'], 'related_id' => (int)($related['id'] ?? 0), 'created_at' => now(),
            ]);
        }
        $counter = (int)Settings::get('einv_counter', 0) + 1;
        Settings::set('einv_counter', $counter);
        $progressivo = str_pad(strtoupper(base_convert((string)$counter, 10, 36)), 5, '0', STR_PAD_LEFT);
        $s = InvoiceData::seller();
        $fileName = 'IT' . ($s['cf'] !== '' ? $s['cf'] : preg_replace('/\D/', '', $s['vat'])) . '_' . $progressivo . '.xml';
        $data = InvoiceData::fromOrder($order, $type, $number, $date, $progressivo, $related);
        $xml = XmlBuilder::build($data);
        $xmlErrors = XmlBuilder::validate($xml);
        if ($xmlErrors) {
            DB::update('einvoices', ['status' => 'error', 'errors' => json_encode($xmlErrors, json_flags())], 'id = ?', [$id]);
            self::log($id, __('Validazione XML fallita.'));
            return ['ok' => false, 'invoice' => self::find($id), 'errors' => $xmlErrors];
        }
        file_put_contents(self::dir() . '/' . $fileName, $xml);
        DB::update('einvoices', [
            'progressivo' => $progressivo, 'file_name' => $fileName, 'status' => 'generated', 'errors' => null,
            'net' => (int)round($data['net'] * $dec), 'vat' => (int)round($data['vat'] * $dec), 'total' => (int)round($data['total'] * $dec),
        ], 'id = ?', [$id]);
        self::log($id, $existing ? __('Fattura rigenerata (%s).', $fileName) : __('Fattura generata (%s).', $fileName));
        $inv = self::find($id);
        if ((string)Settings::get('einv_autosend', '1') === '1' && Sdi::canSend()) {
            self::send($id);
            $inv = self::find($id);
        }
        return ['ok' => true, 'invoice' => $inv, 'errors' => []];
    }

    public static function send(int $id): ?string
    {
        $inv = self::find($id);
        if (!$inv) {
            return __('Fattura non trovata.');
        }
        $error = Sdi::send($inv);
        if ($error !== null) {
            self::log($id, __('Invio fallito: %s', $error));
            return $error;
        }
        DB::update('einvoices', ['status' => 'sent', 'sent_at' => now()], 'id = ?', [$id]);
        self::log($id, __('Inviata al Sistema di Interscambio.'));
        if ((string)Settings::get('einv_courtesy', '1') === '1' && $inv['order_id']) {
            $order = Orders::find((int)$inv['order_id']);
            if ($order && $order['email'] !== '' && !str_contains((string)$inv['log'], 'cortesia')) {
                Mailer::send($order['email'], __('Fattura %s del tuo ordine %s', $inv['number'], $order['number']),
                    '<p>' . e(__('In allegato trovi la copia di cortesia della fattura. L\'originale elettronico è stato trasmesso tramite SdI.')) . '</p>',
                    [['name' => 'fattura-' . str_replace('/', '-', $inv['number']) . '.pdf', 'data' => Invoices::pdf($order, true), 'type' => 'application/pdf']]);
                self::log($id, __('Copia di cortesia inviata al cliente.'));
            }
        }
        return null;
    }

    public static function creditNote(int $id): array
    {
        $orig = self::find($id);
        if (!$orig || $orig['doc_type'] !== 'TD01' || !in_array($orig['status'], self::VALID, true)) {
            return ['ok' => false, 'invoice' => null, 'errors' => [__('Si può emettere una nota di credito solo per una fattura valida.')]];
        }
        if (DB::val("SELECT 1 FROM einvoices WHERE related_id = ? AND doc_type = 'TD04' AND status <> 'error'", [$id])) {
            return ['ok' => false, 'invoice' => null, 'errors' => [__('Esiste già una nota di credito per questa fattura.')]];
        }
        $order = Orders::find((int)$orig['order_id']);
        if (!$order) {
            return ['ok' => false, 'invoice' => null, 'errors' => [__('Ordine non trovato.')]];
        }
        return self::issue($order, 'TD04', ['id' => $id, 'number' => $orig['number'], 'date' => $orig['issue_date']]);
    }

    public static function requested(array $order): bool
    {
        return !empty($order['billing']['invoice']['requested']);
    }

    public static function autoIssue(array $order): void
    {
        if (!self::enabled()) {
            return;
        }
        $mode = (string)Settings::get('einv_auto', 'requested');
        if ($mode === 'off' || ($mode === 'requested' && !self::requested($order))) {
            return;
        }
        $res = self::issue($order);
        if (!$res['ok']) {
            Orders::event((int)$order['id'], __('Fattura elettronica non emessa: %s', implode(' ', $res['errors'])));
        }
    }

    public static function statusLabel(string $status): string
    {
        return __(self::STATUSES[$status] ?? $status);
    }

    public static function cron(): array
    {
        if (!self::enabled() || !Sdi::canReceive() || (string)Settings::get('einv_sync', '1') !== '1') {
            return [];
        }
        $last = (string)Settings::get('einv_last_sync', '');
        if ($last !== '' && strtotime($last) > time() - 600) {
            return [];
        }
        return Sdi::sync();
    }

    public static function xmlPath(array $inv): string
    {
        return self::dir() . '/' . basename((string)$inv['file_name']);
    }
}
