<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;

final class ProfileController extends AdminController
{
    public function form(Request $req): Response
    {
        $user = Auth::user();
        if ($req->isPost()) {
            $full = DB::row('SELECT * FROM users WHERE id = ?', [$user['id']]);
            $email = mb_strtolower($req->str('email'));
            $new = (string)($req->post['new_password'] ?? '');
            $error = match (true) {
                !password_verify((string)($req->post['current_password'] ?? ''), $full['password']) => __('La password attuale non è corretta.'),
                !filter_var($email, FILTER_VALIDATE_EMAIL) => __('Inserisci un indirizzo email valido.'),
                $new !== '' && strlen($new) < 10 => __('La password deve avere almeno 10 caratteri.'),
                (bool)DB::row('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $user['id']]) => __('Esiste già un account con questa email.'),
                default => null,
            };
            if ($error) {
                return $this->back('admin/profile', $error, 'error');
            }
            DB::update('users', ['name' => $req->str('name'), 'email' => $email], 'id = ?', [$user['id']]);
            if ($new !== '') {
                Auth::setPassword((int)$user['id'], $new);
            }
            return $this->back('admin/profile', __('Profilo aggiornato.'));
        }
        return $this->view('profile', ['title' => __('Il mio profilo'), 'u' => $user], 'settings');
    }
}
