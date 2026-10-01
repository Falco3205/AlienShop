<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Http;
use Alien\Core\Settings;
use Alien\Core\Str;

final class IndexNow
{
    public static function key(): string
    {
        $key = (string)Settings::get('indexnow_key', '');
        if ($key === '') {
            $key = Str::randomToken(16);
            Settings::set('indexnow_key', $key);
        }
        return $key;
    }

    public static function ping(array $paths): void
    {
        if (!Settings::get('indexnow_enabled', true) || Settings::get('discourage_indexing') || !$paths) {
            return;
        }
        $host = (string)parse_url(url(), PHP_URL_HOST);
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.local')) {
            return;
        }
        $key = self::key();
        try {
            Http::request('POST', 'https://api.indexnow.org/indexnow', json_encode([
                'host' => $host,
                'key' => $key,
                'keyLocation' => url($key . '.txt'),
                'urlList' => array_map(static fn($p) => url($p), array_slice($paths, 0, 100)),
            ]), ['Content-Type: application/json; charset=utf-8'], 3);
        } catch (\Throwable) {
        }
    }
}
