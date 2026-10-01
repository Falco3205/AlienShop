<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Money;
use Alien\Core\Settings;
use Alien\Core\Str;

final class Seo
{
    private static array $meta = [
        'title' => '',
        'description' => '',
        'canonical' => '',
        'image' => '',
        'type' => 'website',
        'robots' => 'index,follow',
        'prev' => '',
        'next' => '',
        'schemas' => [],
    ];

    public static function set(array $values): void
    {
        self::$meta = array_merge(self::$meta, array_filter($values, static fn($v) => $v !== null));
    }

    public static function noindex(): void
    {
        self::$meta['robots'] = 'noindex,nofollow';
    }

    public static function schema(array $schema): void
    {
        self::$meta['schemas'][] = $schema;
    }

    public static function get(string $key): mixed
    {
        return self::$meta[$key] ?? null;
    }

    public static function fullTitle(): string
    {
        $store = (string)Settings::get('store_name', 'Shop');
        $title = trim((string)self::$meta['title']);
        if ($title === '') {
            $tag = (string)Settings::get('store_tagline', '');
            return $tag !== '' ? $store . ' — ' . $tag : $store;
        }
        return mb_strtolower($title) === mb_strtolower($store) ? $title : $title . ' — ' . $store;
    }

    public static function head(): string
    {
        $m = self::$meta;
        $store = (string)Settings::get('store_name', 'Shop');
        $desc = Str::excerpt($m['description'] !== '' ? $m['description'] : (string)Settings::get('store_description', ''), 160);
        $canonical = $m['canonical'] !== '' ? $m['canonical'] : url(ltrim(request()->path, '/'));
        $image = $m['image'] !== '' ? $m['image'] : (Settings::get('logo') ? upload_url((string)Settings::get('logo')) : '');
        $title = self::fullTitle();
        $robots = (bool)Settings::get('discourage_indexing', false) ? 'noindex,nofollow' : $m['robots'];

        $h = '<title>' . e($title) . "</title>\n";
        if ($desc !== '') {
            $h .= '<meta name="description" content="' . e($desc) . "\">\n";
        }
        $h .= '<meta name="robots" content="' . e($robots . ($robots === 'index,follow' ? ',max-image-preview:large,max-snippet:-1' : '')) . "\">\n";
        $h .= '<link rel="canonical" href="' . e($canonical) . "\">\n";
        if ($m['prev'] !== '') {
            $h .= '<link rel="prev" href="' . e($m['prev']) . "\">\n";
        }
        if ($m['next'] !== '') {
            $h .= '<link rel="next" href="' . e($m['next']) . "\">\n";
        }
        $h .= '<meta property="og:site_name" content="' . e($store) . "\">\n";
        $h .= '<meta property="og:type" content="' . e($m['type']) . "\">\n";
        $h .= '<meta property="og:title" content="' . e(self::$meta['title'] !== '' ? $m['title'] : $title) . "\">\n";
        $h .= '<meta property="og:description" content="' . e($desc) . "\">\n";
        $h .= '<meta property="og:url" content="' . e($canonical) . "\">\n";
        $h .= '<meta property="og:locale" content="' . e(Settings::get('locale', 'it') === 'it' ? 'it_IT' : 'en_US') . "\">\n";
        if ($image !== '') {
            $h .= '<meta property="og:image" content="' . e($image) . "\">\n";
        }
        $h .= '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . "\">\n";
        foreach (['google_verification' => 'google-site-verification', 'bing_verification' => 'msvalidate.01', 'pinterest_verification' => 'p:domain_verify'] as $key => $name) {
            if ($v = Settings::get($key)) {
                $h .= '<meta name="' . $name . '" content="' . e($v) . "\">\n";
            }
        }
        $schemas = array_merge(self::baseSchemas(), $m['schemas']);
        foreach ($schemas as $s) {
            $h .= '<script type="application/ld+json">' . json_encode($s, json_flags()) . "</script>\n";
        }
        return $h;
    }

    private static function baseSchemas(): array
    {
        if (request()->path !== '/') {
            return [];
        }
        $out = [[
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string)Settings::get('store_name', 'Shop'),
            'url' => url(),
            'logo' => Settings::get('logo') ? upload_url((string)Settings::get('logo')) : null,
            'email' => Settings::get('store_email') ?: null,
            'sameAs' => array_values(array_filter(array_map('trim', explode(',', (string)Settings::get('social_links', ''))))) ?: null,
        ], [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => (string)Settings::get('store_name', 'Shop'),
            'url' => url(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('search?q={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ]];
        return array_map(static fn($s) => array_filter($s, static fn($v) => $v !== null), $out);
    }

    public static function forProduct(array $p): void
    {
        $url = url(Catalog::productUrl($p));
        $images = array_map(static fn($i) => upload_url($i['path']), $p['images']);
        $desc = $p['seo_description'] !== '' ? $p['seo_description'] : Str::excerpt($p['short_description'] ?: $p['description'], 160);
        self::set([
            'title' => $p['seo_title'] !== '' ? $p['seo_title'] : $p['name'],
            'description' => $desc,
            'canonical' => $url,
            'image' => $images[0] ?? '',
            'type' => 'product',
            'robots' => $p['noindex'] ? 'noindex,follow' : 'index,follow',
        ]);
        $currency = Money::currency();
        $availability = 'https://schema.org/' . ($p['in_stock'] ? 'InStock' : 'OutOfStock');
        $fmt = static fn(int $c) => number_format($c / Money::factor(), Money::decimals(), '.', '');
        $offer = ((int)$p['price_min'] !== (int)$p['price_max'])
            ? ['@type' => 'AggregateOffer', 'lowPrice' => $fmt((int)$p['price_min']), 'highPrice' => $fmt((int)$p['price_max']), 'priceCurrency' => $currency, 'offerCount' => max(1, count($p['variants'])), 'availability' => $availability, 'url' => $url]
            : ['@type' => 'Offer', 'price' => $fmt((int)$p['price_min']), 'priceCurrency' => $currency, 'availability' => $availability, 'url' => $url, 'itemCondition' => 'https://schema.org/NewCondition'];
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p['name'],
            'description' => Str::excerpt($p['description'] ?: $p['short_description'], 500),
            'sku' => $p['sku'] !== '' ? $p['sku'] : (string)$p['id'],
            'image' => $images ?: null,
            'brand' => $p['vendor'] !== '' ? ['@type' => 'Brand', 'name' => $p['vendor']] : null,
            'offers' => $offer,
        ];
        self::schema(array_filter($schema, static fn($v) => $v !== null && $v !== ''));
        self::breadcrumbs(self::productTrail($p));
    }

    public static function productTrail(array $p): array
    {
        $trail = [[__('Home'), url()]];
        if (!empty($p['categories'])) {
            foreach (Catalog::categoryPath($p['categories'][0]) as $c) {
                $trail[] = [$c['name'], url(Catalog::categoryUrl($c))];
            }
        }
        $trail[] = [$p['name'], url(Catalog::productUrl($p))];
        return $trail;
    }

    public static function breadcrumbs(array $trail): void
    {
        $items = [];
        foreach ($trail as $i => [$name, $link]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $link];
        }
        self::schema(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
    }
}
