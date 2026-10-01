<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Secret;
use Alien\Core\Settings;
use Alien\EInvoice\Fiscal;
use Alien\EInvoice\InvoiceData;
use Alien\EInvoice\XmlBuilder;
use Alien\Services\EInvoices;
use Alien\Services\Invoices;
use Alien\Services\Modules;
use Alien\Services\Orders;
use Alien\Services\Sdi;

final class EInvoiceController extends AdminController
{
    private const SELLER = ['name', 'vat', 'cf', 'regime', 'address', 'cap', 'city', 'prov', 'email', 'phone', 'rea_office', 'rea_number', 'nature', 'iban'];

    public function index(Request $req): Response
    {
        $status = $req->str('status');
        $where = isset(EInvoices::STATUSES[$status]) ? 'WHERE status = ?' : '';
        $counts = ['' => (int)DB::val('SELECT COUNT(*) FROM einvoices')];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM einvoices GROUP BY status') as $r) {
            $counts[$r['status']] = (int)$r['n'];
        }
        return $this->view('einvoice/index', [
            'title' => __('Fatture elettroniche'),
            'subtitle' => __('Fatture emesse e inviate al Sistema di Interscambio'),
            'actions' => '<form method="post" action="' . e(url('admin/einvoice/sync')) . '">' . csrf_field() . '<button class="btn sec">↻ ' . e(__('Sincronizza ora')) . '</button></form>',
            'rows' => DB::all("SELECT * FROM einvoices $where ORDER BY id DESC LIMIT 200", $where ? [$status] : []),
            'counts' => $counts,
            'status' => $status,
            'configured' => InvoiceData::sellerErrors(InvoiceData::seller()) === [],
            'canSend' => Sdi::canSend(),
            'lastSync' => setting('einv_last_sync'),
            'report' => json_decode((string)setting('einv_last_report', '{}'), true) ?: [],
        ], 'einvoice');
    }

    public function show(Request $req, array $params): Response
    {
        $inv = EInvoices::find((int)$params['id']);
        if (!$inv) {
            return $this->back('admin/einvoice', __('Fattura non trovata.'), 'error');
        }
        return $this->view('einvoice/show', [
            'title' => ($inv['doc_type'] === 'TD04' ? __('Nota di credito') : __('Fattura')) . ' ' . $inv['number'],
            'inv' => $inv,
            'order' => $inv['order_id'] ? Orders::find((int)$inv['order_id']) : null,
            'canSend' => Sdi::canSend(),
        ], 'einvoice');
    }

    public function action(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        $inv = EInvoices::find($id);
        if (!$inv) {
            return $this->back('admin/einvoice', __('Fattura non trovata.'), 'error');
        }
        $back = 'admin/einvoice/' . $id;
        switch ($req->str('action')) {
            case 'send':
                $error = EInvoices::send($id);
                return $this->back($back, $error === null ? __('Fattura inviata al Sistema di Interscambio.') : __('Invio non riuscito: %s', $error), $error === null ? 'success' : 'error');
            case 'regenerate':
                $order = Orders::find((int)$inv['order_id']);
                $r = $order ? EInvoices::issue($order) : ['ok' => false, 'errors' => [__('Ordine non trovato.')]];
                return $this->back($back, $r['ok'] ? __('Fattura rigenerata.') : implode(' ', $r['errors']), $r['ok'] ? 'success' : 'error');
            case 'credit':
                $r = EInvoices::creditNote($id);
                return $r['ok'] ? $this->back('admin/einvoice/' . $r['invoice']['id'], __('Nota di credito emessa.')) : $this->back($back, implode(' ', $r['errors']), 'error');
            case 'status':
                if (isset(EInvoices::STATUSES[$req->str('status')])) {
                    DB::update('einvoices', ['status' => $req->str('status')], 'id = ?', [$id]);
                    EInvoices::log($id, __('Stato impostato manualmente: %s', EInvoices::statusLabel($req->str('status'))));
                }
                return $this->back($back, __('Stato aggiornato.'));
        }
        return $this->back($back);
    }

    public function xml(Request $req, array $params): Response
    {
        $inv = EInvoices::find((int)$params['id']);
        if (!$inv || !is_file(EInvoices::xmlPath($inv))) {
            return $this->back('admin/einvoice', __('File XML non trovato.'), 'error');
        }
        return Response::download((string)file_get_contents(EInvoices::xmlPath($inv)), $inv['file_name'], 'application/xml');
    }

    public function pdf(Request $req, array $params): Response
    {
        $inv = EInvoices::find((int)$params['id']);
        $order = $inv ? Orders::find((int)$inv['order_id']) : null;
        if (!$order) {
            return $this->back('admin/einvoice', __('Ordine non trovato.'), 'error');
        }
        return Response::download(Invoices::pdf($order, true), 'fattura-' . str_replace('/', '-', $inv['number']) . '.pdf', 'application/pdf');
    }

    public function sync(): Response
    {
        $r = Sdi::sync();
        $msg = __('Messaggi letti: %d · Fatture ricevute: %d · Notifiche SdI: %d', $r['messages'], $r['invoices'], $r['notifications']);
        return $this->back('admin/einvoice', $r['errors'] ? __('Sincronizzazione con avvisi: %s', implode(' | ', $r['errors'])) : $msg, $r['errors'] ? 'error' : 'success');
    }

    public function orderInvoice(Request $req, array $params): Response
    {
        $order = Orders::find((int)$params['id']);
        if (!$order) {
            return $this->back('admin/orders', __('Ordine non trovato.'), 'error');
        }
        $in = $req->post;
        $billing = $order['billing'];
        $billing['invoice'] = [
            'requested' => 1, 'type' => ($in['type'] ?? '') === 'company' ? 'company' : 'private', 'name' => trim((string)($in['name'] ?? '')),
            'vat' => preg_replace('/\s+/', '', (string)($in['vat'] ?? '')), 'cf' => strtoupper(preg_replace('/\s+/', '', (string)($in['cf'] ?? '')) ?? ''),
            'sdi' => strtoupper(trim((string)($in['sdi'] ?? ''))), 'pec' => trim((string)($in['pec'] ?? '')),
        ];
        if (!empty($in['state'])) {
            $billing['state'] = strtoupper(substr((string)$in['state'], 0, 2));
        }
        DB::update('orders', ['billing' => json_encode($billing, json_flags())], 'id = ?', [$order['id']]);
        $r = EInvoices::issue(Orders::find((int)$order['id']));
        return $this->back('admin/orders/' . $order['id'], $r['ok'] ? __('Fattura elettronica emessa.') : implode(' ', $r['errors']), $r['ok'] ? 'success' : 'error');
    }

    public function wizard(Request $req): Response
    {
        $step = max(1, min(5, $req->int('step', 1)));
        if ($req->isPost()) {
            $from = $req->int('from', 1);
            if ($from === 1) {
                foreach (self::SELLER as $k) {
                    Settings::set('einv_' . $k, trim((string)($req->post[$k] ?? '')));
                }
                Settings::set('einv_bollo', $req->str('bollo') === '1' ? 1 : 0);
                $errors = InvoiceData::sellerErrors(InvoiceData::seller());
                if ($errors) {
                    return $this->back('admin/einvoice/setup?step=1', implode(' ', $errors), 'error');
                }
            } elseif ($from === 2) {
                Settings::set('einv_transport', $req->str('transport') === 'pec' ? 'pec' : 'manual');
                foreach (['pec_address', 'pec_smtp_host', 'pec_smtp_port', 'pec_smtp_secure', 'pec_imap_host', 'pec_imap_port', 'pec_imap_secure', 'pec_user', 'sdi_address'] as $k) {
                    Settings::set($k, trim((string)($req->post[$k] ?? '')));
                }
                if ($req->str('pec_pass') !== '') {
                    Settings::set('pec_pass', Secret::seal((string)$req->post['pec_pass']));
                }
            } elseif ($from === 4) {
                Settings::set('einv_auto', in_array($req->str('einv_auto'), ['off', 'requested', 'all'], true) ? $req->str('einv_auto') : 'requested');
                foreach (['einv_autosend', 'einv_courtesy', 'einv_sync'] as $k) {
                    Settings::set($k, $req->str($k) === '1' ? 1 : 0);
                }
            } elseif ($from === 5) {
                Modules::set('einvoice', true);
                Settings::set('einv_done', 1);
                return $this->back('admin/einvoice', __('Fatturazione elettronica attiva!'));
            }
            return Response::redirect('admin/einvoice/setup?step=' . min(5, $from + 1));
        }

        $check = null;
        if ($step === 3 && $req->str('test') === 'smtp') {
            $check = ['smtp', Sdi::testSmtp()];
        } elseif ($step === 3 && $req->str('test') === 'imap') {
            $check = ['imap', Sdi::testImap()];
        }
        $final = null;
        if ($step === 5) {
            $seller = InvoiceData::sellerErrors(InvoiceData::seller());
            $sampleErrors = [];
            if (!$seller) {
                $sample = $this->sampleOrder();
                $d = InvoiceData::fromOrder($sample, 'TD01', date('Y') . '/0000', date('Y-m-d'), '00000');
                $sampleErrors = XmlBuilder::validate(XmlBuilder::build($d));
            }
            $final = ['seller' => $seller, 'xml' => $sampleErrors, 'smtp' => Sdi::canSend(), 'imap' => Sdi::canReceive()];
        }
        return $this->view('einvoice/wizard', [
            'title' => __('Fatturazione elettronica'),
            'subtitle' => __('Emetti e ricevi fatture via SdI, gratis'),
            'step' => $step,
            'check' => $check,
            'final' => $final,
            'regimes' => Fiscal::REGIMES,
            'natures' => Fiscal::NATURES,
            'v' => static fn(string $k, string $d = '') => (string)setting($k, $d),
            'hasPass' => (string)setting('pec_pass', '') !== '',
        ], 'einvoice');
    }

    private function sampleOrder(): array
    {
        $addr = ['name' => 'Mario Rossi', 'address' => 'Via Verdi 3', 'city' => 'Roma', 'zip' => '00100', 'state' => 'RM', 'country' => 'IT', 'invoice' => ['type' => 'private', 'name' => 'Mario Rossi', 'cf' => 'RSSMRA85T10A562S']];
        return ['id' => 0, 'number' => 'TEST', 'currency' => 'EUR', 'subtotal' => 12200, 'discount' => 0, 'shipping' => 0, 'tax' => 2200, 'total' => 12200, 'tax_rate' => 2200, 'coupon_code' => '', 'shipping_method' => '',
            'payment_method' => 'bank', 'created_at' => now(), 'billing' => $addr, 'shipping_address' => $addr,
            'items' => [['name' => 'Prodotto di prova', 'variant_label' => '', 'qty' => 1, 'total' => 12200]]];
    }
}
