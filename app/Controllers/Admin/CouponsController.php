<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Response;

final class CouponsController extends AdminController
{
    public function index(): Response
    {
        return $this->view('coupons/index', [
            'title' => __('Codici sconto'), 'subtitle' => __('Promozioni in percentuale, importo fisso o spedizione gratuita'),
            'actions' => '<a class="btn" href="' . e(url('admin/coupons/new')) . '">+ ' . e(__('Nuovo codice')) . '</a>',
            'rows' => DB::all('SELECT * FROM coupons ORDER BY id DESC'),
        ], 'coupons');
    }

    public function form(Request $req, array $params = []): Response
    {
        $id = isset($params['id']) ? (int)$params['id'] : null;
        $c = $id ? DB::row('SELECT * FROM coupons WHERE id = ?', [$id]) : null;
        if ($id && !$c) {
            return $this->back('admin/coupons', __('Codice non trovato.'), 'error');
        }
        if ($req->isPost()) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $req->str('code')) ?? '');
            if ($code === '') {
                return $this->back($id ? 'admin/coupons/' . $id : 'admin/coupons/new', __('Il codice è obbligatorio.'), 'error');
            }
            if (DB::row('SELECT id FROM coupons WHERE code = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$code, $id] : [$code])) {
                return $this->back($id ? 'admin/coupons/' . $id : 'admin/coupons/new', __('Esiste già un codice uguale.'), 'error');
            }
            $type = $req->str('type') === 'fixed' ? 'fixed' : 'percent';
            $row = [
                'code' => $code,
                'type' => $type,
                'value' => $type === 'percent' ? max(0, min(100, $req->int('value'))) : Money::parse($req->str('value')),
                'min_subtotal' => Money::parse($req->str('min_subtotal')),
                'max_uses' => max(0, $req->int('max_uses')),
                'free_shipping' => $req->str('free_shipping') === '1' ? 1 : 0,
                'starts_at' => $req->str('starts_at') !== '' ? date('Y-m-d H:i:s', strtotime($req->str('starts_at'))) : null,
                'expires_at' => $req->str('expires_at') !== '' ? date('Y-m-d 23:59:59', strtotime($req->str('expires_at'))) : null,
                'active' => $req->str('active') === '1' ? 1 : 0,
            ];
            $id ? DB::update('coupons', $row, 'id = ?', [$id]) : $id = DB::insert('coupons', $row + ['used' => 0]);
            return $this->back('admin/coupons', __('Codice salvato.'));
        }
        return $this->view('coupons/form', ['title' => $c ? $c['code'] : __('Nuovo codice'), 'c' => $c], 'coupons');
    }

    public function delete(Request $req, array $params): Response
    {
        DB::delete('coupons', 'id = ?', [(int)$params['id']]);
        return $this->back('admin/coupons', __('Codice eliminato.'));
    }
}
