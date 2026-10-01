<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\DB;
use Alien\Core\Settings;

final class Poller
{
    private const INTERVAL = 300;

    public static function pollShop(?array $shop): bool
    {
        if (!$shop || in_array($shop['status'], ['pending', 'installing', 'suspended'], true)) {
            return false;
        }
        $res = ShopClient::call($shop, 'GET', '/hub/stats');
        if (!$res['ok']) {
            DB::update('shops', ['last_error' => mb_substr((string)$res['error'], 0, 500), 'last_seen' => now()], 'id = ?', [$shop['id']]);
            return false;
        }
        $m = $res['metrics'] ?? [];
        DB::update('shops', [
            'metrics' => json_encode($m, JSON_UNESCAPED_UNICODE), 'version' => substr((string)($m['commit'] ?? ''), 0, 7) ?: (string)($m['version'] ?? ''),
            'last_seen' => now(), 'last_ok' => now(), 'last_error' => '', 'status' => $shop['status'] === 'error' ? 'active' : $shop['status'],
        ], 'id = ?', [$shop['id']]);
        return true;
    }

    public static function pollAll(): array
    {
        @set_time_limit(0);
        $total = $ok = 0;
        foreach (Shops::all() as $shop) {
            if (in_array($shop['status'], ['pending', 'installing', 'suspended'], true)) {
                continue;
            }
            $total++;
            self::pollShop($shop) ? $ok++ : null;
        }
        Settings::set('last_poll', time());
        return ['total' => $total, 'ok' => $ok, 'failed' => $total - $ok];
    }

    public static function maybe(): void
    {
        if (time() - (int)Settings::get('last_poll', 0) < self::INTERVAL) {
            return;
        }
        Settings::set('last_poll', time());
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        try {
            self::pollAll();
        } catch (\Throwable) {
        }
    }
}
