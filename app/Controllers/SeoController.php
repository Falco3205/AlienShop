<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Cache;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\IndexNow;
use Alien\Services\Sitemap;

final class SeoController extends Controller
{
    private function cached(string $key, callable $build, string $type = 'application/xml'): Response
    {
        $k = Cache::key('/_seo/' . $key, []);
        $hit = Cache::get($k, 3600);
        $body = $hit['body'] ?? null;
        if ($body === null) {
            $body = $build();
            Cache::put($k, $body);
        }
        return Response::text($body, $type)->header('Cache-Control', 'public, max-age=600');
    }

    public function sitemapIndex(): Response
    {
        return $this->cached('index', [Sitemap::class, 'index']);
    }

    public function sitemapPages(): Response
    {
        return $this->cached('pages', [Sitemap::class, 'pages']);
    }

    public function sitemapCategories(): Response
    {
        return $this->cached('categories', [Sitemap::class, 'categories']);
    }

    public function sitemapProducts(Request $req, array $params): Response
    {
        $n = max(1, (int)$params['n']);
        return $this->cached('products-' . $n, static fn() => Sitemap::products($n));
    }

    public function robots(): Response
    {
        return Response::text(Sitemap::robots())->header('Cache-Control', 'public, max-age=3600');
    }

    public function googleFeed(): Response
    {
        return $this->cached('google-feed', [Sitemap::class, 'googleFeed']);
    }

    public function indexNowKey(Request $req, array $params): Response
    {
        return hash_equals(IndexNow::key(), $params['key']) ? Response::text($params['key']) : $this->missing($req);
    }
}
