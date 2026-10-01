<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;

final class SearchController extends AdminController
{
    public function index(Request $req): Response
    {
        $q = mb_substr($req->str('q'), 0, 80);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $r = ['products' => [], 'orders' => [], 'customers' => [], 'pages' => []];
        if ($q !== '') {
            $r['products'] = DB::all("SELECT id, name, sku, image, status FROM products WHERE name LIKE ? ESCAPE '\\' OR sku LIKE ? ESCAPE '\\' ORDER BY id DESC LIMIT 8", [$like, $like]);
            $r['orders'] = DB::all("SELECT id, number, email, status, total, currency FROM orders WHERE number LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\' ORDER BY id DESC LIMIT 8", [$like, $like]);
            $r['customers'] = DB::all("SELECT id, name, email FROM users WHERE role = 'customer' AND (name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\') LIMIT 8", [$like, $like]);
            $r['pages'] = DB::all("SELECT id, title, type FROM pages WHERE title LIKE ? ESCAPE '\\' LIMIT 8", [$like]);
        }
        return $this->view('search', ['title' => $q !== '' ? __('Risultati per "%s"', $q) : __('Cerca'), 'q' => $q, 'r' => $r], 'dashboard');
    }
}
