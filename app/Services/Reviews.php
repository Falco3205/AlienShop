<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Mailer;
use Alien\Core\Settings;

final class Reviews
{
    public static function forProduct(int $productId, int $limit = 30): array
    {
        return DB::all("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY id DESC LIMIT $limit", [$productId]);
    }

    public static function submit(int $productId, array $in, string $ip): ?string
    {
        $rating = (int)($in['rating'] ?? 0);
        $author = trim(mb_substr((string)($in['author'] ?? ''), 0, 120));
        $body = trim(mb_substr((string)($in['body'] ?? ''), 0, 3000));
        $email = mb_strtolower(trim((string)($in['email'] ?? '')));
        if ($rating < 1 || $rating > 5) {
            return __('Scegli un voto da 1 a 5 stelle.');
        }
        if ($author === '' || mb_strlen($body) < 10) {
            return __('Inserisci il tuo nome e un commento di almeno 10 caratteri.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return __('Inserisci un indirizzo email valido.');
        }
        $hash = substr(hash('sha256', $ip . (string)\Alien\Core\Config::get('app.key', '')), 0, 40);
        if ((int)DB::val('SELECT COUNT(*) FROM reviews WHERE ip_hash = ? AND created_at > ?', [$hash, date('Y-m-d H:i:s', time() - 3600)]) >= 3) {
            return __('Hai inviato troppe recensioni: riprova più tardi.');
        }
        if ($email !== '' && DB::val('SELECT 1 FROM reviews WHERE product_id = ? AND email = ?', [$productId, $email])) {
            return __('Hai già recensito questo prodotto.');
        }
        $verified = $email !== '' && (bool)DB::val("SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.email = ? AND oi.product_id = ? AND o.status NOT IN ('cancelled','refunded')", [$email, $productId]);
        $auto = (string)Settings::get('reviews_auto', '0') === '1';
        DB::insert('reviews', [
            'product_id' => $productId, 'author' => $author, 'email' => $email, 'rating' => $rating,
            'title' => trim(mb_substr((string)($in['title'] ?? ''), 0, 190)), 'body' => $body,
            'status' => $auto ? 'approved' : 'pending', 'verified' => $verified ? 1 : 0, 'ip_hash' => $hash, 'created_at' => now(),
        ]);
        self::refresh($productId);
        return null;
    }

    public static function refresh(int $productId): void
    {
        $row = DB::row("SELECT COUNT(*) AS n, COALESCE(AVG(rating), 0) AS avg FROM reviews WHERE product_id = ? AND status = 'approved'", [$productId]);
        DB::update('products', ['rating_count' => (int)$row['n'], 'rating_avg' => (int)round((float)$row['avg'] * 10)], 'id = ?', [$productId]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $r = DB::row('SELECT product_id FROM reviews WHERE id = ?', [$id]);
        if ($r && in_array($status, ['approved', 'rejected', 'pending'], true)) {
            DB::update('reviews', ['status' => $status], 'id = ?', [$id]);
            self::refresh((int)$r['product_id']);
        }
    }

    public static function delete(int $id): void
    {
        $r = DB::row('SELECT product_id FROM reviews WHERE id = ?', [$id]);
        if ($r) {
            DB::delete('reviews', 'id = ?', [$id]);
            self::refresh((int)$r['product_id']);
        }
    }

    public static function sendRequests(int $limit = 20): int
    {
        $days = max(1, (int)Settings::get('reviews_request_days', 7));
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
        $n = 0;
        foreach (DB::all("SELECT id FROM orders WHERE status IN ('shipped','completed') AND review_asked = 0 AND updated_at < ? AND email <> '' LIMIT $limit", [$cutoff]) as $o) {
            $order = Orders::find((int)$o['id']);
            DB::update('orders', ['review_asked' => 1], 'id = ?', [$o['id']]);
            if (!$order) {
                continue;
            }
            $links = '';
            foreach ($order['items'] as $it) {
                $p = DB::row('SELECT slug FROM products WHERE id = ?', [$it['product_id']]);
                if ($p) {
                    $links .= '<li><a href="' . e(url('products/' . $p['slug'] . '#reviews')) . '">' . e($it['name']) . '</a></li>';
                }
            }
            if ($links !== '') {
                Cron::queue($order['email'], __('Com\'è andato il tuo ordine %s?', $order['number']), '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto"><p>' . e(__('Grazie per aver acquistato da %s! Ci aiuti con una recensione?', Settings::get('store_name', ''))) . '</p><ul>' . $links . '</ul></div>', 'review-request');
                $n++;
            }
        }
        return $n;
    }
}
