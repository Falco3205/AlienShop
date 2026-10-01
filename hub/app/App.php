<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\Auth;
use Alien\Core\Config;
use Alien\Core\Csrf;
use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Router;
use Hub\Controllers\ApiController;
use Hub\Controllers\AuthController;
use Hub\Controllers\DashboardController;
use Hub\Controllers\JobsController;
use Hub\Controllers\NodesController;
use Hub\Controllers\SettingsController;
use Hub\Controllers\ShopsController;

final class App
{
    public static function run(): void
    {
        set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($msg, 0, $no, $file, $line);
        });
        try {
            if (!Config::installed()) {
                self::send(new Response('Hub non installato. Esegui: php hub/bin/hub install --url=... --admin-email=... --admin-password=...', 503, ['Content-Type' => 'text/plain; charset=utf-8']));
                return;
            }
            DB::boot();
            $req = Request::capture();
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: same-origin');
            if (is_https()) {
                header('Strict-Transport-Security: max-age=31536000');
            }
            $router = new Router();
            self::routes($router);
            $res = $router->dispatch($req) ?? new Response('Pagina non trovata', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
            self::send($res);
            if (!str_starts_with($req->path, '/api/')) {
                Poller::maybe();
            }
        } catch (\Throwable $e) {
            @mkdir(ROOT . '/storage/logs', 0750, true);
            @file_put_contents(ROOT . '/storage/logs/error.log', '[' . now() . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo 'Errore interno.';
        }
    }

    private static function send(Response $r): void
    {
        $r->send();
    }

    private static function routes(Router $r): void
    {
        $guard = static function (string $class, string $action, bool $public = false) {
            return static function (Request $req, array $params) use ($class, $action, $public) {
                if (!$public) {
                    if (!Auth::isAdmin()) {
                        return Response::redirect('login');
                    }
                    if ($req->isPost() && !Csrf::valid($req)) {
                        flash('error', 'Sessione scaduta, riprova.');
                        return Response::redirect($req->header('Referer') ?: '');
                    }
                }
                return (new $class())->$action($req, $params);
            };
        };
        $add = static function (string $method, string $path, string $class, string $action, bool $public = false) use ($r, $guard) {
            $r->add($method, $path, $guard($class, $action, $public));
        };
        $both = static function (string $path, string $class, string $action, bool $public = false) use ($add) {
            $add('GET', $path, $class, $action, $public);
            $add('POST', $path, $class, $action, $public);
        };

        $both('/login', AuthController::class, 'login', true);
        $add('POST', '/logout', AuthController::class, 'logout');
        $add('GET', '/assets/{file}', AuthController::class, 'asset', true);

        $add('GET', '/', DashboardController::class, 'index');
        $add('POST', '/poll', DashboardController::class, 'poll');

        $add('GET', '/shops', ShopsController::class, 'index');
        $both('/shops/new', ShopsController::class, 'create');
        $add('GET', '/shops/{id}', ShopsController::class, 'show');
        foreach (['poll', 'update', 'open', 'suspend', 'unsuspend', 'notes', 'retry', 'forget-password'] as $a) {
            $add('POST', '/shops/{id}/' . $a, ShopsController::class, lcfirst(str_replace('-', '', ucwords($a, '-'))));
        }

        $add('GET', '/nodes', NodesController::class, 'index');
        $both('/nodes/new', NodesController::class, 'create');
        $add('GET', '/nodes/{id}', NodesController::class, 'show');
        $add('POST', '/nodes/{id}/token', NodesController::class, 'token');

        $add('GET', '/jobs', JobsController::class, 'index');
        $add('GET', '/jobs/{id}', JobsController::class, 'show');

        $both('/settings', SettingsController::class, 'index');
        $add('POST', '/settings/update', SettingsController::class, 'update');

        $add('POST', '/api/node/poll', ApiController::class, 'poll', true);
        $add('POST', '/api/node/jobs/{id}', ApiController::class, 'result', true);
        $add('POST', '/api/node/claim', ApiController::class, 'claim', true);
    }
}
