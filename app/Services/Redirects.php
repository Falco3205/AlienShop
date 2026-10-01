<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;

final class Redirects
{
    public static function normalize(string $path): string
    {
        $path = (string)parse_url($path, PHP_URL_PATH);
        return trim(rawurldecode($path), '/');
    }

    public static function add(string $from, string $to, int $code = 301): void
    {
        $from = self::normalize($from);
        $to = preg_match('#^https?://#', $to) ? $to : self::normalize($to);
        if ($from === '' || $from === $to) {
            return;
        }
        $existing = DB::row('SELECT id FROM redirects WHERE from_path = ?', [$from]);
        if ($existing) {
            DB::update('redirects', ['to_path' => $to, 'code' => $code], 'id = ?', [$existing['id']]);
        } else {
            DB::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'code' => $code, 'created_at' => now()]);
        }
        DB::update('redirects', ['to_path' => $to], 'to_path = ? AND from_path <> ?', [$from, $to]);
        DB::delete('redirects', 'from_path = to_path');
    }

    public static function remove(string $from): void
    {
        DB::delete('redirects', 'from_path = ?', [self::normalize($from)]);
    }

    public static function resolve(string $path): ?array
    {
        $r = DB::row('SELECT * FROM redirects WHERE from_path = ?', [self::normalize($path)]);
        if ($r) {
            DB::exec('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$r['id']]);
        }
        return $r;
    }

    public static function importCsv(string $csv): int
    {
        $n = 0;
        foreach (preg_split('/\R/', $csv) ?: [] as $line) {
            $cols = str_getcsv(trim($line), ',', '"', '\\');
            if (count($cols) < 2 || $cols[0] === '' || strtolower($cols[0]) === 'from') {
                continue;
            }
            self::add($cols[0], $cols[1], (int)($cols[2] ?? 301) === 302 ? 302 : 301);
            $n++;
        }
        return $n;
    }
}
