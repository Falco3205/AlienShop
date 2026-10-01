<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\Mailer;
use Alien\Core\Settings;
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
                \Alien\Services\Notifications::welcome($email, $req->str('name'));
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
            setcookie('as_admin', '', time() - 3600, \Alien\Core\Session::path());
        }
        return Response::redirect(url());
    }

    public function forgot(Request $req): Response
    {
        $data = [];
        if ($req->isPost()) {
            if ($this->csrfFails($req)) {
                $data['error'] = __('Sessione scaduta, riprova.');
            } elseif (Auth::tooManyAttempts()) {
                $data['error'] = __('Troppi tentativi. Riprova tra qualche minuto.');
            } else {
                Auth::hit();
                $user = DB::row('SELECT * FROM users WHERE email = ?', [mb_strtolower($req->str('email'))]);
                if ($user) {
                    $link = url('account/reset/' . Auth::resetToken($user));
                    \Alien\Services\Notifications::passwordReset($user, $link);
                }
                $data['sent'] = true;
            }
        }
        Seo::set(['title' => __('Password dimenticata')]);
        Seo::noindex();
        return $this->noStore($this->render('account/forgot', $data));
    }

    public function reset(Request $req, array $params): Response
    {
        $user = Auth::userFromResetToken($params['token']);
        $data = ['token' => $params['token'], 'valid' => $user !== null];
        if ($user && $req->isPost()) {
            $password = (string)($req->post['password'] ?? '');
            if ($this->csrfFails($req)) {
                $data['error'] = __('Sessione scaduta, riprova.');
            } elseif (strlen($password) < 8) {
                $data['error'] = __('La password deve avere almeno 8 caratteri.');
            } else {
                Auth::setPassword((int)$user['id'], $password);
                flash('success', __('Password aggiornata. Ora puoi accedere.'));
                return Response::redirect('account/login');
            }
        }
        Seo::set(['title' => __('Reimposta password')]);
        Seo::noindex();
        return $this->noStore($this->render('account/reset', $data));
    }

    public function profile(Request $req): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('account/login');
        }
        if ($this->csrfFails($req)) {
            flash('error', __('Sessione scaduta, riprova.'));
            return Response::redirect('account');
        }
        $name = mb_substr($req->str('name'), 0, 190);
        if ($name === '') {
            flash('error', __('Compila tutti i campi correttamente.'));
            return Response::redirect('account');
        }
        DB::update('users', ['name' => $name, 'phone' => mb_substr($req->str('phone'), 0, 60)], 'id = ?', [$user['id']]);
        $password = (string)($req->post['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                flash('error', __('La password deve avere almeno 8 caratteri.'));
                return Response::redirect('account');
            }
            Auth::setPassword((int)$user['id'], $password);
        }
        flash('success', __('Profilo aggiornato.'));
        return Response::redirect('account');
    }
}
