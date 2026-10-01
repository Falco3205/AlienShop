<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\Auth;
use Alien\Core\Csrf;
use Alien\Core\RateLimit;
use Alien\Core\TwoFactor;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\View;

final class AuthController extends Controller
{
    public function login(Request $req): Response
    {
        if (Auth::isAdmin()) {
            return Response::redirect('');
        }
        $data = [];
        if ($req->isPost()) {
            $data['email'] = $req->str('email');
            if (!Csrf::valid($req)) {
                $data['error'] = 'Sessione scaduta, riprova.';
            } elseif (Auth::tooManyAttempts()) {
                $data['error'] = 'Troppi tentativi. Riprova tra qualche minuto.';
            } elseif ($user = Auth::attempt($req->str('email'), (string)($req->post['password'] ?? ''), 'admin')) {
                if (TwoFactor::enabled((int)$user['id'])) {
                    TwoFactor::startChallenge($user);
                    return Response::redirect('login/2fa', 303);
                }
                Auth::login($user);
                return Response::redirect('', 303);
            } else {
                $data['error'] = 'Credenziali non valide.';
            }
        }
        return new Response(View::admin('login', $data, false), 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store']);
    }

    public function twoFactor(Request $req): Response
    {
        $user = TwoFactor::challengeUser();
        if (!$user) {
            return Response::redirect('login', 303);
        }
        $data = [];
        if ($req->isPost()) {
            if (!Csrf::valid($req)) {
                $data['error'] = 'Sessione scaduta, riprova.';
            } elseif (!RateLimit::hitKey('2fa:' . $user['id'], 6, 900)) {
                $data['error'] = 'Troppi tentativi. Riprova tra qualche minuto.';
            } elseif (TwoFactor::check((int)$user['id'], $req->str('code'))) {
                TwoFactor::clearChallenge();
                RateLimit::clear('2fa:' . $user['id']);
                Auth::login($user);
                return Response::redirect('', 303);
            } else {
                $data['error'] = 'Codice non valido.';
            }
        }
        return new Response(View::admin('login-2fa', $data, false), 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store']);
    }

    public function logout(): Response
    {
        Auth::logout();
        return Response::redirect('login', 303);
    }

    public function asset(Request $req, array $params): Response
    {
        $allowed = ['admin.css' => 'text/css', 'admin.js' => 'application/javascript', 'favicon.svg' => 'image/svg+xml'];
        $file = $params['file'];
        if (!isset($allowed[$file])) {
            return new Response('Not found', 404);
        }
        return new Response((string)file_get_contents(REPO . '/public/assets/' . $file), 200, ['Content-Type' => $allowed[$file], 'Cache-Control' => 'public, max-age=86400']);
    }
}
