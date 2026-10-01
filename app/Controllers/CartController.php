<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Cart;
use Alien\Services\Seo;

final class CartController extends Controller
{
    public function show(Request $req): Response
    {
        $lines = Cart::lines();
        Seo::set(['title' => __('Carrello')]);
        Seo::noindex();
        return $this->noStore($this->render('cart', ['lines' => $lines, 'totals' => Cart::totals($lines)]));
    }

    public function add(Request $req): Response
    {
        $options = [];
        foreach ((array)($req->post['opt'] ?? []) as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $options[$k] = $v;
            }
        }
        $error = Cart::add($req->int('product_id'), $req->int('variant_id'), $options, max(1, min(99, $req->int('qty', 1))));
        if ($req->isAjax()) {
            return Response::json(
                ['ok' => $error === null, 'count' => Cart::count(), 'message' => $error ?? __('Aggiunto al carrello')],
                $error === null ? 200 : 422
            );
        }
        if ($error) {
            flash('error', $error);
            return Response::redirect($req->header('Referer') ?: 'cart');
        }
        return Response::redirect('cart');
    }

    public function update(Request $req): Response
    {
        if ($remove = (string)($req->post['remove'] ?? '')) {
            Cart::setQty($remove, 0);
        } else {
            foreach ((array)($req->post['qty'] ?? []) as $key => $qty) {
                Cart::setQty((string)$key, (int)$qty);
            }
        }
        return Response::redirect('cart');
    }

    public function coupon(Request $req): Response
    {
        $code = $req->str('code');
        if ($code === '') {
            Cart::removeCoupon();
        } elseif ($error = Cart::applyCoupon($code)) {
            flash('error', $error);
        } else {
            flash('success', __('Codice sconto applicato.'));
        }
        return Response::redirect('cart');
    }
}
