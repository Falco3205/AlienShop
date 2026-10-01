<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\Auth;
use Alien\Core\Config;
use Alien\Core\DB;
use Alien\Core\Settings;

final class Installer
{
    public static function installed(): bool
    {
        return Config::installed();
    }

    public static function install(array $d): array
    {
        $errors = [];
        if (!filter_var($d['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email amministratore non valida.';
        }
        if (strlen((string)($d['admin_password'] ?? '')) < 10) {
            $errors[] = 'La password deve avere almeno 10 caratteri.';
        }
        if (!preg_match('#^https?://#', (string)($d['url'] ?? ''))) {
            $errors[] = 'Indirizzo del pannello non valido (es. https://falconefabio.it/alienshop).';
        }
        if ($errors) {
            return $errors;
        }
        $db = ($d['db'] ?? 'sqlite') === 'mysql'
            ? ['driver' => 'mysql', 'host' => (string)($d['db_host'] ?? 'localhost'), 'port' => (int)($d['db_port'] ?? 3306), 'name' => (string)$d['db_name'], 'user' => (string)$d['db_user'], 'pass' => (string)($d['db_pass'] ?? '')]
            : ['driver' => 'sqlite', 'path' => ROOT . '/storage/hub.sqlite'];
        try {
            $pdo = DB::connect($db);
            \Alien\Services\Installer::createSchema($pdo, $db['driver']);
        } catch (\Throwable $e) {
            return ['Database: ' . $e->getMessage()];
        }
        Config::write(['app' => ['url' => rtrim((string)$d['url'], '/'), 'key' => bin2hex(random_bytes(32)), 'debug' => false], 'db' => $db]);
        Settings::setMany(['installed_at' => now(), 'shop_repo' => 'Falco3205/AlienShop', 'shop_branch' => 'main', 'default_theme' => 'aurora', 'default_lang' => 'it', 'default_admin_email' => (string)$d['admin_email']]);
        Auth::create((string)$d['admin_email'], (string)$d['admin_password'], (string)($d['admin_name'] ?? 'Admin'), 'admin');
        file_put_contents(ROOT . '/storage/installed.lock', now());
        return [];
    }
}
