<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Money;
use Alien\Core\Settings;

final class Analytics
{
    private static array $events = [];

    public static function validId(string $id): bool
    {
        return (bool)preg_match('/^G-[A-Z0-9]{6,14}$/', $id);
    }

    public static function id(): string
    {
        $id = strtoupper(trim((string)Settings::get('analytics_id', '')));
        return self::validId($id) ? $id : '';
    }

    public static function enabled(): bool
    {
        return self::id() !== '' && !Settings::get('discourage_indexing');
    }

    public static function consentRequired(): bool
    {
        return (string)Settings::get('analytics_consent', '1') === '1';
    }

    public static function ecommerce(): bool
    {
        return (string)Settings::get('analytics_ecommerce', '1') === '1';
    }

    public static function event(string $name, array $params): void
    {
        if (self::enabled() && (self::ecommerce() || !in_array($name, ['view_item', 'begin_checkout', 'purchase', 'add_to_cart'], true))) {
            self::$events[] = ['name' => $name, 'params' => $params];
        }
    }

    public static function item(array $row, ?int $price = null, int $qty = 1, string $variant = ''): array
    {
        $item = [
            'item_id' => ($row['sku'] ?? '') !== '' ? $row['sku'] : (string)($row['id'] ?? $row['product_id'] ?? ''),
            'item_name' => $row['name'],
            'price' => self::amount($price ?? (int)$row['price']),
            'quantity' => $qty,
        ];
        if ($variant !== '') {
            $item['item_variant'] = $variant;
        }
        if (!empty($row['vendor'])) {
            $item['item_brand'] = $row['vendor'];
        }
        return $item;
    }

    public static function amount(int $cents): float
    {
        return round($cents / Money::factor(), Money::decimals());
    }

    public static function render(): string
    {
        if (!self::enabled()) {
            return '';
        }
        $config = [
            'id' => self::id(),
            'consent' => self::consentRequired(),
            'currency' => Money::currency(),
            'events' => self::$events,
        ];
        return '<script>window.ASGA=' . json_encode($config, json_flags()) . ";</script>\n";
    }

    public static function check(): array
    {
        $id = self::id();
        if ($id === '') {
            return ['ok' => false, 'message' => __('Nessun ID di misurazione impostato.')];
        }
        $res = \Alien\Core\Http::request('GET', url(), null, ['Cache-Control: no-cache'], 5);
        if ($res['status'] === 0) {
            return ['ok' => true, 'unverified' => true, 'message' => __('Il codice %s è configurato, ma il server non riesce a contattare il sito per controllarlo (succede in ambienti locali o con firewall). Apri il negozio e verifica in Google Analytics → Tempo reale.', $id)];
        }
        if ($res['status'] !== 200) {
            return ['ok' => false, 'message' => __('La home page ha risposto con errore HTTP %d.', $res['status'])];
        }
        if (!str_contains($res['body'], 'window.ASGA') || !str_contains($res['body'], $id)) {
            return ['ok' => false, 'message' => __('Il codice di tracciamento non è presente nella home page.')];
        }
        return ['ok' => true, 'message' => __('Il codice di tracciamento %s è installato correttamente.', $id)];
    }
}
