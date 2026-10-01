<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Auth;
use Alien\Core\Config;
use Alien\Core\Security;
use Alien\Core\DB;
use Alien\Core\RateLimit;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Session;
use Alien\Core\Totp;
use Alien\Core\TwoFactor;

final class SecurityController extends AdminController
{
    public function index(): Response
    {
        $user = Auth::user();
        Session::start();
        $codes = $_SESSION['totp_codes'] ?? null;
        unset($_SESSION['totp_codes']);
        $pending = TwoFactor::pendingSecret();
        return $this->view('security', [
            'title' => __('Sicurezza dell\'account'),
            'subtitle' => __('Proteggi l\'accesso con la verifica in due passaggi'),
            'enabled' => TwoFactor::enabled((int)$user['id']),
            'pending' => $pending,
            'uri' => $pending ? Totp::uri($pending, (string)$user['email'], (string)setting('store_name', 'AlienShop')) : '',
            'codes' => $codes,
            'csp' => (string)Config::get('app.csp', 'standard'),
            'cspExtra' => implode("\n", Security::extraSources()),
        ], 'settings');
    }

    private function passwordOk(Request $req): bool
    {
        $full = DB::row('SELECT password FROM users WHERE id = ?', [Auth::user()['id']]);
        return RateLimit::hitKey('pw:' . Auth::user()['id'], 8, 900) && $full && password_verify((string)($req->post['password'] ?? ''), $full['password']);
    }

    public function csp(Request $req): Response
    {
        $lines = preg_split('/[\s,]+/', trim((string)($req->post['csp_extra'] ?? ''))) ?: [];
        $hosts = [];
        foreach ($lines as $h) {
            if ($h === '') {
                continue;
            }
            if (!Security::validSource($h)) {
                return $this->back('admin/security', __('Indirizzo non valido: %s (usa il formato https://dominio.it).', mb_substr($h, 0, 60)), 'error');
            }
            $hosts[] = $h;
        }
        $cfg = Config::all();
        $cfg['app']['csp'] = $req->str('csp') === 'off' ? 'off' : 'standard';
        $cfg['app']['csp_extra'] = array_values(array_unique($hosts));
        Config::write($cfg);
        return $this->back('admin/security', __('Impostazioni di sicurezza salvate.'));
    }

    public function start(Request $req): Response
    {
        if (!$this->passwordOk($req)) {
            return $this->back('admin/security', __('Password non corretta.'), 'error');
        }
        TwoFactor::begin((int)Auth::user()['id']);
        return $this->back('admin/security');
    }

    public function confirm(Request $req): Response
    {
        if (!RateLimit::hitKey('2fa-setup:' . Auth::user()['id'], 8, 900)) {
            return $this->back('admin/security', __('Troppi tentativi. Riprova tra qualche minuto.'), 'error');
        }
        $codes = TwoFactor::confirm((int)Auth::user()['id'], $req->str('code'));
        if ($codes === null) {
            return $this->back('admin/security', __('Codice non valido: controlla l\'ora del telefono e riprova.'), 'error');
        }
        $_SESSION['totp_codes'] = $codes;
        return $this->back('admin/security', __('Verifica in due passaggi attivata.'));
    }

    public function disable(Request $req): Response
    {
        $id = (int)Auth::user()['id'];
        if (!$this->passwordOk($req) || !TwoFactor::check($id, $req->str('code'))) {
            return $this->back('admin/security', __('Password o codice non corretti.'), 'error');
        }
        TwoFactor::disable($id);
        return $this->back('admin/security', __('Verifica in due passaggi disattivata.'));
    }

    public function recovery(Request $req): Response
    {
        $id = (int)Auth::user()['id'];
        if (!TwoFactor::enabled($id) || !$this->passwordOk($req)) {
            return $this->back('admin/security', __('Password non corretta.'), 'error');
        }
        Session::start();
        $_SESSION['totp_codes'] = TwoFactor::newRecoveryCodes($id);
        return $this->back('admin/security', __('Nuovi codici di recupero generati: i precedenti non valgono più.'));
    }
}
