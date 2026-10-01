<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Response;

final class ShippingController extends AdminController
{
    public function index(): Response
    {
        return $this->view('shipping/index', [
            'title' => __('Metodi di spedizione'), 'subtitle' => __('Quanto costa spedire e quando è gratis'),
            'actions' => '<a class="btn" href="' . e(url('admin/shipping/new')) . '">+ ' . e(__('Nuovo metodo')) . '</a>',
            'rows' => DB::all('SELECT * FROM shipping_methods ORDER BY position, id'),
        ], 'shipping');
    }

    public function form(Request $req, array $params = []): Response
    {
        $id = isset($params['id']) ? (int)$params['id'] : null;
        $m = $id ? DB::row('SELECT * FROM shipping_methods WHERE id = ?', [$id]) : null;
        if ($id && !$m) {
            return $this->back('admin/shipping', __('Metodo non trovato.'), 'error');
        }
        if ($req->isPost()) {
            if ($req->str('name') === '') {
                return $this->back($id ? 'admin/shipping/' . $id : 'admin/shipping/new', __('Il nome è obbligatorio.'), 'error');
            }
            $row = [
                'name' => $req->str('name'),
                'price' => Money::parse($req->str('price')),
                'free_over' => Money::parse($req->str('free_over')),
                'countries' => strtoupper(preg_replace('/[^A-Za-z,]/', '', $req->str('countries')) ?? ''),
                'position' => $req->int('position'),
                'active' => $req->str('active') === '1' ? 1 : 0,
            ];
            \Alien\Core\Settings::set('onboard_shipping', 1);
            $id ? DB::update('shipping_methods', $row, 'id = ?', [$id]) : DB::insert('shipping_methods', $row);
            return $this->back('admin/shipping', __('Metodo salvato.'));
        }
        return $this->view('shipping/form', ['title' => $m ? $m['name'] : __('Nuovo metodo'), 'm' => $m], 'shipping');
    }

    public function delete(Request $req, array $params): Response
    {
        DB::delete('shipping_methods', 'id = ?', [(int)$params['id']]);
        return $this->back('admin/shipping', __('Metodo eliminato.'));
    }
}
