<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Config;
use Alien\Core\Lang;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\View;
use Alien\Services\Installer;
use Alien\Services\Shipping;
use Alien\Services\Themes;

final class InstallController
{
    public function handle(Request $req): Response
    {
        $lang = in_array($req->str('lang'), ['it', 'en'], true) ? $req->str('lang') : 'it';
        Lang::load($lang);

        if ($req->path === '/install/test-db' && $req->isPost()) {
            if (!Installer::keyOk($req->post)) {
                return Response::json(['ok' => false, 'message' => __('Chiave di installazione non valida.')], 403);
            }
            $err = Installer::testDb(Installer::dbConfig($req->post));
            return Response::json(['ok' => $err === null, 'message' => $err ?? __('Connessione riuscita')]);
        }
        if ($req->path !== '/install') {
            return Response::redirect(rtrim(Config::baseUrl(), '/') . '/install', 302);
        }

        $errors = [];
        $data = $req->post + ['lang' => $lang];
        if ($req->isPost()) {
            $errors = Installer::keyOk($req->post) ? Installer::validate($req->post) : [__('Chiave di installazione non valida.')];
            if (!$errors) {
                try {
                    Installer::install($req->post);
                    return Response::redirect(rtrim(Config::baseUrl(), '/') . '/admin', 302);
                } catch (\Throwable $e) {
                    @unlink(ROOT . '/config/config.php');
                    $errors[] = $e->getMessage();
                }
            }
        }

        return Response::html(View::install('wizard', [
            'lang' => $lang,
            'errors' => $errors,
            'data' => $data + [
                'db_driver' => extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql', 'db_host' => 'localhost', 'db_port' => 3306,
                'store_name' => '', 'store_email' => '', 'currency' => 'EUR', 'country' => $lang === 'en' ? 'GB' : 'IT',
                'tax_rate' => 22, 'prices_include_tax' => 1, 'demo' => 1, 'theme' => 'aurora', 'app_url' => Config::baseUrl(),
            ],
            'keyRequired' => Installer::installKey() !== '',
            'requirements' => Installer::requirements(),
            'themes' => Themes::all(),
            'currencies' => Money::currencies(),
            'countries' => Shipping::countries(),
        ]));
    }
}
