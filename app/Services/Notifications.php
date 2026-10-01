<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Config;

final class Notifications
{
    public static function welcome(string $email, string $name): void
    {
        EmailTemplates::send('welcome', $email, [
            'customer_name' => $name !== '' ? $name : $email,
            'customer_email' => $email,
            'account_link' => Config::baseUrl() . '/account',
        ]);
    }

    public static function passwordReset(array $user, string $link): void
    {
        EmailTemplates::send('password_reset', (string)$user['email'], [
            'customer_name' => (string)(($user['name'] ?? '') !== '' ? $user['name'] : $user['email']),
            'reset_link' => $link,
        ]);
    }
}
