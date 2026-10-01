<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Catalog;
use Alien\Services\Orders;

final class DashboardController extends AdminController
{
    public function index(Request $req): Response
    {
        $days = in_array($req->int('days', 30), [7, 30, 90], true) ? $req->int('days', 30) : 30;
        return $this->view('dashboard', [
            'title' => __('Dashboard'),
            'days' => $days,
            'stats' => Orders::stats($days),
            'recent' => DB::all('SELECT * FROM orders ORDER BY id DESC LIMIT 8'),
            'products' => (int)DB::val('SELECT COUNT(*) FROM products'),
            'customers' => (int)DB::val("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
            'toShip' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status = 'processing'"),
            'lowStock' => Catalog::lookup(['low_stock' => 1, 'per' => 6])['items'],
        ], 'dashboard');
    }
}
