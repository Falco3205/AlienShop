<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Config;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Secret;
use Alien\Core\Settings;
use Alien\Services\Updater;

final class UpdatesController extends AdminController
{
    public function index(Request $req): Response
    {
        $st = Updater::status($req->str('check') === '1');
        $state = Updater::state();
        return $this->view('updates', [
            'title' => __('Aggiornamenti'),
            'subtitle' => __('Tieni il negozio allineato a GitHub'),
            'st' => $st,
            'version' => ALIEN_VERSION,
            'canRollback' => Updater::method() === 'git' ? !empty($state['previous']) : (bool)glob(ROOT . '/storage/backups/code-*.zip'),
            'writable' => Updater::writable(),
            'zip' => Updater::zipAvailable(),
            'secret' => Updater::webhookSecret(),
            'hookUrl' => Config::baseUrl() . '/webhooks/github',
            'hasToken' => (string)Settings::get('update_token', '') !== '',
            'applied' => (string)Settings::get('update_applied_at', ''),
        ], 'updates');
    }

    public function check(): Response
    {
        $st = Updater::status(true);
        return $this->back('admin/updates', $st['error'] ?: ($st['available'] ? __('È disponibile un aggiornamento.') : __('Il negozio è già aggiornato.')), $st['error'] ? 'error' : 'success');
    }

    public function apply(): Response
    {
        @set_time_limit(300);
        $r = Updater::apply();
        return $this->back('admin/updates', $r['message'], $r['ok'] ? 'success' : 'error');
    }

    public function rollback(): Response
    {
        $r = Updater::rollback();
        return $this->back('admin/updates', $r['message'], $r['ok'] ? 'success' : 'error');
    }

    public function settings(Request $req): Response
    {
        Settings::set('update_repo', $req->str('update_repo'));
        Settings::set('update_branch', $req->str('update_branch'));
        Settings::set('update_auto', $req->str('update_auto') === '1' ? '1' : '0');
        if ($req->str('update_token') !== '') {
            Settings::set('update_token', Secret::seal($req->str('update_token')));
        }
        if ($req->str('clear_token') === '1') {
            Settings::set('update_token', '');
        }
        if ($req->str('regenerate') === '1') {
            Updater::regenerateSecret();
        }
        Settings::set('update_latest', '');
        return $this->back('admin/updates', __('Impostazioni salvate.'));
    }
}
