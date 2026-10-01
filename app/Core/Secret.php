<?php
declare(strict_types=1);

namespace Alien\Core;

final class Secret
{
    private static function key(): string
    {
        return sodium_crypto_generichash('alienshop-secret|' . (string)Config::get('app.key', ''), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public static function seal(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'enc:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function open(string $stored): string
    {
        if (!str_starts_with($stored, 'enc:')) {
            return $stored;
        }
        $raw = base64_decode(substr($stored, 4), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
        return $plain === false ? '' : $plain;
    }
}
