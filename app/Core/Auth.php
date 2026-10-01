<?php
declare(strict_types=1);

namespace Alien\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (!isset($_COOKIE[Session::NAME])) {
            return null;
        }
        Session::start();
        $id = (int)($_SESSION['uid'] ?? 0);
        return self::$user = $id ? DB::row('SELECT id, email, name, phone, role FROM users WHERE id = ?', [$id]) : null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['uid'] = (int)$user['id'];
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    public static function attempt(string $email, string $password, ?string $role = null): ?array
    {
        $ip = request()->ip();
        DB::delete('login_attempts', 'created_at < ?', [time() - 900]);
        if ((int)DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip]) >= 8) {
            return null;
        }
        $user = DB::row('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        $ok = $user && password_verify($password, $user['password']) && ($role === null || $user['role'] === $role);
        if (!$ok) {
            DB::insert('login_attempts', ['ip' => $ip, 'created_at' => time()]);
            return null;
        }
        DB::delete('login_attempts', 'ip = ?', [$ip]);
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }
        return $user;
    }

    public static function tooManyAttempts(): bool
    {
        return (int)DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at >= ?', [request()->ip(), time() - 900]) >= 8;
    }

    public static function create(string $email, string $password, string $name, string $role = 'customer', string $phone = ''): int
    {
        return DB::insert('users', [
            'email' => mb_strtolower(trim($email)),
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name,
            'phone' => $phone,
            'role' => $role,
            'created_at' => now(),
        ]);
    }
}
