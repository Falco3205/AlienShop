<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Session;
use Alien\Payments\Registry;
use Alien\Services\Cart;
use Alien\Services\Orders;
use Alien\Services\Seo;
use Alien\Services\Shipping;

final class CheckoutController extends Controller
{
    public function show(Request $req): Response
    {
        $lines = Cart::lines();
        if (!$lines) {
            return Response::redirect('cart');
        }
        $country = (string)old('country', setting('default_country', 'IT'));
        Seo::set(['title' => __('Checkout')]);
        Seo::noindex();
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $this->noStore($this->render('checkout', [
            'lines' => $lines,
            'totals' => Cart::totals($lines, null, $country),
            'gateways' => Registry::enabled(),
            'countries' => Shipping::countries(),
            'user' => Auth::user(),
            'errors' => $errors,
        ]));
    }

    public function refresh(Request $req): Response
    {
        if ($this->csrfFails($req)) {
            return Response::redirect('checkout');
        }
        $_SESSION['_old'] = array_map(static fn($v) => is_string($v) ? $v : '', $req->post);
        $_SESSION['ship_method'] = $req->int('shipping_method');
        return Response::redirect('checkout');
    }

    public function place(Request $req): Response
    {
        if ($this->csrfFails($req)) {
            return $this->fail($req, [__('Sessione scaduta, riprova.')]);
        }
        $lines = Cart::lines();
        if (!$lines) {
            return Response::redirect('cart');
        }
        $in = fn(string $k) => mb_substr($req->str($k), 0, 190);
        $errors = [];
        $email = mb_strtolower($in('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('Inserisci un indirizzo email valido.');
        }
        foreach (['name' => __('Nome e cognome'), 'address' => __('Indirizzo'), 'city' => __('Città'), 'zip' => __('CAP')] as $k => $label) {
            if ($in($k) === '') {
                $errors[] = __('Campo obbligatorio: %s', $label);
            }
        }
        $country = strtoupper($in('country'));
        if (!isset(Shipping::countries()[$country])) {
            $errors[] = __('Paese non valido.');
        }
        if ($req->str('terms') !== '1') {
            $errors[] = __('Devi accettare i termini e condizioni.');
        }
        $gateway = Registry::enabled()[$req->str('payment_method')] ?? null;
        if (!$gateway) {
            $errors[] = __('Seleziona un metodo di pagamento.');
        }
        $totals = Cart::totals($lines, $req->int('shipping_method'), $country);
        if (!$totals['shipping_method']) {
            $errors[] = __('Nessun metodo di spedizione disponibile per il paese scelto.');
        }
        if ($short = Cart::stockIsAvailable($lines)) {
            $errors[] = __('Prodotto non più disponibile nella quantità richiesta: %s', $short);
        }
        $password = (string)($req->post['password'] ?? '');
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = __('La password deve avere almeno 8 caratteri.');
        }
        if ($errors) {
            return $this->fail($req, $errors);
        }

        $user = Auth::user();
        if (!$user && $password !== '' && !DB::row('SELECT id FROM users WHERE email = ?', [$email])) {
            $uid = Auth::create($email, $password, $in('name'), 'customer', $in('phone'));
            $user = DB::row('SELECT id, email, name, phone, role FROM users WHERE id = ?', [$uid]);
            Auth::login($user);
        }

        $address = [
            'name' => $in('name'), 'phone' => $in('phone'), 'address' => $in('address'),
            'city' => $in('city'), 'zip' => $in('zip'), 'state' => $in('state'), 'country' => $country,
        ];
        $order = Orders::create($lines, $totals, [
            'email' => $email, 'billing' => $address, 'shipping' => $address, 'note' => mb_substr($req->str('note'), 0, 1000),
        ], $gateway->id(), $user ? (int)$user['id'] : null);

        $start = $gateway->start($order);
        if (isset($start['error'])) {
            Orders::setStatus((int)$order['id'], 'cancelled');
            return $this->fail($req, [$start['error']]);
        }
        unset($_SESSION['_old']);
        if (isset($start['redirect'])) {
            DB::update('orders', ['payment_ref' => (string)($start['ref'] ?? '')], 'id = ?', [$order['id']]);
            return Response::redirect($start['redirect']);
        }
        Cart::clear();
        Orders::notify(Orders::find((int)$order['id']));
        return Response::redirect('checkout/thank-you/' . $order['token']);
    }

    private function fail(Request $req, array $errors): Response
    {
        Session::start();
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = array_map(static fn($v) => is_string($v) ? $v : '', $req->post);
        return Response::redirect('checkout');
    }

    public function thanks(Request $req, array $params): Response
    {
        $order = Orders::findByToken($params['token']);
        if (!$order) {
            return $this->missing($req);
        }
        Seo::set(['title' => __('Ordine %s', $order['number'])]);
        Seo::noindex();
        return $this->noStore($this->render('thank-you', ['order' => $order, 'gateway' => Registry::get($order['payment_method'])]));
    }

    public function paymentReturn(Request $req, array $params): Response
    {
        $order = Orders::findByToken($req->str('order'));
        $gateway = Registry::get($params['gateway']);
        if (!$order || !$gateway || $order['payment_method'] !== $gateway->id()) {
            return $this->missing($req);
        }
        if ($gateway->handleReturn($req, $order)) {
            Cart::clear();
        } else {
            flash('error', __('Non abbiamo ancora ricevuto la conferma del pagamento. Se hai già pagato, riceverai una email appena confermato.'));
        }
        return Response::redirect('checkout/thank-you/' . $order['token']);
    }

    public function paymentCancel(Request $req, array $params): Response
    {
        $order = Orders::findByToken($params['token']);
        if ($order && $order['payment_status'] !== 'paid') {
            Orders::setStatus((int)$order['id'], 'cancelled');
            flash('error', __('Pagamento annullato. Puoi riprovare.'));
        }
        return Response::redirect('checkout');
    }
}
