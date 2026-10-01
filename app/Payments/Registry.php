<?php
declare(strict_types=1);

namespace Alien\Payments;

final class Registry
{
    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];
            foreach (glob(__DIR__ . '/*Gateway.php') ?: [] as $file) {
                $class = __NAMESPACE__ . '\\' . basename($file, '.php');
                if ($class === Gateway::class || !class_exists($class)) {
                    continue;
                }
                $g = new $class();
                if ($g instanceof Gateway) {
                    self::$all[$g->id()] = $g;
                }
            }
            $order = ['stripe' => 0, 'paypal' => 1, 'mollie' => 2, 'bank' => 3, 'cod' => 4];
            uksort(self::$all, static fn($a, $b) => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));
        }
        return self::$all;
    }

    public static function get(string $id): ?Gateway
    {
        return self::all()[$id] ?? null;
    }

    public static function enabled(): array
    {
        return array_filter(self::all(), static fn(Gateway $g) => $g->enabled());
    }
}
