<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Redirects;

final class RedirectsController extends AdminController
{
    public function index(Request $req): Response
    {
        if ($req->isPost()) {
            if ($req->str('from') !== '') {
                Redirects::add($req->str('from'), $req->str('to'), $req->int('code', 301) === 302 ? 302 : 301);
                return $this->back('admin/redirects', __('Redirect salvato.'));
            }
            if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
                $n = Redirects::importCsv((string)file_get_contents($_FILES['csv']['tmp_name']));
                return $this->back('admin/redirects', __('%d redirect importati.', $n));
            }
            return $this->back('admin/redirects', __('Indica il percorso di origine.'), 'error');
        }
        return $this->view('redirects/index', [
            'title' => __('Redirect 301'),
            'rows' => DB::all('SELECT * FROM redirects ORDER BY id DESC LIMIT 500'),
            'missing' => DB::all('SELECT * FROM not_found_log ORDER BY hits DESC, last_at DESC LIMIT 30'),
        ], 'redirects');
    }

    public function clearMissing(Request $req, array $params): Response
    {
        DB::delete('not_found_log', 'id = ?', [(int)$params['id']]);
        return $this->back('admin/redirects');
    }

    public function delete(Request $req, array $params): Response
    {
        DB::delete('redirects', 'id = ?', [(int)$params['id']]);
        return $this->back('admin/redirects', __('Redirect eliminato.'));
    }
}
