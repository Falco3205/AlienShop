<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Core\Pdf;
use Alien\Core\Settings;

final class Invoices
{
    public static function title(): string
    {
        return match ((string)Settings::get('invoice_type', 'receipt')) {
            'invoice' => __('Fattura'),
            'sales' => __('Documento di vendita'),
            default => __('Ricevuta'),
        };
    }

    public static function nextNumber(): string
    {
        $year = date('Y');
        $prefix = (string)Settings::get('invoice_prefix', '');
        $max = 0;
        $numbers = DB::col('SELECT invoice_number FROM orders WHERE invoice_number LIKE ?', [$prefix . $year . '/%']);
        foreach (DB::col('SELECT number FROM einvoices WHERE number LIKE ?', [$prefix . $year . '/%']) as $n) {
            $numbers[] = $n;
        }
        foreach ($numbers as $num) {
            $max = max($max, (int)substr((string)$num, strrpos((string)$num, '/') + 1));
        }
        return $prefix . $year . '/' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function assign(array $order): array
    {
        if ($order['invoice_number'] !== '') {
            return $order;
        }
        $number = self::nextNumber();
        DB::update('orders', ['invoice_number' => $number, 'invoice_date' => now()], 'id = ?', [$order['id']]);
        return Orders::find((int)$order['id']);
    }

    public static function seller(): array
    {
        $p = LegalTemplates::profile();
        $name = $p['company'] !== '' ? $p['company'] : (string)Settings::get('store_name', '');
        $lines = array_values(array_filter([
            $p['address'],
            $p['vat'] !== '' ? __('P.IVA') . ' ' . $p['vat'] : '',
            $p['tax_code'] !== '' ? __('C.F.') . ' ' . $p['tax_code'] : '',
            $p['email'] !== '' ? $p['email'] : (string)Settings::get('store_email', ''),
            $p['phone'],
        ]));
        return ['name' => $name, 'lines' => $lines];
    }

    public static function pdf(array $order, bool $courtesy = false): string
    {
        $order = self::assign($order);
        $cur = $order['currency'];
        $f = static fn(int $c) => Money::format($c, $cur);
        $pdf = new Pdf();
        $seller = self::seller();
        $m = 40.0;
        $right = Pdf::W - $m;

        $pdf->text($m, 56, $seller['name'], 18, true);
        $y = 72;
        foreach ($seller['lines'] as $l) {
            $pdf->text($m, $y, $l, 9, false, 'L', [0.35, 0.35, 0.4]);
            $y += 12;
        }
        $pdf->text($right, 56, mb_strtoupper($courtesy ? __('Fattura') : self::title()), 16, true, 'R');
        if ($courtesy) {
            $pdf->text($right, 112, __('Copia di cortesia'), 8, true, 'R', [0.45, 0.45, 0.5]);
        }
        $pdf->text($right, 74, 'N. ' . $order['invoice_number'], 10, true, 'R');
        $pdf->text($right, 88, __('Data') . ': ' . date('d/m/Y', strtotime((string)$order['invoice_date'])), 9, false, 'R', [0.35, 0.35, 0.4]);
        $pdf->text($right, 100, __('Ordine') . ' ' . $order['number'], 9, false, 'R', [0.35, 0.35, 0.4]);

        $y = max($y, 118) + 14;
        $b = $order['billing'] ?: $order['shipping_address'];
        $pdf->text($m, $y, __('Cliente'), 8, true, 'L', [0.45, 0.45, 0.5]);
        $y += 13;
        foreach (array_filter([$b['name'] ?? '', $b['address'] ?? '', trim(($b['zip'] ?? '') . ' ' . ($b['city'] ?? '') . ' ' . ($b['state'] ?? '')), $b['country'] ?? '', $order['email']]) as $l) {
            $pdf->text($m, $y, (string)$l, 10);
            $y += 13;
        }

        $y += 14;
        $cols = ['desc' => $m + 6, 'qty' => $right - 190, 'unit' => $right - 110, 'tot' => $right - 6];
        $pdf->rect($m, $y - 11, $right - $m, 18, [0.94, 0.94, 0.96]);
        $pdf->text($cols['desc'], $y + 1, __('Descrizione'), 8, true);
        $pdf->text($cols['qty'], $y + 1, __('Qtà'), 8, true, 'R');
        $pdf->text($cols['unit'], $y + 1, __('Prezzo'), 8, true, 'R');
        $pdf->text($cols['tot'], $y + 1, __('Totale'), 8, true, 'R');
        $y += 20;
        foreach ($order['items'] as $it) {
            $label = $it['name'] . ($it['variant_label'] !== '' ? ' (' . $it['variant_label'] . ')' : '') . ($it['sku'] !== '' ? ' — ' . $it['sku'] : '');
            $lines = $pdf->wrap($label, $cols['qty'] - $cols['desc'] - 40, 9);
            if ($y + count($lines) * 12 > Pdf::H - 170) {
                $pdf->addPage();
                $y = 60;
            }
            $pdf->text($cols['qty'], $y, (string)$it['qty'], 9, false, 'R');
            $pdf->text($cols['unit'], $y, $f((int)$it['price']), 9, false, 'R');
            $pdf->text($cols['tot'], $y, $f((int)$it['total']), 9, false, 'R');
            foreach ($lines as $l) {
                $pdf->text($cols['desc'], $y, $l, 9);
                $y += 12;
            }
            $pdf->line($m, $y - 5, $right, $y - 5);
            $y += 5;
        }

        $y += 6;
        $row = static function (string $label, string $value, bool $bold = false) use ($pdf, &$y, $right) {
            $pdf->text($right - 130, $y, $label, $bold ? 10 : 9, $bold, 'R', $bold ? [0, 0, 0] : [0.35, 0.35, 0.4]);
            $pdf->text($right - 6, $y, $value, $bold ? 10 : 9, $bold, 'R');
            $y += 15;
        };
        $net = (int)$order['total'] - (int)$order['tax'];
        $row(__('Subtotale'), $f((int)$order['subtotal']));
        if ((int)$order['discount']) {
            $row(__('Sconto') . ($order['coupon_code'] !== '' ? ' ' . $order['coupon_code'] : ''), '-' . $f((int)$order['discount']));
        }
        $row(__('Spedizione'), $f((int)$order['shipping']));
        $row(__('Imponibile'), $f($net));
        $row(__('IVA / imposte'), $f((int)$order['tax']));
        $pdf->line($right - 220, $y - 8, $right, $y - 8, 0.8, [0.2, 0.2, 0.2]);
        $y += 4;
        $row(__('Totale'), $f((int)$order['total']), true);

        $y += 14;
        $pdf->text($m, $y, __('Pagamento') . ': ' . $order['payment_method'] . ' — ' . __(Orders::PAYMENT_STATUSES[$order['payment_status']] ?? $order['payment_status']), 9, false, 'L', [0.35, 0.35, 0.4]);
        $note = $courtesy ? __('Copia di cortesia: l\'originale è la fattura elettronica trasmessa tramite il Sistema di Interscambio.') : (string)Settings::get('invoice_note', '');
        if ($note === '' && !$courtesy && Settings::get('invoice_type', 'receipt') !== 'invoice') {
            $note = __('Documento commerciale non valido ai fini fiscali.');
        }
        if ($note !== '') {
            $y += 20;
            foreach ($pdf->wrap($note, $right - $m, 8) as $l) {
                $pdf->text($m, $y, $l, 8, false, 'L', [0.45, 0.45, 0.5]);
                $y += 11;
            }
        }
        return $pdf->output();
    }

    public static function csv(string $from, string $to): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['invoice_number', 'date', 'order', 'customer', 'country', 'net', 'tax', 'total', 'currency', 'payment', 'status'], ',', '"', '\\');
        foreach (DB::all("SELECT * FROM orders WHERE invoice_number <> '' AND invoice_date >= ? AND invoice_date <= ? ORDER BY invoice_date", [$from . ' 00:00:00', $to . ' 23:59:59']) as $o) {
            $addr = json_decode((string)$o['billing'], true) ?: [];
            $dec = static fn(int $c) => Money::input($c);
            fputcsv($fh, \Alien\Core\Str::csvRow([$o['invoice_number'], substr((string)$o['invoice_date'], 0, 10), $o['number'], $o['email'], $addr['country'] ?? '', $dec((int)$o['total'] - (int)$o['tax']), $dec((int)$o['tax']), $dec((int)$o['total']), $o['currency'], $o['payment_method'], $o['status']]), ',', '"', '\\');
        }
        rewind($fh);
        return (string)stream_get_contents($fh);
    }

    public static function sendToCustomer(array $order): bool
    {
        $order = self::assign($order);
        return \Alien\Core\Mailer::send(
            $order['email'],
            __('%s %s del tuo ordine %s', self::title(), $order['invoice_number'], $order['number']),
            '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto"><p>' . e(__('In allegato trovi il documento relativo al tuo ordine %s. Grazie!', $order['number'])) . '</p></div>',
            [['name' => 'documento-' . str_replace('/', '-', $order['invoice_number']) . '.pdf', 'data' => self::pdf($order), 'type' => 'application/pdf']]
        );
    }
}
