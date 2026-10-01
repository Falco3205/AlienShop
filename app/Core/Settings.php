<?php
declare(strict_types=1);

namespace Alien\Core;

final class Settings
{
    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];
            foreach (DB::all('SELECT skey, svalue FROM settings') as $r) {
                self::$all[$r['skey']] = $r['svalue'];
            }
        }
        return self::$all;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $a = self::all();
        return array_key_exists($key, $a) && $a[$key] !== null && $a[$key] !== '' ? $a[$key] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $value = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
        DB::exec('REPLACE INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
        self::$all ??= [];
        self::$all[$key] = $value;
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            self::set($k, $v);
        }
    }

    public static function json(string $key, array $default = []): array
    {
        $v = self::get($key);
        $d = $v ? json_decode((string)$v, true) : null;
        return is_array($d) ? $d : $default;
    }
}
