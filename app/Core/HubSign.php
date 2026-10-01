<?php
declare(strict_types=1);

namespace Alien\Core;

final class HubSign
{
    public static function signature(string $secret, string $method, string $path, string $body, int $ts): string
    {
        return hash_hmac('sha256', $ts . "\n" . strtoupper($method) . "\n" . $path . "\n" . $body, $secret);
    }

    public static function headers(string $secret, string $method, string $path, string $body = ''): array
    {
        $ts = time();
        return ['X-Alien-Time: ' . $ts, 'X-Alien-Signature: ' . self::signature($secret, $method, $path, $body, $ts)];
    }

    public static function verify(string $secret, string $method, string $path, string $body, string $time, string $signature, int $tolerance = 300): bool
    {
        if ($secret === '' || !ctype_digit($time) || abs(time() - (int)$time) > $tolerance) {
            return false;
        }
        return hash_equals(self::signature($secret, $method, $path, $body, (int)$time), $signature);
    }
}
