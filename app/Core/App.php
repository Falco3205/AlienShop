<?php
declare(strict_types=1);

namespace Alien\Core;

use Alien\Controllers\Admin;
use Alien\Controllers\AccountController;
use Alien\Controllers\CartController;
use Alien\Controllers\ContactController;
use Alien\Controllers\CheckoutController;
use Alien\Controllers\HubController;
use Alien\Controllers\InstallController;
use Alien\Controllers\NewsletterController;
use Alien\Controllers\SeoController;
use Alien\Controllers\ShopController;
use Alien\Controllers\WebhookController;
use Alien\Services\Catalog;
use Alien\Services\Cron;
use Alien\Services\Migrator;
use Alien\Services\Redirects;
use Alien\Services\Seo;

final class App
{
    private const CACHEABLE = ['#^/$#', '#^/products/[^/]+$#', '#^/collections/[^/]+$#', '#^/pages/[^/]+$#', '#^/blog(/[^/]+)?$#'];

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
                self::send((new InstallController())->handle(Request::capture()));
                return;
            }
            $req = Request::capture();
            self::securityHeaders();

            if ($redirect = self::canonicalRedirect($req)) {
                self::send($redirect);
                return;
            }

            $cacheKey = self::cacheKey($req);
            if ($cacheKey && ($hit = Cache::get($cacheKey, (int)Config::get('app.cache_ttl', 900)))) {
                self::sendCached($hit, $req);
                return;
            }

            DB::boot();
            if (Migrator::needed()) {
                Migrator::run();
            }
            Lang::load((string)Settings::get('locale', 'it'));
            $response = self::dispatch($req);
            if ($cacheKey && $response->status === 200 && empty($GLOBALS['as_flash_shown'])) {
                Cache::put($cacheKey, $response->body);
            }
            self::send($response);
            if (!str_starts_with($req->path, '/webhooks/') && !str_starts_with($req->path, '/hub/')) {
                Cron::maybeRun();
            }
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    private static function dispatch(Request $req): Response
    {
        $router = new Router();
        self::routes($router);
        View::share('nav', self::navigation());
        if ($req->isPost() && !str_starts_with($req->path, '/webhooks/') && !str_starts_with($req->path, '/hub/') && !$req->sameOrigin()) {
            return new Response('Forbidden', 403);
        }
        $response = $router->dispatch($req);
        if ($response) {
            return $response;
        }
        if (($r = Redirects::resolve($req->path)) !== null) {
            $to = $r['to_path'];
            return Response::redirect(preg_match('#^https?://#', $to) ? $to : url($to), (int)$r['code']);
        }
        return self::notFound();
    }

    public static function notFound(): Response
    {
        Redirects::logMissing(Request::current()->path);
        Seo::set(['title' => __('Pagina non trovata')]);
        Seo::noindex();
        return Response::notFound(View::render('404'));
    }

    private static function routes(Router $r): void
    {
        $r->get('/', [ShopController::class, 'home']);
        $r->get('/collections/{slug}', [ShopController::class, 'collection']);
        $r->get('/products/{slug}', [ShopController::class, 'product']);
        $r->post('/t', [ShopController::class, 'beacon']);
        $r->post('/products/{slug}/reviews', [ShopController::class, 'review']);
        $r->post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
        $r->get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm']);
        $r->get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe']);
        $r->post('/products/{slug}/notify', [ShopController::class, 'notify']);
        $r->get('/contact', [ContactController::class, 'form']);
        $r->post('/contact', [ContactController::class, 'send']);
        $r->get('/track', [ContactController::class, 'trackForm']);
        $r->post('/track', [ContactController::class, 'track']);
        $r->get('/wishlist', [ShopController::class, 'wishlistPage']);
        $r->get('/wishlist/cards', [ShopController::class, 'wishlistCards']);
        $r->get('/search', [ShopController::class, 'search']);
        $r->get('/pages/{slug}', [ShopController::class, 'page']);
        $r->get('/blog', [ShopController::class, 'blog']);
        $r->get('/blog/{slug}', [ShopController::class, 'post']);
        $r->get('/shop', static fn() => Response::redirect('collections/all', 301));
        $r->get('/product/{slug}', static fn($q, $p) => Response::redirect('products/' . $p['slug'], 301));
        $r->get('/product-category/{path*}', [ShopController::class, 'legacyCategory']);
        $r->get('/collections/{c}/products/{slug}', static fn($q, $p) => Response::redirect('products/' . $p['slug'], 301));

        $r->get('/cart', [CartController::class, 'show']);
        $r->post('/cart/add', [CartController::class, 'add']);
        $r->post('/cart/update', [CartController::class, 'update']);
        $r->post('/cart/coupon', [CartController::class, 'coupon']);

        $r->get('/checkout', [CheckoutController::class, 'show']);
        $r->post('/checkout', [CheckoutController::class, 'place']);
        $r->post('/checkout/capture', [CheckoutController::class, 'capture']);
        $r->get('/cart/recover/{token}', [CartController::class, 'recover']);
        $r->post('/checkout/refresh', [CheckoutController::class, 'refresh']);
        $r->get('/checkout/thank-you/{token}', [CheckoutController::class, 'thanks']);
        $r->get('/invoice/{token}', [CheckoutController::class, 'invoice']);
        $r->get('/pay/return/{gateway}', [CheckoutController::class, 'paymentReturn']);
        $r->get('/pay/cancel/{token}', [CheckoutController::class, 'paymentCancel']);
        $r->get('/hub/ping', [HubController::class, 'ping']);
        $r->get('/hub/stats', [HubController::class, 'stats']);
        $r->post('/hub/update', [HubController::class, 'update']);
        $r->post('/hub/sso', [HubController::class, 'sso']);
        $r->get('/hub/login', [HubController::class, 'login']);
        $r->post('/webhooks/github', [WebhookController::class, 'github']);
        $r->post('/webhooks/{gateway}', [WebhookController::class, 'handle']);

        $r->get('/account', [AccountController::class, 'index']);
        $r->any('/account/login', [AccountController::class, 'login']);
        $r->any('/account/register', [AccountController::class, 'register']);
        $r->post('/account/logout', [AccountController::class, 'logout']);
        $r->any('/account/forgot', [AccountController::class, 'forgot']);
        $r->any('/account/reset/{token}', [AccountController::class, 'reset']);
        $r->post('/account/profile', [AccountController::class, 'profile']);

        $r->get('/sitemap.xml', [SeoController::class, 'sitemapIndex']);
        $r->get('/sitemap-pages.xml', [SeoController::class, 'sitemapPages']);
        $r->get('/sitemap-categories.xml', [SeoController::class, 'sitemapCategories']);
        $r->get('/sitemap-products-{n}.xml', [SeoController::class, 'sitemapProducts']);
        $r->get('/robots.txt', [SeoController::class, 'robots']);
        $r->get('/feeds/google.xml', [SeoController::class, 'googleFeed']);
        $r->get('/{key}.txt', [SeoController::class, 'indexNowKey']);

        Admin\Routes::register($r);
    }

    private static function navigation(): array
    {
        $cats = array_values(array_filter(Catalog::categoryTree(true), static fn($c) => (int)$c['show_in_menu'] === 1));
        foreach ($cats as &$c) {
            $c['children'] = array_values(array_filter($c['children'], static fn($s) => (int)$s['show_in_menu'] === 1));
        }
        return [
            'categories' => $cats,
            'pages' => DB::all("SELECT slug, title FROM pages WHERE type = 'page' AND is_active = 1 AND show_in_menu = 1 ORDER BY id"),
            'footer_pages' => DB::all("SELECT slug, title FROM pages WHERE type = 'page' AND is_active = 1 AND show_in_footer = 1 ORDER BY id"),
            'blog' => (int)DB::val("SELECT COUNT(*) FROM pages WHERE type = 'post' AND is_active = 1") > 0,
        ];
    }

    private static function cacheKey(Request $req): ?string
    {
        if ($req->method !== 'GET' || isset($_COOKIE['as_admin']) || isset($req->query['preview_theme']) || isset($req->query['reviewed']) || isset($req->query['attr'])) {
            return null;
        }
        foreach (self::CACHEABLE as $re) {
            if (preg_match($re, $req->path)) {
                return Cache::key($req->path, array_intersect_key($req->query, array_flip(['page', 'sort', 'min', 'max'])));
            }
        }
        return null;
    }

    private static function canonicalRedirect(Request $req): ?Response
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $rawPath = (string)parse_url($uri, PHP_URL_PATH);
        if ($req->method === 'GET' && strlen($rawPath) > 1 && str_ends_with($rawPath, '/') && !str_starts_with($req->path, '/admin')) {
            $qs = parse_url($uri, PHP_URL_QUERY);
            $b = parse_url(Config::baseUrl());
            $origin = ($b['scheme'] ?? 'http') . '://' . ($b['host'] ?? 'localhost') . (isset($b['port']) ? ':' . $b['port'] : '');
            return Response::redirect($origin . rtrim($rawPath, '/') . ($qs ? '?' . $qs : ''), 301);
        }
        return null;
    }

    private static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        if (is_https()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        header_remove('X-Powered-By');
    }

    private static function sendCached(array $hit, Request $req): void
    {
        $etag = '"' . md5((string)$hit['mtime'] . strlen($hit['body'])) . '"';
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $hit['mtime']) . ' GMT');
        header('X-Cache: HIT');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return;
        }
        self::compress();
        echo $hit['body'];
    }

    private static function compress(): void
    {
        if (!ini_get('zlib.output_compression') && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') && function_exists('ob_gzhandler')) {
            ob_start('ob_gzhandler');
        }
    }

    private static function send(Response $response): void
    {
        $type = $response->headers['Content-Type'] ?? '';
        if (preg_match('#text/|xml|json#', $type)) {
            self::compress();
        }
        if (str_contains($type, 'text/html') && !isset($response->headers['Cache-Control'])) {
            $response->headers['Cache-Control'] = 'public, max-age=0, must-revalidate';
        }
        $response->send();
    }

    private static function fail(\Throwable $e): void
    {
        @file_put_contents(
            ROOT . '/storage/logs/error.log',
            '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n\n",
            FILE_APPEND
        );
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo Config::debug()
            ? '<pre>' . e($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>'
            : '<!doctype html><meta charset="utf-8"><title>Errore</title><div style="font-family:sans-serif;text-align:center;padding:15vh 20px"><h1>Si è verificato un errore</h1><p>Riprova tra qualche istante.</p></div>';
    }
}
