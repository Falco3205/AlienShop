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
        $add('POST', '/admin/dismiss-onboarding', DashboardController::class, 'dismiss');
        $add('POST', '/admin/remove-demo', DashboardController::class, 'removeDemo');
        $add('GET', '/admin/search', SearchController::class, 'index');
        $both('/admin/login', AuthController::class, 'login', true);
        $add('POST', '/admin/logout', AuthController::class, 'logout');

        $add('GET', '/admin/products', ProductsController::class, 'index');
        $both('/admin/products/new', ProductsController::class, 'form');
        $both('/admin/products/{id}', ProductsController::class, 'form');
        $add('POST', '/admin/products/{id}/delete', ProductsController::class, 'delete');
        $add('POST', '/admin/products/{id}/duplicate', ProductsController::class, 'duplicate');
        $add('POST', '/admin/products-bulk', ProductsController::class, 'bulk');

        $add('GET', '/admin/categories', CategoriesController::class, 'index');
        $both('/admin/categories/new', CategoriesController::class, 'form');
        $both('/admin/categories/{id}', CategoriesController::class, 'form');
        $add('POST', '/admin/categories/{id}/delete', CategoriesController::class, 'delete');

        $add('GET', '/admin/orders', OrdersController::class, 'index');
        $both('/admin/orders/{id}', OrdersController::class, 'show');
        $add('GET', '/admin/orders/{id}/print', OrdersController::class, 'printSlip');
        $add('GET', '/admin/customers', OrdersController::class, 'customers');

        foreach (['coupons' => CouponsController::class, 'shipping' => ShippingController::class, 'pages' => PagesController::class] as $slug => $class) {
            $add('GET', "/admin/$slug", $class, 'index');
            $both("/admin/$slug/new", $class, 'form');
            $both("/admin/$slug/{id}", $class, 'form');
            $add('POST', "/admin/$slug/{id}/delete", $class, 'delete');
        }

        $both('/admin/redirects', RedirectsController::class, 'index');
        $add('POST', '/admin/redirects/{id}/dismiss', RedirectsController::class, 'clearMissing');
        $add('POST', '/admin/redirects/{id}/delete', RedirectsController::class, 'delete');

        $add('GET', '/admin/themes', ThemesController::class, 'index');
        $add('POST', '/admin/themes/activate', ThemesController::class, 'activate');
        $add('POST', '/admin/themes/customize', ThemesController::class, 'customize');

        $add('GET', '/admin/reviews', ReviewsController::class, 'index');
        $add('POST', '/admin/reviews/settings', ReviewsController::class, 'settings');
        $add('POST', '/admin/reviews/{id}', ReviewsController::class, 'action');
        $add('GET', '/admin/newsletter', NewsletterController::class, 'index');
        $add('POST', '/admin/newsletter/send', NewsletterController::class, 'send');
        $add('GET', '/admin/newsletter/export', NewsletterController::class, 'export');
        $add('POST', '/admin/newsletter/{id}/delete', NewsletterController::class, 'delete');
        $add('GET', '/admin/abandoned', AbandonedController::class, 'index');
        $add('POST', '/admin/abandoned/settings', AbandonedController::class, 'settings');
        $add('POST', '/admin/abandoned/{id}/remind', AbandonedController::class, 'remind');
        $add('GET', '/admin/invoices', InvoicesController::class, 'index');
        $add('POST', '/admin/invoices/settings', InvoicesController::class, 'settings');
        $add('GET', '/admin/invoices/export', InvoicesController::class, 'export');
        $add('GET', '/admin/orders/{id}/invoice', InvoicesController::class, 'download');
        $add('POST', '/admin/orders/{id}/invoice/send', InvoicesController::class, 'send');
        $add('GET', '/admin/backup', BackupController::class, 'index');
        $add('POST', '/admin/backup/download', BackupController::class, 'download');
        $add('GET', '/admin/modules', ModulesController::class, 'index');
        $add('POST', '/admin/modules/{id}/toggle', ModulesController::class, 'toggle');
        $both('/admin/team', TeamController::class, 'index');
        $add('POST', '/admin/team/{id}/remove', TeamController::class, 'remove');
        $add('POST', '/admin/team/{id}/reset', TeamController::class, 'reset');
        $add('GET', '/admin/orders-export', OrdersController::class, 'export');
        $add('GET', '/admin/einvoice', EInvoiceController::class, 'index');
        $both('/admin/einvoice/setup', EInvoiceController::class, 'wizard');
        $add('POST', '/admin/einvoice/sync', EInvoiceController::class, 'sync');
        $add('GET', '/admin/einvoice/{id}', EInvoiceController::class, 'show');
        $add('POST', '/admin/einvoice/{id}', EInvoiceController::class, 'action');
        $add('GET', '/admin/einvoice/{id}/xml', EInvoiceController::class, 'xml');
        $add('GET', '/admin/einvoice/{id}/pdf', EInvoiceController::class, 'pdf');
        $add('POST', '/admin/orders/{id}/einvoice', EInvoiceController::class, 'orderInvoice');
        $add('GET', '/admin/purchases', PurchasesController::class, 'index');
        $add('POST', '/admin/purchases/upload', PurchasesController::class, 'upload');
        $add('POST', '/admin/purchases/sync', PurchasesController::class, 'sync');
        $both('/admin/purchases/{id}', PurchasesController::class, 'show');
        $add('GET', '/admin/purchases/{id}/xml', PurchasesController::class, 'xml');
        $both('/admin/expenses', ExpensesController::class, 'index');
        $add('POST', '/admin/expenses/{id}/delete', ExpensesController::class, 'delete');
        $add('GET', '/admin/accounting', AccountingController::class, 'index');
        $add('GET', '/admin/accounting/export/{kind}', AccountingController::class, 'export');
        $add('GET', '/admin/stats', StatsController::class, 'index');
        $both('/admin/legal', LegalController::class, 'wizard');
        $both('/admin/analytics', AnalyticsController::class, 'wizard');
        $both('/admin/profile', ProfileController::class, 'form');
        $add('GET', '/admin/settings', SettingsController::class, 'hub');
        $both('/admin/settings/general', SettingsController::class, 'general');
        $both('/admin/settings/seo', SettingsController::class, 'seo');
        $both('/admin/settings/mail', SettingsController::class, 'mail');
        $both('/admin/payments', SettingsController::class, 'payments');

        $add('GET', '/admin/import', ImportController::class, 'index');
        $add('POST', '/admin/import', ImportController::class, 'import');
        $add('GET', '/admin/export/{format}', ImportController::class, 'export');
    }
}
