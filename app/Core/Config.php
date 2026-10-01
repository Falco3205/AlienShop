<?php
declare(strict_types=1);

namespace Alien\Core;

final class Config
{
    private static ?array $data = null;

    public static function file(): string
    {
        return ROOT . '/config/config.php';
    }

    public static function installed(): bool
    {
        return is_file(self::file()) && is_file(ROOT . '/storage/installed.lock');
    }

    public static function all(): array
    {
        if (self::$data === null) {
            self::$data = is_file(self::file()) ? (require self::file()) : [];
        }
        return self::$data;
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $v = self::all();
        foreach (explode('.', $path) as $seg) {
            if (!is_array($v) || !array_key_exists($seg, $v)) {
                return $default;
            }
            $v = $v[$seg];
        }
        return $v;
    }

    public static function write(array $data): void
    {
        $php = "<?php\nreturn " . var_export($data, true) . ";\n";
        file_put_contents(self::file(), $php, LOCK_EX);
        @chmod(self::file(), 0640);
        self::$data = $data;
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::file(), true);
        }
    }

    public static function baseUrl(): string
    {
        $configured = self::get('app.url');
        if ($configured) {
            return rtrim((string)$configured, '/');
        }
        return Request::detectBaseUrl();
    }

    public static function debug(): bool
    {
        return (bool)self::get('app.debug', false);
    }
}
