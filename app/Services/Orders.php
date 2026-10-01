<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Config;
use Alien\Core\DB;
use Alien\Core\Mailer;
use Alien\Core\Money;
use Alien\Core\Settings;
use Alien\Core\Str;

final class Orders
{
    public const STATUSES = [
        'pending' => 'In attesa',
        'processing' => 'In lavorazione',
        'shipped' => 'Spedito',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
        'refunded' => 'Rimborsato',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Non pagato',
        'paid' => 'Pagato',
        'failed' => 'Fallito',
        'refunded' => 'Rimborsato',
    ];

    public static function create(array $lines, array $totals, array $customer, string $paymentMethod, ?int $userId): array
    {
        return DB::transaction(function () use ($lines, $totals, $customer, $paymentMethod, $userId) {
            $now = now();
            $token = Str::randomToken(24);
            $id = DB::insert('orders', [
                'number' => 'T' . Str::randomToken(6),
                'token' => $token,
                'user_id' => (int)$userId,
                'email' => $customer['email'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $paymentMethod,
                'currency' => Money::currency(),
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'shipping' => $totals['shipping'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'coupon_code' => $totals['coupon']['code'] ?? '',
                'shipping_method' => $totals['shipping_method']['name'] ?? '',
                'billing' => json_encode($customer['billing'], json_flags()),
                'shipping_address' => json_encode($customer['shipping'], json_flags()),
                'note' => $customer['note'] ?? '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $number = (string)Settings::get('order_prefix', 'AS-') . (1000 + $id);
            DB::update('orders', ['number' => $number], 'id = ?', [$id]);

            foreach ($lines as $l) {
                DB::insert('order_items', [
                    'order_id' => $id,
                    'product_id' => $l['product']['id'],
                    'variant_id' => $l['variant']['id'] ?? 0,
                    'name' => $l['name'],
                    'variant_label' => $l['label'],
                    'sku' => $l['sku'],
                    'price' => $l['unit'],
                    'qty' => $l['qty'],
                    'total' => $l['total'],
                ]);
                self::adjustStock($l['product'], $l['variant'], -$l['qty']);
            }
            if (!empty($totals['coupon'])) {
                DB::exec('UPDATE coupons SET used = used + 1 WHERE id = ?', [$totals['coupon']['id']]);
            }
            self::event($id, __('Ordine creato.'));
            return self::find($id);
        });
    }

    private static function adjustStock(array $product, ?array $variant, int $delta): void
    {
        if (!(int)$product['manage_stock']) {
            return;
        }
        if ($variant) {
            DB::exec('UPDATE variants SET stock = stock + ? WHERE id = ?', [$delta, $variant['id']]);
        } else {
            DB::exec('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [$delta, $product['id']]);
        }
        Catalog::refreshDerived((int)$product['id']);
    }

    public static function find(int $id): ?array
    {
        return self::hydrate(DB::row('SELECT * FROM orders WHERE id = ?', [$id]));
    }

    public static function findByToken(string $token): ?array
    {
        return self::hydrate(DB::row('SELECT * FROM orders WHERE token = ?', [$token]));
    }

    public static function findByNumber(string $number): ?array
    {
        return self::hydrate(DB::row('SELECT * FROM orders WHERE number = ?', [$number]));
    }

    private static function hydrate(?array $o): ?array
    {
        if (!$o) {
            return null;
        }
        $o['items'] = DB::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$o['id']]);
        $o['billing'] = json_decode((string)$o['billing'], true) ?: [];
        $o['shipping_address'] = json_decode((string)$o['shipping_address'], true) ?: [];
        $o['events'] = DB::all('SELECT * FROM order_events WHERE order_id = ? ORDER BY id DESC', [$o['id']]);
        return $o;
    }

    public static function event(int $orderId, string $message): void
    {
        DB::insert('order_events', ['order_id' => $orderId, 'message' => mb_substr($message, 0, 500), 'created_at' => now()]);
    }

    public static function markPaid(array $order, string $ref = ''): void
    {
        if ($order['payment_status'] === 'paid') {
            return;
        }
        DB::update('orders', [
            'payment_status' => 'paid',
            'status' => $order['status'] === 'pending' ? 'processing' : $order['status'],
            'payment_ref' => $ref !== '' ? $ref : $order['payment_ref'],
            'updated_at' => now(),
        ], 'id = ?', [$order['id']]);
        self::event((int)$order['id'], __('Pagamento ricevuto (%s).', $order['payment_method']));
        self::notify(self::find((int)$order['id']));
    }

    public static function markFailed(array $order, string $reason = ''): void
    {
        if ($order['payment_status'] === 'paid') {
            return;
        }
        DB::update('orders', ['payment_status' => 'failed', 'updated_at' => now()], 'id = ?', [$order['id']]);
        self::event((int)$order['id'], __('Pagamento non riuscito.') . ($reason !== '' ? ' ' . $reason : ''));
    }

    public static function setStatus(int $id, string $status, string $tracking = ''): void
    {
        $o = self::find($id);
        if (!$o || !isset(self::STATUSES[$status])) {
            return;
        }
        $restock = in_array($status, ['cancelled', 'refunded'], true) && !in_array($o['status'], ['cancelled', 'refunded'], true);
        $fields = ['status' => $status, 'updated_at' => now()];
        if ($tracking !== '') {
            $fields['tracking'] = $tracking;
        }
        if ($status === 'refunded') {
            $fields['payment_status'] = 'refunded';
        }
        DB::update('orders', $fields, 'id = ?', [$id]);
        if ($restock) {
            foreach ($o['items'] as $it) {
                $product = Catalog::product((int)$it['product_id']);
                if ($product) {
                    $variant = null;
                    foreach ($product['variants'] as $v) {
                        if ((int)$v['id'] === (int)$it['variant_id']) {
                            $variant = $v;
                        }
                    }
                    self::adjustStock($product, $variant, (int)$it['qty']);
                }
            }
            self::event($id, __('Magazzino ripristinato.'));
        }
        self::event($id, __('Stato cambiato in: %s', __(self::STATUSES[$status])));
        if ($status === 'shipped' && $o['email']) {
            Mailer::send($o['email'], __('Il tuo ordine %s è stato spedito', $o['number']), self::emailHtml(self::find($id), __('Il tuo ordine è in viaggio!')));
        }
    }

    public static function notify(array $order): void
    {
        $subject = __('Conferma ordine %s', $order['number']);
        Mailer::send($order['email'], $subject, self::emailHtml($order, __('Grazie per il tuo ordine!')));
        $admin = (string)Settings::get('store_email', '');
        if ($admin !== '') {
            Mailer::send($admin, __('Nuovo ordine %s', $order['number']) . ' — ' . Money::format((int)$order['total'], $order['currency']), self::emailHtml($order, __('Hai ricevuto un nuovo ordine.')));
        }
    }

    public static function emailHtml(array $o, string $intro): string
    {
        $rows = '';
        foreach ($o['items'] as $it) {
            $rows .= '<tr><td style="padding:6px 0">' . e($it['name']) . ($it['variant_label'] ? ' <small>(' . e($it['variant_label']) . ')</small>' : '')
                . ' × ' . (int)$it['qty'] . '</td><td style="text-align:right">' . e(Money::format((int)$it['total'], $o['currency'])) . '</td></tr>';
        }
        $line = static fn(string $l, int $v) => $v ? '<tr><td>' . e($l) . '</td><td style="text-align:right">' . e(Money::format($v, $o['currency'])) . '</td></tr>' : '';
        $ship = $o['shipping_address'];
        $addr = e(trim(($ship['name'] ?? '') . ', ' . ($ship['address'] ?? '') . ', ' . ($ship['zip'] ?? '') . ' ' . ($ship['city'] ?? '') . ' ' . ($ship['country'] ?? ''), ' ,'));
        $store = e(Settings::get('store_name', 'Shop'));
        $link = Config::baseUrl() . '/checkout/thank-you/' . $o['token'];
        return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#222"><h2>' . $store . '</h2><p>' . e($intro) . '</p>'
            . '<p><strong>' . e(__('Ordine')) . ' ' . e($o['number']) . '</strong></p>'
            . '<table width="100%" cellspacing="0" style="border-top:1px solid #ddd;border-bottom:1px solid #ddd">' . $rows . '</table>'
            . '<table width="100%" cellspacing="0" style="margin-top:10px">'
            . $line(__('Subtotale'), (int)$o['subtotal']) . $line(__('Sconto'), -(int)$o['discount']) . $line(__('Spedizione'), (int)$o['shipping'])
            . '<tr><td><strong>' . e(__('Totale')) . '</strong></td><td style="text-align:right"><strong>' . e(Money::format((int)$o['total'], $o['currency'])) . '</strong></td></tr></table>'
            . '<p>' . e(__('Spedizione a')) . ': ' . $addr . '</p>'
            . '<p>' . e(__('Pagamento')) . ': ' . e($o['payment_method']) . ' — ' . e(__(self::PAYMENT_STATUSES[$o['payment_status']] ?? $o['payment_status'])) . '</p>'
            . ($o['tracking'] ? '<p>' . e(__('Tracking')) . ': ' . e($o['tracking']) . '</p>' : '')
            . '<p><a href="' . e($link) . '">' . e(__('Vedi il tuo ordine')) . '</a></p></div>';
    }

    public static function forUser(int $userId): array
    {
        return DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', [$userId]);
    }

    public static function stats(int $days = 30): array
    {
        $since = date('Y-m-d 00:00:00', strtotime("-$days days"));
        $row = DB::row("SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled','refunded')", [$since]);
        $daily = DB::all("SELECT SUBSTR(created_at,1,10) AS d, COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled','refunded') GROUP BY SUBSTR(created_at,1,10) ORDER BY d", [$since]);
        $top = DB::all("SELECT oi.name, SUM(oi.qty) AS qty, SUM(oi.total) AS total FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.created_at >= ? AND o.status NOT IN ('cancelled','refunded') GROUP BY oi.name ORDER BY qty DESC LIMIT 5", [$since]);
        $prevFrom = date('Y-m-d 00:00:00', strtotime('-' . ($days * 2) . ' days'));
        $prev = DB::row("SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue FROM orders WHERE created_at >= ? AND created_at < ? AND status NOT IN ('cancelled','refunded')", [$prevFrom, $since]);
        $series = [];
        foreach ($daily as $d) {
            $series[$d['d']] = (int)$d['t'];
        }
        $filled = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $filled[] = ['d' => $day, 't' => $series[$day] ?? 0];
        }
        return ['orders' => (int)$row['orders'], 'revenue' => (int)$row['revenue'], 'prev_orders' => (int)$prev['orders'], 'prev_revenue' => (int)$prev['revenue'], 'daily' => $filled, 'top' => $top];
    }
}
