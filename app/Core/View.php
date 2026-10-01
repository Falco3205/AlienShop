<?php
declare(strict_types=1);

namespace Alien\Core;

final class View
{
    public static ?string $override = null;
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function theme(): string
    {
        $t = self::$override ?? (string)Settings::get('theme', 'aurora');
        return preg_match('/^[a-z0-9_-]+$/D', $t) && is_dir(ROOT . '/themes/' . $t) ? $t : 'aurora';
    }

    public static function resolve(string $name): string
    {
        foreach ([ROOT . '/themes/' . self::theme() . '/views/', ROOT . '/themes/_base/views/'] as $dir) {
            if (is_file($dir . $name . '.php')) {
                return $dir . $name . '.php';
            }
        }
        throw new \RuntimeException('Template non trovato: ' . $name);
    }

    public static function partial(string $name, array $data = []): string
    {
        return self::include(self::resolve('partials/' . $name), $data);
    }

    public static function render(string $name, array $data = [], string $layout = 'layout'): string
    {
        $content = self::include(self::resolve($name), $data);
        return $layout === '' ? $content : self::include(self::resolve($layout), $data + ['content' => $content]);
    }

    public static function admin(string $name, array $data = [], bool $layout = true): string
    {
        $content = self::include(ROOT . '/views/admin/' . $name . '.php', $data);
        return $layout ? self::include(ROOT . '/views/admin/layout.php', $data + ['content' => $content]) : $content;
    }

    public static function install(string $name, array $data = []): string
    {
        $content = self::include(ROOT . '/views/install/' . $name . '.php', $data);
        return self::include(ROOT . '/views/install/layout.php', $data + ['content' => $content]);
    }

    private static function include(string $__file, array $__data): string
    {
        extract(self::$shared, EXTR_SKIP);
        extract($__data, EXTR_OVERWRITE);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}
