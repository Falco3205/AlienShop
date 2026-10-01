<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Config;
use Alien\Core\DB;
use Alien\Core\Secret;
use Alien\Core\Settings;

final class HubAgent
{
    public static function secret(): string
    {
        return Secret::open((string)Settings::get('hub_secret', ''));
    }

    public static function configured(): bool
    {
        return self::secret() !== '';
    }

    public static function connect(string $url, string $secret): void
    {
        Settings::set('hub_url', rtrim($url, '/'));
        Settings::set('hub_secret', Secret::seal($secret));
    }

    public static function metrics(): array
    {
        $stats = Orders::stats(30);
        $today = date('Y-m-d 00:00:00');
        $d7 = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $live = "status NOT IN ('cancelled','refunded')";
        $sum = static fn(string $since): array => DB::row("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM orders WHERE created_at >= ? AND $live", [$since]) ?: ['n' => 0, 't' => 0];
        $tod = $sum($today);
        $wk = $sum($d7);
        $update = json_decode((string)Settings::get('update_latest', ''), true);
        $cur = Updater::currentCommit();
        $log = ROOT . '/storage/logs/error.log';
        return [
            'version' => ALIEN_VERSION,
            'commit' => $cur,
            'update_available' => !empty($update['sha']) && $cur !== '' && $cur !== $update['sha'],
            'store_name' => (string)Settings::get('store_name', ''),
            'url' => Config::baseUrl(),
            'currency' => (string)Settings::get('currency', 'EUR'),
            'installed_at' => (string)Settings::get('installed_at', ''),
            'orders_today' => (int)$tod['n'],
            'revenue_today' => (int)$tod['t'],
            'orders_7d' => (int)$wk['n'],
            'revenue_7d' => (int)$wk['t'],
            'orders_30d' => $stats['orders'],
            'revenue_30d' => $stats['revenue'],
            'orders_prev_30d' => $stats['prev_orders'],
            'revenue_prev_30d' => $stats['prev_revenue'],
            'daily' => $stats['daily'],
            'top' => $stats['top'],
            'to_ship' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status = 'processing'"),
            'awaiting_payment' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND payment_status = 'unpaid'"),
            'products' => (int)DB::val("SELECT COUNT(*) FROM products WHERE status = 'active'"),
            'out_of_stock' => (int)DB::val("SELECT COUNT(*) FROM products WHERE status = 'active' AND in_stock = 0"),
            'low_stock' => (int)DB::val("SELECT COUNT(*) FROM products WHERE status = 'active' AND manage_stock = 1 AND in_stock = 1 AND stock_qty <= 3 AND type = 'simple'"),
            'customers' => (int)DB::val("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
            'abandoned_carts' => (int)DB::val('SELECT COUNT(*) FROM abandoned_carts WHERE recovered = 0'),
            'payments_on' => array_values(array_filter(['stripe', 'paypal', 'mollie'], static fn($g) => (string)Settings::get('pay_' . $g . '_enabled', '0') === '1')),
            'cron_last' => (string)Settings::get('cron_last', ''),
            'errors_24h' => is_file($log) && filemtime($log) > time() - 86400 ? count(file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : 0,
            'php' => PHP_VERSION,
            'db' => DB::driver(),
        ];
    }

    public static function createSso(): string
    {
        $token = bin2hex(random_bytes(24));
        Settings::set('hub_sso', json_encode(['hash' => hash('sha256', $token), 'exp' => time() + 90]));
        return $token;
    }

    public static function consumeSso(string $token): bool
    {
        $s = json_decode((string)Settings::get('hub_sso', ''), true);
        Settings::set('hub_sso', '');
        return $token !== '' && is_array($s) && $s['exp'] >= time() && hash_equals((string)$s['hash'], hash('sha256', $token));
    }
}
