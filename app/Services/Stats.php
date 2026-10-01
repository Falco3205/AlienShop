<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;

final class Stats
{
    private const FIELDS = ['views', 'carts', 'checkouts'];

    public static function bump(string $field, int $productId, int $n = 1): void
    {
        if (!in_array($field, self::FIELDS, true) || $productId <= 0 || self::isBot()) {
            return;
        }
        $day = date('Y-m-d');
        try {
            if (DB::exec("UPDATE product_stats SET $field = $field + ? WHERE day = ? AND product_id = ?", [$n, $day, $productId]) === 0) {
                DB::insert('product_stats', ['day' => $day, 'product_id' => $productId, $field => $n]);
            }
        } catch (\Throwable) {
            DB::exec("UPDATE product_stats SET $field = $field + ? WHERE day = ? AND product_id = ?", [$n, $day, $productId]);
        }
    }

    public static function isBot(): bool
    {
        return isset($_COOKIE['as_admin']) || (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|headless|monitor|curl|wget/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    public static function search(string $term, int $results): void
    {
        $term = mb_strtolower(trim(mb_substr($term, 0, 100)));
        if ($term === '' || self::isBot()) {
            return;
        }
        $zero = $results === 0 ? 1 : 0;
        try {
            if (DB::exec('UPDATE search_terms SET hits = hits + 1, zero = zero + ?, last_at = ? WHERE term = ?', [$zero, now(), $term]) === 0) {
                DB::insert('search_terms', ['term' => $term, 'hits' => 1, 'zero' => $zero, 'last_at' => now()]);
            }
        } catch (\Throwable) {
        }
    }

    public static function report(int $days): array
    {
        $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $sinceTime = $since . ' 00:00:00';
        $rows = [];
        foreach (DB::all('SELECT p.id, p.name, p.image, p.slug, SUM(s.views) AS views, SUM(s.carts) AS carts, SUM(s.checkouts) AS checkouts
            FROM product_stats s JOIN products p ON p.id = s.product_id WHERE s.day >= ? GROUP BY p.id, p.name, p.image, p.slug', [$since]) as $r) {
            $rows[(int)$r['id']] = $r + ['sold' => 0, 'revenue' => 0];
        }
        foreach (DB::all("SELECT oi.product_id, MAX(oi.name) AS name, SUM(oi.qty) AS qty, SUM(oi.total) AS revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE o.created_at >= ? AND o.status NOT IN ('cancelled','refunded') GROUP BY oi.product_id", [$sinceTime]) as $r) {
            $id = (int)$r['product_id'];
            if (!isset($rows[$id])) {
                $p = DB::row('SELECT id, name, image, slug FROM products WHERE id = ?', [$id]);
                $rows[$id] = ($p ?: ['id' => $id, 'name' => $r['name'], 'image' => null, 'slug' => '']) + ['views' => 0, 'carts' => 0, 'checkouts' => 0];
            }
            $rows[$id]['sold'] = (int)$r['qty'];
            $rows[$id]['revenue'] = (int)$r['revenue'];
        }
        foreach ($rows as &$r) {
            $r['views'] = (int)$r['views'];
            $r['carts'] = (int)$r['carts'];
            $r['checkouts'] = (int)$r['checkouts'];
            $r['abandoned'] = max(0, $r['carts'] - $r['sold']);
            $r['conversion'] = $r['views'] > 0 ? round($r['sold'] / $r['views'] * 100, 1) : 0.0;
        }
        unset($r);
        $list = array_values($rows);
        $top = static function (string $key, int $n = 10, ?callable $filter = null) use ($list) {
            $items = array_values(array_filter($list, $filter ?? static fn($r) => $r[$key] > 0));
            usort($items, static fn($a, $b) => $b[$key] <=> $a[$key]);
            return array_slice($items, 0, $n);
        };
        $orders = (int)DB::val("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled','refunded')", [$sinceTime]);
        $views = array_sum(array_column($list, 'views'));
        $carts = array_sum(array_column($list, 'carts'));
        $checkouts = array_sum(array_column($list, 'checkouts'));
        return [
            'funnel' => ['views' => $views, 'carts' => $carts, 'checkouts' => $checkouts, 'orders' => $orders],
            'viewed' => $top('views'),
            'sold' => $top('sold'),
            'carted' => $top('carts'),
            'abandoned' => $top('abandoned'),
            'neverBought' => $top('views', 10, static fn($r) => $r['views'] >= 5 && $r['sold'] === 0),
            'searches' => DB::all('SELECT term, hits, zero FROM search_terms ORDER BY hits DESC LIMIT 10'),
            'noResults' => DB::all('SELECT term, zero FROM search_terms WHERE zero > 0 ORDER BY zero DESC LIMIT 10'),
            'tracking' => (int)DB::val('SELECT COUNT(*) FROM product_stats') > 0,
        ];
    }
}
