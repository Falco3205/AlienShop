<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Purchases;
use Alien\Services\Sdi;

final class PurchasesController extends AdminController
{
    public function index(Request $req): Response
    {
        $where = [];
        $params = [];
        if ($req->str('paid') === '0') {
            $where[] = 'paid_at IS NULL';
        } elseif ($req->str('paid') === '1') {
            $where[] = 'paid_at IS NOT NULL';
        }
        if ($q = $req->str('q')) {
            $where[] = '(supplier_name LIKE ? OR number LIKE ? OR supplier_vat LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return $this->view('purchases/index', [
            'title' => __('Fatture ricevute'),
            'subtitle' => __('Fatture dei fornitori ricevute da SdI o caricate a mano'),
            'actions' => '<form method="post" action="' . e(url('admin/purchases/sync')) . '">' . csrf_field() . '<button class="btn sec">↻ ' . e(__('Controlla la PEC')) . '</button></form>',
            'rows' => DB::all("SELECT * FROM purchase_invoices $sql ORDER BY issue_date DESC, id DESC LIMIT 300", $params),
            'canReceive' => Sdi::canReceive(),
        ], 'purchases');
    }

    public function upload(Request $req): Response
    {
        $files = [];
        $in = $_FILES['files'] ?? null;
        if ($in && is_array($in['name'])) {
            foreach ($in['name'] as $i => $name) {
                if (($in['error'][$i] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file($in['tmp_name'][$i])) {
                    $files[] = ['name' => $name, 'path' => $in['tmp_name'][$i]];
                }
            }
        }
        if (!$files) {
            return $this->back('admin/purchases', __('Seleziona almeno un file XML, P7M o ZIP.'), 'error');
        }
        @set_time_limit(300);
        $r = Purchases::ingestMany($files, 'upload');
        if ($r['errors']) {
            $_SESSION['purchase_errors'] = $r['errors'];
        }
        return $this->back('admin/purchases', __('Importate: %d · Già presenti: %d', $r['added'], $r['duplicates']), $r['added'] > 0 || !$r['errors'] ? 'success' : 'error');
    }

    public function sync(): Response
    {
        $r = Sdi::sync();
        return $this->back('admin/purchases', $r['errors'] ? __('Sincronizzazione con avvisi: %s', implode(' | ', $r['errors'])) : __('Messaggi letti: %d · Fatture ricevute: %d · Duplicate: %d', $r['messages'], $r['invoices'], $r['duplicates']), $r['errors'] ? 'error' : 'success');
    }

    public function show(Request $req, array $params): Response
    {
        $row = DB::row('SELECT * FROM purchase_invoices WHERE id = ?', [(int)$params['id']]);
        if (!$row) {
            return $this->back('admin/purchases', __('Fattura non trovata.'), 'error');
        }
        if ($req->isPost()) {
            if ($req->str('action') === 'delete') {
                DB::delete('purchase_invoices', 'id = ?', [$row['id']]);
                return $this->back('admin/purchases', __('Fattura eliminata.'));
            }
            DB::update('purchase_invoices', [
                'category' => mb_substr($req->str('category'), 0, 80),
                'deductible' => max(0, min(100, $req->int('deductible', 100))),
                'paid_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $req->str('paid_at')) ? $req->str('paid_at') : null,
                'notes' => $req->str('notes'),
            ], 'id = ?', [$row['id']]);
            return $this->back('admin/purchases/' . $row['id'], __('Fattura aggiornata.'));
        }
        return $this->view('purchases/show', [
            'title' => ($row['doc_type'] === 'TD04' ? __('Nota di credito') : __('Fattura')) . ' ' . $row['number'],
            'p' => $row,
            'd' => json_decode((string)$row['data'], true) ?: [],
            'categories' => array_column(DB::all("SELECT DISTINCT category FROM purchase_invoices WHERE category <> '' UNION SELECT DISTINCT category FROM expenses WHERE category <> ''"), 'category'),
        ], 'purchases');
    }

    public function xml(Request $req, array $params): Response
    {
        $row = DB::row('SELECT * FROM purchase_invoices WHERE id = ?', [(int)$params['id']]);
        $file = $row ? Purchases::dir() . '/' . preg_replace('/-\d+$/', '', $row['xml_hash']) . '.xml' : '';
        if (!$row || !is_file($file)) {
            return $this->back('admin/purchases', __('File XML non trovato.'), 'error');
        }
        return Response::download((string)file_get_contents($file), preg_replace('/[^A-Za-z0-9_.-]/', '_', $row['supplier_name'] . '_' . $row['number']) . '.xml', 'application/xml');
    }
}
