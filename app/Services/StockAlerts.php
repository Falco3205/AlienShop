<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Settings;

final class StockAlerts
{
    public static function subscribe(int $productId, string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return __('Inserisci un indirizzo email valido.');
        }
        if (!DB::val('SELECT 1 FROM stock_alerts WHERE product_id = ? AND email = ?', [$productId, $email])) {
            DB::insert('stock_alerts', ['product_id' => $productId, 'email' => $email, 'created_at' => now()]);
        }
        return null;
    }

    public static function notify(int $productId): void
    {
        $p = DB::row("SELECT name, slug FROM products WHERE id = ? AND status = 'active'", [$productId]);
        if (!$p) {
            return;
        }
        $link = url('products/' . $p['slug']);
        foreach (DB::all('SELECT * FROM stock_alerts WHERE product_id = ?', [$productId]) as $a) {
            Cron::queue($a['email'], __('%s è di nuovo disponibile!', $p['name']), '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto"><p>' . e(__('Buone notizie: "%s" è tornato disponibile su %s.', $p['name'], Settings::get('store_name', ''))) . '</p><p><a href="' . e($link) . '" style="background:#111;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">' . e(__('Vai al prodotto')) . '</a></p></div>', 'stock-alert');
        }
        DB::delete('stock_alerts', 'product_id = ?', [$productId]);
    }
}
