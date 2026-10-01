<?php
declare(strict_types=1);

namespace Alien\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(24));
    }

    public static function valid(Request $req): bool
    {
        Session::start();
        $sent = (string)($req->post['_csrf'] ?? $req->header('X-CSRF-Token'));
        return $sent !== '' && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $sent);
    }
}
