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
        if ($id && !self::sessionValid()) {
            self::logout();
            return null;
        }
        return self::$user = $id ? DB::row('SELECT id, email, name, phone, role FROM users WHERE id = ?', [$id]) : null;
    }

    private static function sessionValid(): bool
    {
        $now = time();
        $ua = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!isset($_SESSION['ua'])) {
            $_SESSION['ua'] = $ua;
        }
        $isAdminSession = (bool)($_SESSION['adm'] ?? false);
        $idle = $isAdminSession ? 4 * 3600 : 7 * 86400;
        $max = $isAdminSession ? 12 * 3600 : 30 * 86400;
        if (!hash_equals((string)$_SESSION['ua'], $ua)
            || $now - (int)($_SESSION['seen'] ?? $now) > $idle
            || $now - (int)($_SESSION['born'] ?? $now) > $max) {
            return false;
        }
        $_SESSION['seen'] = $now;
        return true;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['uid'] = (int)$user['id'];
        $_SESSION['adm'] = ($user['role'] ?? '') === 'admin';
        $_SESSION['born'] = $_SESSION['seen'] = time();
        $_SESSION['ua'] = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
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
        $email = mb_strtolower(trim($email));
        $accountKey = 'u:' . substr(hash('sha256', $email), 0, 24);
        DB::delete('login_attempts', 'created_at < ?', [time() - 900]);
        if (RateLimit::count($ip) >= 8 || RateLimit::count($accountKey) >= 10) {
            return null;
        }
        $user = DB::row('SELECT * FROM users WHERE email = ?', [$email]);
        static $dummy = null;
        $hash = $user['password'] ?? ($dummy ??= password_hash('dummy-password', PASSWORD_DEFAULT));
        $ok = password_verify($password, $hash) && $user && ($role === null || $user['role'] === $role);
        if (!$ok) {
            DB::insert('login_attempts', ['ip' => $ip, 'created_at' => time()]);
            DB::insert('login_attempts', ['ip' => $accountKey, 'created_at' => time()]);
            return null;
        }
        RateLimit::clear($ip);
        RateLimit::clear($accountKey);
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
