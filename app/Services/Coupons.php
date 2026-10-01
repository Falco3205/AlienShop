<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;

final class Coupons
{
    public static function validate(string $code, int $subtotal): array
    {
        $c = DB::row('SELECT * FROM coupons WHERE code = ?', [strtoupper(trim($code))]);
        $now = now();
        $error = match (true) {
            !$c || !$c['active'] => __('Codice sconto non valido.'),
            $c['starts_at'] && $c['starts_at'] > $now => __('Il codice sconto non è ancora attivo.'),
            $c['expires_at'] && $c['expires_at'] < $now => __('Il codice sconto è scaduto.'),
            (int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses'] => __('Il codice sconto ha raggiunto il limite di utilizzi.'),
            $subtotal < (int)$c['min_subtotal'] => __('Importo minimo non raggiunto per questo codice.'),
            default => null,
        };
        return $error ? [null, $error] : [$c, null];
    }

    public static function discount(array $coupon, int $subtotal): int
    {
        $d = $coupon['type'] === 'percent'
            ? (int)round($subtotal * min(100, (int)$coupon['value']) / 100)
            : (int)$coupon['value'];
        return max(0, min($subtotal, $d));
    }
}
