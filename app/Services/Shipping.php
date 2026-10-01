<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;

final class Shipping
{
    public static function methods(string $country = ''): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM shipping_methods WHERE active = 1 ORDER BY position, id') as $m) {
            $list = array_filter(array_map('trim', explode(',', strtoupper((string)$m['countries']))));
            if (!$list || $country === '' || in_array(strtoupper($country), $list, true)) {
                $out[] = $m;
            }
        }
        return $out;
    }

    public static function price(array $method, int $subtotalAfterDiscount, bool $couponFree): int
    {
        if ($couponFree || ((int)$method['free_over'] > 0 && $subtotalAfterDiscount >= (int)$method['free_over'])) {
            return 0;
        }
        return (int)$method['price'];
    }

    public static function countries(): array
    {
        return [
            'IT' => __('Italia'), 'DE' => __('Germania'), 'FR' => __('Francia'), 'ES' => __('Spagna'), 'AT' => __('Austria'),
            'BE' => __('Belgio'), 'NL' => __('Paesi Bassi'), 'PT' => __('Portogallo'), 'CH' => __('Svizzera'), 'GB' => __('Regno Unito'),
            'IE' => __('Irlanda'), 'PL' => __('Polonia'), 'SE' => __('Svezia'), 'DK' => __('Danimarca'), 'GR' => __('Grecia'),
            'US' => __('Stati Uniti'), 'CA' => __('Canada'), 'AU' => __('Australia'),
        ];
    }
}
