<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Session;
use Alien\Core\Settings;
use Alien\Core\Str;

final class AbandonedCarts
{
    public static function capture(string $email): void
    {
        $email = mb_strtolower(trim($email));
        if (!Modules::on('abandoned_cart') || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $lines = Cart::lines();
        if (!$lines) {
            return;
        }
        $items = array_values(array_map(static fn($l) => ['pid' => (int)$l['product']['id'], 'vid' => (int)($l['variant']['id'] ?? 0), 'qty' => (int)$l['qty']], $lines));
        $total = Cart::totals($lines)['total'];
        $row = DB::row('SELECT id FROM abandoned_carts WHERE email = ? AND recovered = 0 AND reminded_at IS NULL', [$email]);
        $data = ['cart' => json_encode($items), 'total' => $total, 'updated_at' => now()];
        if ($row) {
            DB::update('abandoned_carts', $data, 'id = ?', [$row['id']]);
        } else {
            DB::insert('abandoned_carts', $data + ['email' => $email, 'token' => Str::randomToken(16), 'created_at' => now()]);
        }
    }

    public static function orderPlaced(string $email): void
    {
        $email = mb_strtolower($email);
        DB::exec('UPDATE abandoned_carts SET recovered = 1 WHERE email = ? AND recovered = 0 AND reminded_at IS NOT NULL', [$email]);
        DB::exec('DELETE FROM abandoned_carts WHERE email = ? AND recovered = 0 AND reminded_at IS NULL', [$email]);
    }

    public static function restore(string $token): bool
    {
        $row = DB::row('SELECT * FROM abandoned_carts WHERE token = ?', [$token]);
        if (!$row) {
            return false;
        }
        Cart::clear();
        foreach ((array)json_decode((string)$row['cart'], true) as $it) {
            Cart::add((int)$it['pid'], (int)$it['vid'], [], max(1, (int)$it['qty']));
        }
        $code = (string)Settings::get('abandoned_coupon', '');
        if ($code !== '') {
            Cart::applyCoupon($code);
        }
        return true;
    }

    public static function sendReminders(int $limit = 20): int
    {
        $hours = max(1, (int)Settings::get('abandoned_delay', 2));
        $before = date('Y-m-d H:i:s', time() - $hours * 3600);
        $after = date('Y-m-d H:i:s', time() - 7 * 86400);
        $n = 0;
        foreach (DB::all("SELECT * FROM abandoned_carts WHERE recovered = 0 AND reminded_at IS NULL AND updated_at < ? AND updated_at > ? LIMIT $limit", [$before, $after]) as $c) {
            if (DB::val("SELECT 1 FROM orders WHERE email = ? AND created_at > ? AND status NOT IN ('cancelled')", [$c['email'], $c['created_at']])) {
                DB::delete('abandoned_carts', 'id = ?', [$c['id']]);
                continue;
            }
            if (self::remind($c)) {
                $n++;
            }
        }
        return $n;
    }

    public static function remind(array $c): bool
    {
        $rows = '';
        foreach ((array)json_decode((string)$c['cart'], true) as $it) {
            $p = DB::row('SELECT name, slug, image FROM products WHERE id = ? AND status = \'active\'', [$it['pid']]);
            if ($p) {
                $rows .= '<tr><td style="padding:6px 0"><a href="' . e(url('products/' . $p['slug'])) . '">' . e($p['name']) . '</a> × ' . (int)$it['qty'] . '</td></tr>';
            }
        }
        if ($rows === '') {
            DB::delete('abandoned_carts', 'id = ?', [$c['id']]);
            return false;
        }
        $code = (string)Settings::get('abandoned_coupon', '');
        $store = (string)Settings::get('store_name', '');
        $subject = (string)Settings::get('abandoned_subject', '') ?: __('Hai dimenticato qualcosa nel carrello?');
        $body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto"><h2>' . e($store) . '</h2><p>' . e(__('Hai lasciato questi prodotti nel carrello:')) . '</p><table>' . $rows . '</table>'
            . ($code !== '' ? '<p><strong>' . e(__('Per te un regalo: usa il codice %s al checkout (già applicato dal link).', $code)) . '</strong></p>' : '')
            . '<p><a href="' . e(url('cart/recover/' . $c['token'])) . '" style="background:#111;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none">' . e(__('Completa il tuo ordine')) . '</a></p></div>';
        Cron::queue($c['email'], $subject, $body, 'abandoned');
        DB::update('abandoned_carts', ['reminded_at' => now()], 'id = ?', [$c['id']]);
        return true;
    }

    public static function stats(): array
    {
        return [
            'waiting' => (int)DB::val('SELECT COUNT(*) FROM abandoned_carts WHERE recovered = 0 AND reminded_at IS NULL'),
            'reminded' => (int)DB::val('SELECT COUNT(*) FROM abandoned_carts WHERE recovered = 0 AND reminded_at IS NOT NULL'),
            'recovered' => (int)DB::val('SELECT COUNT(*) FROM abandoned_carts WHERE recovered = 1'),
            'recovered_value' => (int)DB::val('SELECT COALESCE(SUM(total),0) FROM abandoned_carts WHERE recovered = 1'),
        ];
    }
}
