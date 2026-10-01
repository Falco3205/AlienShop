<?php
declare(strict_types=1);

namespace Alien\Core;

final class RateLimit
{
    public static function hit(string $bucket, int $max, int $window = 900): bool
    {
        return self::hitKey($bucket . ':' . request()->ip(), $max, $window);
    }

    public static function hitKey(string $key, int $max, int $window = 900): bool
    {
        $key = substr($key, 0, 64);
        $now = time();
        DB::delete('login_attempts', 'created_at < ?', [$now - 86400]);
        if (self::count($key, $window) >= $max) {
            return false;
        }
        DB::insert('login_attempts', ['ip' => $key, 'created_at' => $now]);
        return true;
    }

    public static function count(string $key, int $window = 900): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at >= ?', [substr($key, 0, 64), time() - $window]);
    }

    public static function blocked(string $bucket, int $max, int $window = 900): bool
    {
        return self::count($bucket . ':' . request()->ip(), $window) >= $max;
    }

    public static function clear(string $key): void
    {
        DB::delete('login_attempts', 'ip = ?', [substr($key, 0, 64)]);
    }
}
