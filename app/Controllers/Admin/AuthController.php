<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Auth;
use Alien\Core\Csrf;
use Alien\Core\RateLimit;
use Alien\Core\TwoFactor;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\View;

final class AuthController extends AdminController
{
    public function login(Request $req): Response
    {
        if (Auth::isAdmin()) {
            return Response::redirect('admin');
        }
        $data = [];
        if ($req->isPost()) {
            $data['email'] = $req->str('email');
            if (!Csrf::valid($req)) {
                $data['error'] = __('Sessione scaduta, riprova.');
            } elseif (Auth::tooManyAttempts()) {
                $data['error'] = __('Troppi tentativi. Riprova tra qualche minuto.');
            } elseif ($user = Auth::attempt($req->str('email'), (string)($req->post['password'] ?? ''), 'admin')) {
                if (TwoFactor::enabled((int)$user['id'])) {
                    TwoFactor::startChallenge($user);
                    return Response::redirect('admin/login/2fa', 303);
                }
                return $this->finish($user);
            } else {
                $data['error'] = __('Credenziali non valide.');
            }
        }
        return new Response(View::admin('login', $data, false), 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store']);
    }

    private function finish(array $user): Response
    {
        Auth::login($user);
        setcookie('as_admin', '1', ['path' => \Alien\Core\Session::path(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
        return Response::redirect('admin', 303);
    }

    public function twoFactor(Request $req): Response
    {
        $user = TwoFactor::challengeUser();
        if (!$user) {
            return Response::redirect('admin/login', 303);
        }
        $data = [];
        if ($req->isPost()) {
            if (!Csrf::valid($req)) {
                $data['error'] = __('Sessione scaduta, riprova.');
            } elseif (!RateLimit::hitKey('2fa:' . $user['id'], 6, 900)) {
                $data['error'] = __('Troppi tentativi. Riprova tra qualche minuto.');
            } elseif (TwoFactor::check((int)$user['id'], $req->str('code'))) {
                TwoFactor::clearChallenge();
                RateLimit::clear('2fa:' . $user['id']);
                return $this->finish($user);
            } else {
                $data['error'] = __('Codice non valido.');
            }
        }
        return new Response(View::admin('login-2fa', $data, false), 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store']);
    }

    public function logout(): Response
    {
        Auth::logout();
        setcookie('as_admin', '', time() - 3600, \Alien\Core\Session::path());
        return Response::redirect('admin/login');
    }
}
