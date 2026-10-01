<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Auth;
use Alien\Core\Cache;
use Alien\Core\Csrf;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Router;

final class Routes
{
    public static function register(Router $r): void
    {
        require_once __DIR__ . '/helpers.php';

        $add = static function (string $method, string $path, string $class, string $action, bool $public = false) use ($r) {
            $r->add($method, $path, static function (Request $req, array $params) use ($class, $action, $public) {
                if (!$public) {
                    if (!Auth::isAdmin()) {
                        return Response::redirect('admin/login');
                    }
                    if ($req->isPost() && !Csrf::valid($req)) {
                        flash('error', __('Sessione scaduta, riprova.'));
                        return Response::redirect($req->header('Referer') ?: 'admin');
                    }
                }
                $res = (new $class())->$action($req, $params);
                if ($req->isPost() && !$public) {
                    Cache::flush();
                }
                return $res;
            });
        };
        $both = static function (string $path, string $class, string $action, bool $public = false) use ($add) {
            $add('GET', $path, $class, $action, $public);
            $add('POST', $path, $class, $action, $public);
        };

        $add('GET', '/admin', DashboardController::class, 'index');
        $both('/admin/login', AuthController::class, 'login', true);
        $add('POST', '/admin/logout', AuthController::class, 'logout');

        $add('GET', '/admin/products', ProductsController::class, 'index');
        $both('/admin/products/new', ProductsController::class, 'form');
        $both('/admin/products/{id}', ProductsController::class, 'form');
        $add('POST', '/admin/products/{id}/delete', ProductsController::class, 'delete');
        $add('POST', '/admin/products-bulk', ProductsController::class, 'bulk');

        $add('GET', '/admin/categories', CategoriesController::class, 'index');
        $both('/admin/categories/new', CategoriesController::class, 'form');
        $both('/admin/categories/{id}', CategoriesController::class, 'form');
        $add('POST', '/admin/categories/{id}/delete', CategoriesController::class, 'delete');

        $add('GET', '/admin/orders', OrdersController::class, 'index');
        $both('/admin/orders/{id}', OrdersController::class, 'show');
        $add('GET', '/admin/customers', OrdersController::class, 'customers');

        foreach (['coupons' => CouponsController::class, 'shipping' => ShippingController::class, 'pages' => PagesController::class] as $slug => $class) {
            $add('GET', "/admin/$slug", $class, 'index');
            $both("/admin/$slug/new", $class, 'form');
            $both("/admin/$slug/{id}", $class, 'form');
            $add('POST', "/admin/$slug/{id}/delete", $class, 'delete');
        }

        $both('/admin/redirects', RedirectsController::class, 'index');
        $add('POST', '/admin/redirects/{id}/delete', RedirectsController::class, 'delete');

        $add('GET', '/admin/themes', ThemesController::class, 'index');
        $add('POST', '/admin/themes/activate', ThemesController::class, 'activate');
        $add('POST', '/admin/themes/customize', ThemesController::class, 'customize');

        $both('/admin/settings', SettingsController::class, 'general');
        $both('/admin/settings/seo', SettingsController::class, 'seo');
        $both('/admin/settings/mail', SettingsController::class, 'mail');
        $both('/admin/payments', SettingsController::class, 'payments');

        $add('GET', '/admin/import', ImportController::class, 'index');
        $add('POST', '/admin/import', ImportController::class, 'import');
        $add('GET', '/admin/export/{format}', ImportController::class, 'export');
    }
}
