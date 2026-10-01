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
        if (!isset($_COOKIE[Session::name()])) {
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

    public static function hit(): void
    {
        DB::insert('login_attempts', ['ip' => request()->ip(), 'created_at' => time()]);
    }

    public static function resetToken(array $user, ?int $expires = null): string
    {
        $expires ??= time() + 3600;
        return $user['id'] . '.' . $expires . '.' . self::resetSignature($user, $expires);
    }

    private static function resetSignature(array $user, int $expires): string
    {
        return hash_hmac('sha256', $user['id'] . '|' . $expires . '|' . substr(hash('sha256', (string)$user['password']), 0, 16), (string)Config::get('app.key', ''));
    }

    public static function userFromResetToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) || (int)$parts[1] < time()) {
            return null;
        }
        $user = DB::row('SELECT * FROM users WHERE id = ?', [(int)$parts[0]]);
        return $user && hash_equals(self::resetSignature($user, (int)$parts[1]), $parts[2]) ? $user : null;
    }

    public static function setPassword(int $userId, string $password): void
    {
        DB::update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$userId]);
    }
}
