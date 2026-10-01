<?php
declare(strict_types=1);

use Alien\Core\Config;
use Alien\Core\Csrf;
use Alien\Core\Lang;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Session;
use Alien\Core\Settings;
use Alien\Core\View;

function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    return Config::baseUrl();
}

function url(string $path = ''): string
{
    return rtrim(base_url(), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function upload_url(?string $path, ?int $size = null): string
{
    if (!$path) {
        return asset('placeholder.svg');
    }
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    if ($size && !str_ends_with($path, '.svg')) {
        $info = pathinfo($path);
        $variant = ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '') . $info['filename'] . '-' . $size . '.' . $info['extension'];
        if (is_file(ROOT . '/public/uploads/' . $variant)) {
            return url('uploads/' . $variant);
        }
    }
    return url('uploads/' . $path);
}

function srcset(?string $path): string
{
    if (!$path || preg_match('#^https?://#', $path) || str_ends_with($path, '.svg')) {
        return '';
    }
    $parts = [];
    foreach ([400, 800] as $w) {
        $u = upload_url($path, $w);
        if (!str_ends_with($u, $path)) {
            $parts[] = $u . ' ' . $w . 'w';
        }
    }
    return implode(', ', $parts);
}

function money(int|float|null $cents): string
{
    return Money::format((int)round((float)($cents ?? 0)));
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function __(string $text, mixed ...$args): string
{
    $t = Lang::translate($text);
    return $args ? vsprintf($t, $args) : $t;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, string $message): void
{
    Session::start();
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flash(): array
{
    if (!isset($_COOKIE[Session::name()])) {
        return [];
    }
    Session::start();
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    if ($f) {
        $GLOBALS['as_flash_shown'] = true;
    }
    return $f;
}

function old(string $key, mixed $default = ''): mixed
{
    Session::start();
    return $_SESSION['_old'][$key] ?? $default;
}

function partial(string $name, array $data = []): string
{
    return View::partial($name, $data);
}

function request(): Request
{
    return Request::current();
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function json_flags(): int
{
    return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
}
