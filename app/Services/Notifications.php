<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Config;
use Alien\Core\Mailer;
use Alien\Core\Settings;

final class Notifications
{
    public static function welcome(string $email, string $name): void
    {
        $store = (string)Settings::get('store_name', 'Shop');
        $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#222"><h2>' . e($store) . '</h2>'
            . '<p>' . e(__('Ciao %s,', $name !== '' ? $name : $email)) . '</p>'
            . '<p>' . e(__('il tuo account è stato creato. Ora puoi seguire i tuoi ordini e acquistare più velocemente.')) . '</p>'
            . '<p><a href="' . e(Config::baseUrl() . '/account') . '">' . e(__('Vai al tuo account')) . '</a></p></div>';
        Mailer::send($email, __('Benvenuto su %s', $store), $html);
    }
}
