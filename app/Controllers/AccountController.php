<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Orders;
use Alien\Services\Seo;

final class AccountController extends Controller
{
    public function index(Request $req): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('account/login');
        }
        Seo::set(['title' => __('Il mio account')]);
        Seo::noindex();
        return $this->noStore($this->render('account/index', ['user' => $user, 'orders' => Orders::forUser((int)$user['id'])]));
    }

    public function login(Request $req): Response
    {
        $data = [];
        if ($req->isPost()) {
            if ($this->csrfFails($req)) {
                $data['error'] = __('Sessione scaduta, riprova.');
            } elseif (Auth::tooManyAttempts()) {
                $data['error'] = __('Troppi tentativi. Riprova tra qualche minuto.');
            } elseif ($user = Auth::attempt($req->str('email'), (string)($req->post['password'] ?? ''))) {
                Auth::login($user);
                return Response::redirect($user['role'] === 'admin' ? 'admin' : 'account');
            } else {
                $data['error'] = __('Credenziali non valide.');
            }
            $data['email'] = $req->str('email');
        }
        Seo::set(['title' => __('Accedi')]);
        Seo::noindex();
        return $this->noStore($this->render('account/login', $data));
    }

    public function register(Request $req): Response
    {
        $data = [];
        if ($req->isPost()) {
            $email = mb_strtolower($req->str('email'));
            $password = (string)($req->post['password'] ?? '');
            $data = ['email' => $email, 'name' => $req->str('name')];
            if ($this->csrfFails($req)) {
                $data['error'] = __('Sessione scaduta, riprova.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $req->str('name') === '') {
                $data['error'] = __('Compila tutti i campi correttamente.');
            } elseif (strlen($password) < 8) {
                $data['error'] = __('La password deve avere almeno 8 caratteri.');
            } elseif (DB::row('SELECT id FROM users WHERE email = ?', [$email])) {
                $data['error'] = __('Esiste già un account con questa email.');
            } else {
                $id = Auth::create($email, $password, mb_substr($req->str('name'), 0, 190));
                Auth::login(DB::row('SELECT id, email, name, phone, role FROM users WHERE id = ?', [$id]));
                return Response::redirect('account');
            }
        }
        Seo::set(['title' => __('Crea account')]);
        Seo::noindex();
        return $this->noStore($this->render('account/register', $data));
    }

    public function logout(Request $req): Response
    {
        if (!$this->csrfFails($req)) {
            Auth::logout();
            setcookie('as_admin', '', time() - 3600, '/');
        }
        return Response::redirect(url());
    }
}
