<?php
declare(strict_types=1);

namespace Alien\Core;

final class Session
{
    public static function name(): string
    {
        $path = self::path();
        return 'alien_sid' . ($path === '/' ? '' : '_' . substr(md5($path), 0, 6));
    }

    public static function path(): string
    {
        $p = rtrim((string)parse_url(Config::baseUrl(), PHP_URL_PATH), '/');
        return $p === '' ? '/' : $p;
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = ROOT . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_name(self::name());
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => self::path(),
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '86400');
        session_start();
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }
}
