<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Session;
use Alien\Core\Settings;

final class Cart
{
    private static function items(): array
    {
        Session::start();
        return $_SESSION['cart'] ?? [];
    }

    private static function store(array $items): void
    {
        $_SESSION['cart'] = $items;
        $count = array_sum(array_column($items, 'qty'));
        setcookie('as_cart', (string)$count, [
            'expires' => $count ? time() + 86400 * 14 : time() - 3600,
            'path' => \Alien\Core\Session::path(),
            'secure' => is_https(),
            'samesite' => 'Lax',
        ]);
    }

    public static function count(): int
    {
        return (int)array_sum(array_column(self::items(), 'qty'));
    }

    public static function add(int $productId, int $variantId, array $options, int $qty): ?string
    {
        $product = Catalog::product($productId);
        if (!$product || $product['status'] !== 'active') {
            return __('Prodotto non disponibile.');
        }
        $variant = null;
        if ($product['type'] === 'variable') {
            $variant = $variantId ? self::variantById($product, $variantId) : Catalog::findVariant($product, $options);
            if (!$variant || !$variant['active']) {
                return __('Seleziona tutte le opzioni del prodotto.');
            }
        }
        $key = $productId . ':' . ($variant['id'] ?? 0);
        $items = self::items();
        $newQty = ($items[$key]['qty'] ?? 0) + max(1, $qty);
        if (!self::available($product, $variant, $newQty)) {
            return __('Quantità non disponibile in magazzino.');
        }
        $items[$key] = ['pid' => $productId, 'vid' => (int)($variant['id'] ?? 0), 'qty' => $newQty];
        self::store($items);
        Stats::bump('carts', $productId, max(1, $qty));
        return null;
    }

    private static function variantById(array $product, int $id): ?array
    {
        foreach ($product['variants'] as $v) {
            if ((int)$v['id'] === $id) {
                return $v;
            }
        }
        return null;
    }

    public static function available(array $product, ?array $variant, int $qty): bool
    {
        if (!(int)$product['manage_stock']) {
            return true;
        }
        $stock = $variant ? (int)$variant['stock'] : (int)$product['stock_qty'];
        return $qty <= $stock;
    }

    public static function setQty(string $key, int $qty): void
    {
        $items = self::items();
        if (!isset($items[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['qty'] = min(99, $qty);
        }
        self::store($items);
    }

    public static function clear(): void
    {
        Session::start();
        unset($_SESSION['coupon'], $_SESSION['ship_method']);
        self::store([]);
    }

    public static function lines(): array
    {
        $lines = [];
        $items = self::items();
        foreach ($items as $key => $it) {
            $product = Catalog::product((int)$it['pid']);
            if (!$product || $product['status'] !== 'active') {
                unset($items[$key]);
                continue;
            }
            $variant = $it['vid'] ? self::variantById($product, (int)$it['vid']) : null;
            if ($product['type'] === 'variable' && (!$variant || !$variant['active'])) {
                unset($items[$key]);
                continue;
            }
            $qty = (int)$it['qty'];
            if ((int)$product['manage_stock']) {
                $stock = $variant ? (int)$variant['stock'] : (int)$product['stock_qty'];
                if ($stock <= 0) {
                    unset($items[$key]);
                    continue;
                }
                $qty = min($qty, $stock);
            }
            $unit = $variant ? (int)$variant['final_price'] : (int)$product['price'];
            $lines[$key] = [
                'key' => $key,
                'product' => $product,
                'variant' => $variant,
                'name' => $product['name'],
                'label' => $variant ? implode(' / ', $variant['options']) : '',
                'sku' => $variant && $variant['sku'] !== '' ? $variant['sku'] : $product['sku'],
                'image' => ($variant['image'] ?? null) ?: $product['image'],
                'url' => Catalog::productUrl($product),
                'unit' => $unit,
                'qty' => $qty,
                'total' => $unit * $qty,
            ];
            $items[$key]['qty'] = $qty;
        }
        if ($items !== self::items()) {
            self::store($items);
        }
        return $lines;
    }

    public static function totals(?array $lines = null, ?int $shippingId = null, string $country = ''): array
    {
        $lines ??= self::lines();
        $subtotal = array_sum(array_column($lines, 'total'));

        $coupon = null;
        $couponError = null;
        $code = $_SESSION['coupon'] ?? '';
        if ($code !== '' && $lines) {
            [$coupon, $couponError] = Coupons::validate($code, $subtotal);
            if (!$coupon) {
                unset($_SESSION['coupon']);
            }
        }
        $discount = $coupon ? Coupons::discount($coupon, $subtotal) : 0;
        $net = $subtotal - $discount;

        $methods = $lines ? Shipping::methods($country) : [];
        $selected = null;
        $shippingId ??= (int)($_SESSION['ship_method'] ?? 0);
        foreach ($methods as $m) {
            if ((int)$m['id'] === $shippingId) {
                $selected = $m;
            }
        }
        $selected ??= $methods[0] ?? null;
        $shipping = $selected ? Shipping::price($selected, $net, $coupon && (int)$coupon['free_shipping'] === 1) : 0;

        $rate = self::taxRate($country);
        $includes = (bool)Settings::get('prices_include_tax', 1);
        $taxable = $net + $shipping;
        $tax = $rate > 0 ? (int)round($includes ? $taxable * $rate / (100 + $rate) : $taxable * $rate / 100) : 0;
        $total = $includes ? $taxable : $taxable + $tax;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'shipping' => $shipping,
            'shipping_method' => $selected,
            'shipping_methods' => $methods,
            'tax' => $tax,
            'tax_included' => $includes,
            'tax_rate' => $rate,
            'total' => max(0, $total),
            'count' => array_sum(array_column($lines, 'qty')),
        ];
    }

    public static function taxRate(string $country): float
    {
        foreach (preg_split('/\R+/', (string)Settings::get('tax_country_rates', '')) ?: [] as $line) {
            if (preg_match('/^\s*([A-Za-z]{2})\s*[=:]\s*([0-9]+(?:[.,][0-9]+)?)\s*$/', $line, $m) && strtoupper($m[1]) === strtoupper($country)) {
                return (float)str_replace(',', '.', $m[2]);
            }
        }
        return (float)Settings::get('tax_rate', 0);
    }

    public static function applyCoupon(string $code): ?string
    {
        Session::start();
        $subtotal = array_sum(array_column(self::lines(), 'total'));
        [$coupon, $error] = Coupons::validate($code, $subtotal);
        if (!$coupon) {
            return $error;
        }
        $_SESSION['coupon'] = $coupon['code'];
        return null;
    }

    public static function removeCoupon(): void
    {
        Session::start();
        unset($_SESSION['coupon']);
    }

    public static function stockIsAvailable(array $lines): ?string
    {
        foreach ($lines as $l) {
            $row = $l['variant']
                ? DB::row('SELECT stock FROM variants WHERE id = ?', [$l['variant']['id']])
                : DB::row('SELECT stock_qty AS stock FROM products WHERE id = ?', [$l['product']['id']]);
            if ((int)$l['product']['manage_stock'] && (!$row || (int)$row['stock'] < $l['qty'])) {
                return $l['name'];
            }
        }
        return null;
    }
}
