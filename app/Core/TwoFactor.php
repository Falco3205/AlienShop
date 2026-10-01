<?php
declare(strict_types=1);

namespace Alien\Core;

final class TwoFactor
{
    public static function enabled(int $userId): bool
    {
        return (string)Settings::get('totp_secret_' . $userId, '') !== '';
    }

    public static function secret(int $userId): string
    {
        return Secret::open((string)Settings::get('totp_secret_' . $userId, ''));
    }

    public static function begin(int $userId): string
    {
        $secret = Totp::generateSecret();
        Session::start();
        $_SESSION['totp_setup'] = $secret;
        return $secret;
    }

    public static function pendingSecret(): ?string
    {
        Session::start();
        return $_SESSION['totp_setup'] ?? null;
    }

    public static function confirm(int $userId, string $code): ?array
    {
        $secret = self::pendingSecret();
        if (!$secret || Totp::verify($secret, $code) === null) {
            return null;
        }
        Settings::set('totp_secret_' . $userId, Secret::seal($secret));
        Settings::set('totp_last_' . $userId, (string)intdiv(time(), 30));
        unset($_SESSION['totp_setup']);
        return self::newRecoveryCodes($userId);
    }

    public static function newRecoveryCodes(int $userId): array
    {
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $c = strtoupper(bin2hex(random_bytes(5)));
            $codes[] = substr($c, 0, 5) . '-' . substr($c, 5);
            $hashes[] = hash('sha256', $c);
        }
        Settings::set('totp_recovery_' . $userId, json_encode($hashes));
        return $codes;
    }

    public static function disable(int $userId): void
    {
        foreach (['secret', 'last', 'recovery'] as $k) {
            Settings::set('totp_' . $k . '_' . $userId, '');
        }
    }

    public static function check(int $userId, string $input): bool
    {
        $input = trim($input);
        $secret = self::secret($userId);
        if ($secret === '') {
            return false;
        }
        $step = Totp::verify($secret, $input, (int)Settings::get('totp_last_' . $userId, 0));
        if ($step !== null) {
            Settings::set('totp_last_' . $userId, (string)$step);
            return true;
        }
        $norm = strtoupper(str_replace('-', '', $input));
        if (preg_match('/^[0-9A-F]{10}$/D', $norm)) {
            $hashes = json_decode((string)Settings::get('totp_recovery_' . $userId, '[]'), true) ?: [];
            $h = hash('sha256', $norm);
            foreach ($hashes as $i => $known) {
                if (hash_equals($known, $h)) {
                    unset($hashes[$i]);
                    Settings::set('totp_recovery_' . $userId, json_encode(array_values($hashes)));
                    return true;
                }
            }
        }
        return false;
    }

    public static function startChallenge(array $user): void
    {
        Session::start();
        $_SESSION['2fa_user'] = (int)$user['id'];
        $_SESSION['2fa_until'] = time() + 300;
    }

    public static function challengeUser(): ?array
    {
        Session::start();
        $id = (int)($_SESSION['2fa_user'] ?? 0);
        if (!$id || ($_SESSION['2fa_until'] ?? 0) < time()) {
            unset($_SESSION['2fa_user'], $_SESSION['2fa_until']);
            return null;
        }
        return DB::row('SELECT id, email, name, phone, role FROM users WHERE id = ?', [$id]);
    }

    public static function clearChallenge(): void
    {
        Session::start();
        unset($_SESSION['2fa_user'], $_SESSION['2fa_until']);
    }
}
