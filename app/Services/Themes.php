<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Settings;

final class Themes
{
    public static function all(): array
    {
        $themes = [];
        foreach (glob(ROOT . '/themes/*/theme.json') ?: [] as $file) {
            $slug = basename(dirname($file));
            if ($slug === '_base') {
                continue;
            }
            $meta = json_decode((string)file_get_contents($file), true);
            if (is_array($meta)) {
                $themes[$slug] = $meta + ['slug' => $slug];
            }
        }
        ksort($themes);
        return $themes;
    }

    public static function get(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function activate(string $slug): bool
    {
        if (!self::get($slug)) {
            return false;
        }
        Settings::set('theme', $slug);
        self::build($slug);
        return true;
    }

    public static function layoutClasses(string $slug): string
    {
        $l = (self::get($slug)['layout'] ?? []) + ['header' => 'left', 'grid' => 4, 'card' => 'border', 'hero' => 'split'];
        return 'h-' . $l['header'] . ' g-' . (int)$l['grid'] . ' c-' . $l['card'] . ' hero-' . $l['hero'];
    }

    public static function overrideCss(): string
    {
        $css = '';
        $vars = [
            'theme_primary' => '--primary',
            'theme_accent' => '--accent',
            'theme_bg' => '--bg',
            'theme_text' => '--text',
        ];
        $rules = [];
        foreach ($vars as $setting => $var) {
            $v = (string)Settings::get($setting, '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/D', $v)) {
                $rules[] = $var . ':' . $v;
                if ($var === '--primary') {
                    $rules[] = '--primary-contrast:' . self::contrast($v);
                }
            }
        }
        if ($rules) {
            $css .= ':root{' . implode(';', $rules) . '}';
        }
        $custom = (string)Settings::get('custom_css', '');
        if ($custom !== '') {
            $custom = preg_replace('#@import[^;]*;?#i', '', $custom) ?? '';
            $css .= str_ireplace('</style', '', $custom);
        }
        return $css;
    }

    public static function contrast(string $hex): string
    {
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111111' : '#ffffff';
    }

    public static function minify(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        $css = preg_replace('/\s*([{};,>])\s*/', '$1', $css) ?? $css;
        $css = preg_replace('/;}/', '}', $css) ?? $css;
        return trim($css);
    }

    public static function build(string $slug, bool $preview = false): array
    {
        $css = (string)file_get_contents(ROOT . '/themes/_base/assets/base.css');
        $themeCss = ROOT . '/themes/' . $slug . '/style.css';
        $css = $css . "\n" . (is_file($themeCss) ? file_get_contents($themeCss) : '');
        $css = self::minify($css . ($preview ? '' : self::overrideCss()));
        $js = (string)file_get_contents(ROOT . '/themes/_base/assets/app.js');
        $js = trim((string)preg_replace(['#^\s*//.*$#m', '/\n\s*\n/'], ['', "\n"], $js));

        $dir = ROOT . '/public/assets';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $cssName = 'theme-' . ($preview ? 'preview-' . $slug : substr(md5($css), 0, 10)) . '.css';
        $jsName = 'app-' . substr(md5($js), 0, 10) . '.js';
        if (!$preview) {
            foreach (glob($dir . '/theme-*.css') ?: [] as $old) {
                if (!str_contains($old, 'preview') && basename($old) !== $cssName) {
                    @unlink($old);
                }
            }
        }
        file_put_contents($dir . '/' . $cssName, $css);
        if (!is_file($dir . '/' . $jsName)) {
            foreach (glob($dir . '/app-*.js') ?: [] as $old) {
                @unlink($old);
            }
            file_put_contents($dir . '/' . $jsName, $js);
        }
        if (!$preview) {
            Settings::set('asset_css', $cssName);
            Settings::set('asset_js', $jsName);
        }
        return ['css' => $cssName, 'js' => $jsName];
    }

    public static function assets(?string $previewSlug = null): array
    {
        if ($previewSlug && self::get($previewSlug)) {
            return self::build($previewSlug, true);
        }
        $css = (string)Settings::get('asset_css', '');
        $js = (string)Settings::get('asset_js', '');
        if ($css === '' || !is_file(ROOT . '/public/assets/' . $css) || !is_file(ROOT . '/public/assets/' . $js)) {
            return self::build(\Alien\Core\View::theme());
        }
        return ['css' => $css, 'js' => $js];
    }
}
