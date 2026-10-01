<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Modules;

final class ModulesController extends AdminController
{
    public function index(): Response
    {
        return $this->view('modules', [
            'title' => __('Estensioni'),
            'subtitle' => __('Attiva solo quello che ti serve: tutto è gratuito'),
            'modules' => Modules::all(),
            'cronLast' => setting('cron_last'),
        ], 'modules');
    }

    public function toggle(Request $req, array $params): Response
    {
        $id = $params['id'];
        $all = Modules::all();
        if (!isset($all[$id])) {
            return $this->back('admin/modules', __('Estensione non trovata.'), 'error');
        }
        $enable = !Modules::on($id);
        Modules::set($id, $enable);
        return $this->back('admin/modules', ($enable ? __('%s attivata.', $all[$id]['name']) : __('%s disattivata.', $all[$id]['name'])));
    }
}
