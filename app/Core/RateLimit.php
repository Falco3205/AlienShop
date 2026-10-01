<?php
declare(strict_types=1);

namespace Alien\Core;

final class RateLimit
{
    public static function hit(string $bucket, int $max, int $window = 900): bool
    {
        $key = substr($bucket . ':' . request()->ip(), 0, 64);
        $now = time();
        DB::delete('login_attempts', 'created_at < ?', [$now - 86400]);
        $count = (int)DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at >= ?', [$key, $now - $window]);
        if ($count >= $max) {
            return false;
        }
        DB::insert('login_attempts', ['ip' => $key, 'created_at' => $now]);
        return true;
    }
}
