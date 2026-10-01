<?php
declare(strict_types=1);

namespace Alien\Core;

final class Cache
{
    private static function dir(): string
    {
        return ROOT . '/storage/cache/pages';
    }

    public static function key(string $path, array $query): string
    {
        ksort($query);
        return sha1($path . '?' . http_build_query($query));
    }

    public static function get(string $key, int $ttl): ?array
    {
        $file = self::dir() . '/' . $key . '.html';
        if (!is_file($file)) {
            return null;
        }
        $mtime = (int)filemtime($file);
        if ($ttl <= 0 || time() - $mtime > $ttl) {
            return null;
        }
        return ['body' => (string)file_get_contents($file), 'mtime' => $mtime];
    }

    public static function put(string $key, string $body): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $tmp = $dir . '/' . $key . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $body) !== false) {
            @rename($tmp, $dir . '/' . $key . '.html');
        }
    }

    public static function flush(): void
    {
        foreach (glob(self::dir() . '/*.html') ?: [] as $f) {
            @unlink($f);
        }
    }
}
