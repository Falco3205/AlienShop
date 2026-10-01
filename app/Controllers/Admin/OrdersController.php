<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Orders;

final class OrdersController extends AdminController
{
    public function index(Request $req): Response
    {
        $where = [];
        $params = [];
        if (isset(Orders::STATUSES[$req->str('status')])) {
            $where[] = 'status = ?';
            $params[] = $req->str('status');
        }
        if ($q = $req->str('q')) {
            $where[] = '(number LIKE ? OR email LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $page = $this->page($req);
        $per = 25;
        $total = (int)DB::val("SELECT COUNT(*) FROM orders$sql", $params);
        $orders = DB::all("SELECT * FROM orders$sql ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
        return $this->view('orders/index', ['title' => __('Ordini'), 'orders' => $orders, 'pg' => $this->paginate($total, $page, $per)], 'orders');
    }

    public function show(Request $req, array $params): Response
    {
        $order = Orders::find((int)$params['id']);
        if (!$order) {
            return $this->back('admin/orders', __('Ordine non trovato.'), 'error');
        }
        if ($req->isPost()) {
            $action = $req->str('action');
            if ($action === 'status') {
                Orders::setStatus((int)$order['id'], $req->str('status'), mb_substr($req->str('tracking'), 0, 190));
            } elseif ($action === 'paid') {
                Orders::markPaid($order, 'manual');
            } elseif ($action === 'refund') {
                $gateway = \Alien\Payments\Registry::get($order['payment_method']);
                $error = $order['payment_status'] !== 'paid' ? __('L\'ordine non risulta pagato.') : ($gateway ? $gateway->refund($order) : __('Metodo di pagamento sconosciuto.'));
                if ($error !== null) {
                    return $this->back('admin/orders/' . $order['id'], __('Rimborso non riuscito: %s', $error), 'error');
                }
                Orders::setStatus((int)$order['id'], 'refunded');
                return $this->back('admin/orders/' . $order['id'], __('Rimborso eseguito.'));
            } elseif ($action === 'note' && $req->str('message') !== '') {
                Orders::event((int)$order['id'], $req->str('message'));
            }
            return $this->back('admin/orders/' . $order['id'], __('Ordine aggiornato.'));
        }
        return $this->view('orders/show', ['title' => __('Ordine %s', $order['number']), 'o' => $order], 'orders');
    }

    public function printSlip(Request $req, array $params): Response
    {
        $order = Orders::find((int)$params['id']);
        if (!$order) {
            return $this->back('admin/orders', __('Ordine non trovato.'), 'error');
        }
        return new Response(\Alien\Core\View::admin('orders/print', ['o' => $order], false), 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store']);
    }

    public function customers(Request $req): Response
    {
        $rows = DB::all("SELECT u.id, u.name, u.email, u.created_at, COUNT(o.id) AS orders, COALESCE(SUM(CASE WHEN o.status NOT IN ('cancelled','refunded') THEN o.total END),0) AS spent
            FROM users u LEFT JOIN orders o ON o.user_id = u.id WHERE u.role = 'customer' GROUP BY u.id, u.name, u.email, u.created_at ORDER BY u.id DESC LIMIT 200");
        return $this->view('orders/customers', ['title' => __('Clienti'), 'rows' => $rows], 'customers');
    }
}
