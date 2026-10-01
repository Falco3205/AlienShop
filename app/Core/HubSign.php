<?php
declare(strict_types=1);

namespace Alien\Core;

final class HubSign
{
    public static function signature(string $secret, string $method, string $path, string $body, int $ts, string $nonce): string
    {
        return hash_hmac('sha256', $ts . "\n" . $nonce . "\n" . strtoupper($method) . "\n" . $path . "\n" . $body, $secret);
    }

    public static function headers(string $secret, string $method, string $path, string $body = ''): array
    {
        $ts = time();
        $nonce = bin2hex(random_bytes(12));
        return ['X-Alien-Time: ' . $ts, 'X-Alien-Nonce: ' . $nonce, 'X-Alien-Signature: ' . self::signature($secret, $method, $path, $body, $ts, $nonce)];
    }

    public static function verify(string $secret, string $method, string $path, string $body, string $time, string $signature, string $nonce, int $tolerance = 300): bool
    {
        if ($secret === '' || !ctype_digit($time) || !preg_match('/^[0-9a-f]{16,64}\z/', $nonce) || abs(time() - (int)$time) > $tolerance) {
            return false;
        }
        return hash_equals(self::signature($secret, $method, $path, $body, (int)$time, $nonce), $signature);
    }
}
