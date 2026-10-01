<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Hub\SelfUpdate;

final class SettingsController extends Controller
{
    public function index(Request $req): Response
    {
        $error = null;
        if ($req->isPost()) {
            $repo = $req->str('shop_repo');
            $branch = $req->str('shop_branch');
            if (!preg_match('#^[\w.-]+/[\w.-]+$#', $repo) || !preg_match('#^[\w./-]+$#', $branch)) {
                $error = 'Repository o branch non validi.';
            } elseif (!filter_var($req->str('default_admin_email'), FILTER_VALIDATE_EMAIL)) {
                $error = 'Email predefinita non valida.';
            } else {
                Settings::setMany(['shop_repo' => $repo, 'shop_branch' => $branch, 'default_theme' => $req->str('default_theme') ?: 'aurora', 'default_lang' => $req->str('default_lang') === 'en' ? 'en' : 'it', 'default_admin_email' => $req->str('default_admin_email')]);
                $new = (string)($req->post['new_password'] ?? '');
                if ($new !== '') {
                    if (strlen($new) < 10) {
                        $error = 'La nuova password deve avere almeno 10 caratteri.';
                    } else {
                        DB::update('users', ['password' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [Auth::user()['id']]);
                    }
                }
                if (!$error) {
                    return $this->back('settings', 'Impostazioni salvate.');
                }
            }
        }
        return $this->view('settings', [
            'title' => 'Impostazioni',
            'subtitle' => 'Valori predefiniti per i nuovi negozi e aggiornamento dell\'hub',
            'error' => $error,
            'git' => is_dir(REPO . '/.git'),
        ], 'settings');
    }

    public function update(): Response
    {
        $r = SelfUpdate::run();
        return $this->back('settings', $r['message'], $r['ok'] ? 'success' : 'error');
    }
}
