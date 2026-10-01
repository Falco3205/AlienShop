<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Services\Invoices;
use Alien\Services\Orders;

final class InvoicesController extends AdminController
{
    public function index(): Response
    {
        return $this->view('invoices', [
            'title' => __('Fatture e ricevute'),
            'subtitle' => __('Documenti PDF numerati per i tuoi ordini'),
            'rows' => DB::all("SELECT * FROM orders WHERE invoice_number <> '' ORDER BY invoice_date DESC LIMIT 100"),
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d'),
        ], 'invoices');
    }

    public function settings(Request $req): Response
    {
        Settings::set('invoice_type', in_array($req->str('invoice_type'), ['receipt', 'invoice', 'sales'], true) ? $req->str('invoice_type') : 'receipt');
        Settings::set('invoice_prefix', preg_replace('/[^A-Za-z0-9-]/', '', $req->str('invoice_prefix')) ?? '');
        Settings::set('invoices_auto', $req->str('invoices_auto') === '1' ? 1 : 0);
        Settings::set('invoice_note', $req->str('invoice_note'));
        return $this->back('admin/invoices', __('Impostazioni salvate.'));
    }

    public function download(Request $req, array $params): Response
    {
        $order = Orders::find((int)$params['id']);
        if (!$order) {
            return $this->back('admin/orders', __('Ordine non trovato.'), 'error');
        }
        $pdf = Invoices::pdf($order);
        $order = Orders::find((int)$order['id']);
        return Response::download($pdf, 'documento-' . str_replace('/', '-', $order['invoice_number']) . '.pdf', 'application/pdf');
    }

    public function send(Request $req, array $params): Response
    {
        $order = Orders::find((int)$params['id']);
        $ok = $order && Invoices::sendToCustomer($order);
        return $this->back('admin/orders/' . (int)$params['id'], $ok ? __('Documento inviato al cliente.') : __('Invio non riuscito: controlla le impostazioni email.'), $ok ? 'success' : 'error');
    }

    public function export(Request $req): Response
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $req->str('from')) ? $req->str('from') : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $req->str('to')) ? $req->str('to') : date('Y-m-d');
        return Response::download(Invoices::csv($from, $to), "fatture-$from-$to.csv");
    }
}
