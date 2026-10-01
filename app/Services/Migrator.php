<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Settings;

final class Migrator
{
    public const VERSION = 3;

    private const COLUMNS = [
        ['products', 'rating_avg', 'INTEGER NOT NULL DEFAULT 0'],
        ['products', 'rating_count', 'INTEGER NOT NULL DEFAULT 0'],
        ['orders', 'invoice_number', "VARCHAR(30) NOT NULL DEFAULT ''"],
        ['orders', 'invoice_date', 'VARCHAR(19)'],
        ['orders', 'review_asked', 'INTEGER NOT NULL DEFAULT 0'],
        ['orders', 'tax_rate', 'INTEGER'],
    ];

    public static function needed(): bool
    {
        return (int)Settings::get('schema_version', 1) < self::VERSION;
    }

    public static function run(): void
    {
        $pdo = DB::pdo();
        Installer::createSchema($pdo, DB::driver());
        foreach (self::COLUMNS as [$table, $column, $def]) {
            if (!self::hasColumn($table, $column)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $def");
            }
        }
        Settings::set('schema_version', self::VERSION);
        Settings::set('version', ALIEN_VERSION);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        if (DB::driver() === 'mysql') {
            return (bool)DB::val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
        }
        foreach (DB::all("PRAGMA table_info($table)") as $c) {
            if ($c['name'] === $column) {
                return true;
            }
        }
        return false;
    }
}
