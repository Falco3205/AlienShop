<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Services\AbandonedCarts;

final class AbandonedController extends AdminController
{
    public function index(): Response
    {
        return $this->view('abandoned', [
            'title' => __('Carrelli abbandonati'),
            'subtitle' => __('Clienti che hanno inserito l\'email ma non hanno concluso l\'ordine'),
            'stats' => AbandonedCarts::stats(),
            'rows' => DB::all('SELECT * FROM abandoned_carts ORDER BY updated_at DESC LIMIT 100'),
            'coupons' => array_column(DB::all('SELECT code FROM coupons WHERE active = 1 ORDER BY code'), 'code', 'code'),
        ], 'abandoned');
    }

    public function settings(Request $req): Response
    {
        Settings::set('abandoned_delay', max(1, min(72, $req->int('abandoned_delay', 2))));
        Settings::set('abandoned_coupon', $req->str('abandoned_coupon'));
        Settings::set('abandoned_subject', $req->str('abandoned_subject'));
        return $this->back('admin/abandoned', __('Impostazioni salvate.'));
    }

    public function remind(Request $req, array $params): Response
    {
        $c = DB::row('SELECT * FROM abandoned_carts WHERE id = ?', [(int)$params['id']]);
        $ok = $c && AbandonedCarts::remind($c);
        return $this->back('admin/abandoned', $ok ? __('Promemoria messo in coda.') : __('Impossibile inviare il promemoria.'), $ok ? 'success' : 'error');
    }
}
