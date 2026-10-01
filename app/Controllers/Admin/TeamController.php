<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\Mailer;
use Alien\Core\Request;
use Alien\Core\Response;

final class TeamController extends AdminController
{
    public function index(Request $req): Response
    {
        if ($req->isPost()) {
            $email = mb_strtolower($req->str('email'));
            $password = (string)($req->post['password'] ?? '');
            $error = match (true) {
                !filter_var($email, FILTER_VALIDATE_EMAIL) => __('Inserisci un indirizzo email valido.'),
                strlen($password) < 8 => __('La password deve avere almeno 8 caratteri.'),
                (bool)DB::row('SELECT id FROM users WHERE email = ?', [$email]) => __('Esiste già un account con questa email.'),
                default => null,
            };
            if ($error) {
                return $this->back('admin/team', $error, 'error');
            }
            Auth::create($email, $password, $req->str('name') ?: $email, 'admin');
            return $this->back('admin/team', __('Collaboratore aggiunto.'));
        }
        return $this->view('team', [
            'title' => __('Collaboratori'),
            'subtitle' => __('Chi può accedere al pannello di amministrazione'),
            'rows' => DB::all("SELECT id, name, email, created_at FROM users WHERE role = 'admin' ORDER BY id"),
            'me' => (int)Auth::user()['id'],
        ], 'settings');
    }

    public function remove(Request $req, array $params): Response
    {
        $id = (int)$params['id'];
        if ($id === (int)Auth::user()['id'] || (int)DB::val("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
            return $this->back('admin/team', __('Non puoi rimuovere te stesso o l\'ultimo amministratore.'), 'error');
        }
        DB::update('users', ['role' => 'customer'], "id = ? AND role = 'admin'", [$id]);
        return $this->back('admin/team', __('Accesso amministratore revocato.'));
    }

    public function reset(Request $req, array $params): Response
    {
        $u = DB::row("SELECT * FROM users WHERE id = ? AND role = 'admin'", [(int)$params['id']]);
        if ($u) {
            \Alien\Services\Notifications::passwordReset($u, url('account/reset/' . Auth::resetToken($u)));
        }
        return $this->back('admin/team', __('Link di reimpostazione inviato.'));
    }
}
