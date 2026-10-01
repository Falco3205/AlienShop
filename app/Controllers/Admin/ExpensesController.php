<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Response;

final class ExpensesController extends AdminController
{
    public function index(Request $req): Response
    {
        if ($req->isPost()) {
            $net = Money::parse($req->str('net'));
            $vat = Money::parse($req->str('vat'));
            $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $req->str('day')) ? $req->str('day') : date('Y-m-d');
            if ($req->str('description') === '' || $net <= 0) {
                return $this->back('admin/expenses', __('Descrizione e importo sono obbligatori.'), 'error');
            }
            DB::insert('expenses', [
                'day' => $day, 'supplier' => mb_substr($req->str('supplier'), 0, 190), 'description' => mb_substr($req->str('description'), 0, 255), 'category' => mb_substr($req->str('category'), 0, 80),
                'net' => $net, 'vat' => $vat, 'total' => $net + $vat, 'deductible' => max(0, min(100, $req->int('deductible', 100))), 'paid' => 1, 'created_at' => now(),
            ]);
            return $this->back('admin/expenses', __('Spesa registrata.'));
        }
        return $this->view('expenses', [
            'title' => __('Spese'),
            'subtitle' => __('Costi senza fattura elettronica: scontrini, abbonamenti, commissioni'),
            'rows' => DB::all('SELECT * FROM expenses ORDER BY day DESC, id DESC LIMIT 200'),
            'categories' => array_column(DB::all("SELECT DISTINCT category FROM expenses WHERE category <> '' UNION SELECT DISTINCT category FROM purchase_invoices WHERE category <> ''"), 'category'),
        ], 'expenses');
    }

    public function delete(Request $req, array $params): Response
    {
        DB::delete('expenses', 'id = ?', [(int)$params['id']]);
        return $this->back('admin/expenses', __('Spesa eliminata.'));
    }
}
