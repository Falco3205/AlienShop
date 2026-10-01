<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Core\Settings;

final class Sitemap
{
    private const CHUNK = 2000;

    private static function xml(string $inner): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $inner;
    }

    private static function lastmod(?string $date): string
    {
        return $date ? '<lastmod>' . date('c', strtotime($date)) . '</lastmod>' : '';
    }

    public static function index(): string
    {
        $out = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $out .= '<sitemap><loc>' . e(url('sitemap-pages.xml')) . '</loc></sitemap>';
        $out .= '<sitemap><loc>' . e(url('sitemap-categories.xml')) . '</loc></sitemap>';
        $chunks = max(1, (int)ceil((int)DB::val("SELECT COUNT(*) FROM products WHERE status = 'active' AND noindex = 0") / self::CHUNK));
        for ($i = 1; $i <= $chunks; $i++) {
            $out .= '<sitemap><loc>' . e(url('sitemap-products-' . $i . '.xml')) . '</loc></sitemap>';
        }
        return self::xml($out . '</sitemapindex>');
    }

    public static function pages(): string
    {
        $out = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $out .= '<url><loc>' . e(url()) . '</loc><changefreq>daily</changefreq><priority>1.0</priority></url>';
        $out .= '<url><loc>' . e(url('collections/all')) . '</loc><changefreq>daily</changefreq><priority>0.8</priority></url>';
        $hasBlog = (int)DB::val("SELECT COUNT(*) FROM pages WHERE type = 'post' AND is_active = 1") > 0;
        if ($hasBlog) {
            $out .= '<url><loc>' . e(url('blog')) . '</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>';
        }
        foreach (DB::all('SELECT type, slug, updated_at FROM pages WHERE is_active = 1 ORDER BY id') as $p) {
            $out .= '<url><loc>' . e(url(($p['type'] === 'post' ? 'blog/' : 'pages/') . $p['slug'])) . '</loc>' . self::lastmod($p['updated_at']) . '<priority>0.5</priority></url>';
        }
        return self::xml($out . '</urlset>');
    }

    public static function categories(): string
    {
        $out = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (DB::all('SELECT slug FROM categories WHERE is_active = 1 ORDER BY id') as $c) {
            $out .= '<url><loc>' . e(url('collections/' . $c['slug'])) . '</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>';
        }
        return self::xml($out . '</urlset>');
    }

    public static function products(int $chunk): string
    {
        $offset = (max(1, $chunk) - 1) * self::CHUNK;
        $rows = DB::all("SELECT id, slug, name, updated_at FROM products WHERE status = 'active' AND noindex = 0 ORDER BY id LIMIT " . self::CHUNK . " OFFSET $offset");
        $images = [];
        if ($rows) {
            $ids = array_column($rows, 'id');
            foreach (DB::all('SELECT product_id, path, alt FROM product_images WHERE product_id IN (' . DB::marks($ids) . ') ORDER BY position', $ids) as $i) {
                $images[$i['product_id']][] = $i;
            }
        }
        $out = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
        foreach ($rows as $p) {
            $out .= '<url><loc>' . e(url('products/' . $p['slug'])) . '</loc>' . self::lastmod($p['updated_at']) . '<changefreq>weekly</changefreq><priority>0.9</priority>';
            foreach (array_slice($images[$p['id']] ?? [], 0, 10) as $i) {
                $out .= '<image:image><image:loc>' . e(upload_url($i['path'])) . '</image:loc><image:title>' . e($i['alt'] ?: $p['name']) . '</image:title></image:image>';
            }
            $out .= '</url>';
        }
        return self::xml($out . '</urlset>');
    }

    public static function robots(): string
    {
        if (Settings::get('discourage_indexing')) {
            return "User-agent: *\nDisallow: /\n";
        }
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /account',
            'Disallow: /search',
            'Disallow: /pay/',
            'Disallow: /*?sort=',
            'Disallow: /*?min=',
            'Disallow: /*?max=',
            'Allow: /uploads/',
            'Allow: /assets/',
            '',
            'Sitemap: ' . url('sitemap.xml'),
        ];
        return implode("\n", $lines) . "\n" . (string)Settings::get('robots_extra', '');
    }

    public static function googleFeed(): string
    {
        $store = e(Settings::get('store_name', 'Shop'));
        $cur = Money::currency();
        $fmt = static fn(int $c) => number_format($c / Money::factor(), Money::decimals(), '.', '') . ' ' . $cur;
        $out = '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel><title>' . $store . '</title><link>' . e(url()) . '</link><description>' . $store . '</description>';
        foreach (DB::all("SELECT * FROM products WHERE status = 'active' ORDER BY id") as $row) {
            $p = Catalog::hydrate($row);
            $entries = $p['type'] === 'variable' && $p['variants'] ? $p['variants'] : [null];
            foreach ($entries as $v) {
                $price = $v ? (int)$v['final_price'] : (int)$p['price'];
                $id = $v ? ($v['sku'] !== '' ? $v['sku'] : $p['id'] . '-' . $v['id']) : ($p['sku'] !== '' ? $p['sku'] : (string)$p['id']);
                $inStock = Catalog::inStock($p, $v);
                $title = $p['name'] . ($v ? ' - ' . implode(' / ', $v['options']) : '');
                $out .= '<item><g:id>' . e($id) . '</g:id><title>' . e(mb_substr($title, 0, 150)) . '</title>'
                    . '<description>' . e(mb_substr(trim(strip_tags($p['description'] ?: $p['short_description'] ?: $p['name'])), 0, 4900)) . '</description>'
                    . '<link>' . e(url(Catalog::productUrl($p))) . '</link>'
                    . ($p['image'] ? '<g:image_link>' . e(upload_url($p['image'])) . '</g:image_link>' : '')
                    . '<g:availability>' . ($inStock ? 'in_stock' : 'out_of_stock') . '</g:availability>'
                    . '<g:price>' . e($fmt($price)) . '</g:price><g:condition>new</g:condition>'
                    . ($p['vendor'] !== '' ? '<g:brand>' . e($p['vendor']) . '</g:brand>' : '')
                    . ($v ? '<g:item_group_id>' . (int)$p['id'] . '</g:item_group_id>' : '')
                    . '</item>';
            }
        }
        return self::xml($out . '</channel></rss>');
    }
}
