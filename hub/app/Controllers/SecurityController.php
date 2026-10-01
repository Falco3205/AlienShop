<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\RateLimit;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Session;
use Alien\Core\Totp;
use Alien\Core\TwoFactor;

final class SecurityController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        Session::start();
        $codes = $_SESSION['totp_codes'] ?? null;
        unset($_SESSION['totp_codes']);
        $pending = TwoFactor::pendingSecret();
        return $this->view('security', [
            'title' => 'Sicurezza dell\'account',
            'subtitle' => 'Proteggi l\'accesso con la verifica in due passaggi',
            'enabled' => TwoFactor::enabled((int)$user['id']),
            'pending' => $pending,
            'uri' => $pending ? Totp::uri($pending, (string)$user['email'], 'AlienShop Hub') : '',
            'codes' => $codes,
        ], 'security');
    }

    private function passwordOk(Request $req): bool
    {
        $full = DB::row('SELECT password FROM users WHERE id = ?', [Auth::user()['id']]);
        return RateLimit::hitKey('pw:' . Auth::user()['id'], 8, 900) && $full && password_verify((string)($req->post['password'] ?? ''), $full['password']);
    }

    public function start(Request $req): Response
    {
        if (!$this->passwordOk($req)) {
            return $this->back('security', 'Password non corretta.', 'error');
        }
        TwoFactor::begin((int)Auth::user()['id']);
        return $this->back('security');
    }

    public function confirm(Request $req): Response
    {
        if (!RateLimit::hitKey('2fa-setup:' . Auth::user()['id'], 8, 900)) {
            return $this->back('security', 'Troppi tentativi. Riprova tra qualche minuto.', 'error');
        }
        $codes = TwoFactor::confirm((int)Auth::user()['id'], $req->str('code'));
        if ($codes === null) {
            return $this->back('security', 'Codice non valido: controlla l\'ora del telefono e riprova.', 'error');
        }
        $_SESSION['totp_codes'] = $codes;
        return $this->back('security', 'Verifica in due passaggi attivata.');
    }

    public function disable(Request $req): Response
    {
        $id = (int)Auth::user()['id'];
        if (!$this->passwordOk($req) || !TwoFactor::check($id, $req->str('code'))) {
            return $this->back('security', 'Password o codice non corretti.', 'error');
        }
        TwoFactor::disable($id);
        return $this->back('security', 'Verifica in due passaggi disattivata.');
    }

    public function recovery(Request $req): Response
    {
        $id = (int)Auth::user()['id'];
        if (!TwoFactor::enabled($id) || !$this->passwordOk($req)) {
            return $this->back('security', 'Password non corretta.', 'error');
        }
        Session::start();
        $_SESSION['totp_codes'] = TwoFactor::newRecoveryCodes($id);
        return $this->back('security', 'Nuovi codici di recupero generati: i precedenti non valgono più.');
    }
}
