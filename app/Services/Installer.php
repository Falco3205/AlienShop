<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Auth;
use Alien\Core\Config;
use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Core\Settings;

final class Installer
{
    public static function requirements(): array
    {
        $writable = static fn(string $p) => (is_dir($p) || @mkdir($p, 0755, true)) && is_writable($p);
        return [
            ['PHP >= 8.1 (' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), true],
            ['PDO + SQLite o MySQL', extension_loaded('pdo') && (extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql')), true],
            ['mbstring', extension_loaded('mbstring'), true],
            ['GD (immagini WebP)', extension_loaded('gd'), true],
            ['cURL (pagamenti, import immagini)', extension_loaded('curl'), true],
            ['OpenSSL', extension_loaded('openssl'), true],
            ['intl (slug SEO)', extension_loaded('intl'), false],
            ['config/ scrivibile', $writable(ROOT . '/config'), true],
            ['storage/ scrivibile', $writable(ROOT . '/storage') && $writable(ROOT . '/storage/cache') && $writable(ROOT . '/storage/logs'), true],
            ['public/uploads/ scrivibile', $writable(ROOT . '/public/uploads'), true],
            ['public/assets/ scrivibile', $writable(ROOT . '/public/assets'), true],
        ];
    }

    public static function requirementsMet(): bool
    {
        foreach (self::requirements() as [, $ok, $required]) {
            if ($required && !$ok) {
                return false;
            }
        }
        return true;
    }

    public static function dbConfig(array $d): array
    {
        if (($d['db_driver'] ?? 'sqlite') === 'mysql') {
            return [
                'driver' => 'mysql',
                'host' => trim((string)($d['db_host'] ?? 'localhost')),
                'port' => (int)($d['db_port'] ?? 3306) ?: 3306,
                'name' => trim((string)($d['db_name'] ?? '')),
                'user' => trim((string)($d['db_user'] ?? '')),
                'pass' => (string)($d['db_pass'] ?? ''),
            ];
        }
        return ['driver' => 'sqlite', 'path' => ROOT . '/storage/db/alienshop.sqlite'];
    }

    public static function testDb(array $db): ?string
    {
        try {
            if ($db['driver'] === 'mysql' && !extension_loaded('pdo_mysql')) {
                return 'Estensione pdo_mysql non disponibile.';
            }
            if ($db['driver'] === 'sqlite') {
                if (!extension_loaded('pdo_sqlite')) {
                    return 'Estensione pdo_sqlite non disponibile.';
                }
                @mkdir(dirname($db['path']), 0750, true);
                if (!is_writable(dirname($db['path']))) {
                    return 'La cartella storage/db non è scrivibile.';
                }
            }
            DB::connect($db);
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public static function validate(array $d): array
    {
        $errors = [];
        if (!self::requirementsMet()) {
            $errors[] = 'Requisiti di sistema non soddisfatti.';
        }
        $db = self::dbConfig($d);
        if ($db['driver'] === 'mysql' && ($db['name'] === '' || $db['user'] === '')) {
            $errors[] = 'Inserisci nome database e utente MySQL.';
        } elseif ($err = self::testDb($db)) {
            $errors[] = 'Database: ' . $err;
        }
        if (trim((string)($d['store_name'] ?? '')) === '') {
            $errors[] = 'Inserisci il nome del negozio.';
        }
        if (!filter_var($d['store_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email del negozio non valida.';
        }
        if (!filter_var($d['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email amministratore non valida.';
        }
        if (strlen((string)($d['admin_password'] ?? '')) < 8) {
            $errors[] = 'La password amministratore deve avere almeno 8 caratteri.';
        }
        if (($d['admin_password'] ?? '') !== ($d['admin_password2'] ?? '')) {
            $errors[] = 'Le password non coincidono.';
        }
        if (!Themes::get((string)($d['theme'] ?? ''))) {
            $errors[] = 'Seleziona un tema.';
        }
        return $errors;
    }

    public static function install(array $d): void
    {
        $db = self::dbConfig($d);
        $pdo = DB::connect($db);
        self::createSchema($pdo, $db['driver']);

        $url = rtrim(trim((string)($d['app_url'] ?? '')) ?: Config::baseUrl(), '/');
        Config::write([
            'app' => ['url' => $url, 'key' => bin2hex(random_bytes(32)), 'debug' => false, 'cache_ttl' => 900],
            'db' => $db,
        ]);

        $locale = ($d['lang'] ?? 'it') === 'en' ? 'en' : 'it';
        $currency = in_array($d['currency'] ?? 'EUR', Money::currencies(), true) ? $d['currency'] : 'EUR';
        Settings::setMany([
            'store_name' => trim((string)$d['store_name']),
            'store_email' => trim((string)$d['store_email']),
            'store_tagline' => trim((string)($d['store_tagline'] ?? '')),
            'store_description' => trim((string)($d['store_description'] ?? '')),
            'locale' => $locale,
            'currency' => $currency,
            'default_country' => strtoupper(substr((string)($d['country'] ?? 'IT'), 0, 2)),
            'tax_rate' => (float)str_replace(',', '.', (string)($d['tax_rate'] ?? 22)),
            'prices_include_tax' => !empty($d['prices_include_tax']) ? 1 : 0,
            'order_prefix' => 'AS-',
            'products_per_page' => 24,
            'indexnow_enabled' => 1,
            'mail_driver' => 'mail',
            'hero_title' => trim((string)$d['store_name']),
            'hero_subtitle' => trim((string)($d['store_tagline'] ?? '')),
            'pay_bank_enabled' => 1,
            'pay_bank_title' => $locale === 'en' ? 'Bank transfer' : 'Bonifico bancario',
            'pay_bank_instructions' => $locale === 'en' ? "Please transfer the total to the IBAN provided by email.\nYour order ships when payment is received." : "Effettua il bonifico all'IBAN che riceverai via email.\nL'ordine verrà spedito alla ricezione del pagamento.",
            'installed_at' => now(),
            'version' => ALIEN_VERSION,
        ]);
        IndexNow::key();

        Auth::create((string)$d['admin_email'], (string)$d['admin_password'], trim((string)($d['admin_name'] ?? '')) ?: 'Admin', 'admin');
        self::seedShipping($locale, $currency);
        self::seedPages($locale);
        if (!empty($d['demo'])) {
            Demo::seed($locale);
        }
        Themes::activate((string)$d['theme']);
        file_put_contents(ROOT . '/storage/installed.lock', now());
        @chmod(ROOT . '/storage/installed.lock', 0640);
    }

    public static function createSchema(\PDO $pdo, string $driver): void
    {
        $sql = (string)file_get_contents(ROOT . '/database/schema.sql');
        $sql = str_replace(
            ['{PK}', '{ENGINE}'],
            $driver === 'mysql'
                ? ['INT NOT NULL AUTO_INCREMENT PRIMARY KEY', ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci']
                : ['INTEGER PRIMARY KEY AUTOINCREMENT', ''],
            $sql
        );
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }

    private static function seedShipping(string $locale, string $currency): void
    {
        $f = Money::factor($currency);
        DB::insert('shipping_methods', ['name' => $locale === 'en' ? 'Standard shipping' : 'Spedizione standard', 'price' => (int)round(5.9 * $f), 'free_over' => (int)round(59 * $f), 'countries' => '', 'position' => 1, 'active' => 1]);
        DB::insert('shipping_methods', ['name' => $locale === 'en' ? 'Express shipping' : 'Spedizione express', 'price' => (int)round(9.9 * $f), 'free_over' => 0, 'countries' => '', 'position' => 2, 'active' => 1]);
    }

    private static function seedPages(string $locale): void
    {
        $en = $locale === 'en';
        $pages = [
            ['privacy-policy', $en ? 'Privacy Policy' : 'Privacy Policy', $en ? '<p>This page describes how we collect and use your personal data when you shop with us. Edit this text from the admin panel.</p>' : '<p>Questa pagina descrive come raccogliamo e utilizziamo i tuoi dati personali quando acquisti da noi. Modifica questo testo dal pannello di amministrazione.</p>'],
            ['termini-e-condizioni', $en ? 'Terms & Conditions' : 'Termini e condizioni', $en ? '<p>General terms of sale. Edit this text from the admin panel.</p>' : '<p>Condizioni generali di vendita. Modifica questo testo dal pannello di amministrazione.</p>'],
            ['spedizioni-e-resi', $en ? 'Shipping & Returns' : 'Spedizioni e resi', $en ? '<p>Orders ship within 2 business days. You can return items within 14 days of delivery.</p>' : '<p>Gli ordini vengono spediti entro 2 giorni lavorativi. Puoi restituire gli articoli entro 14 giorni dalla consegna.</p>'],
            ['chi-siamo', $en ? 'About us' : 'Chi siamo', $en ? '<p>Tell your customers who you are and what makes your store special.</p>' : '<p>Racconta ai tuoi clienti chi sei e cosa rende speciale il tuo negozio.</p>'],
        ];
        foreach ($pages as $i => [$slug, $title, $content]) {
            DB::insert('pages', [
                'type' => 'page', 'title' => $title, 'slug' => $slug, 'content' => $content, 'excerpt' => '',
                'show_in_menu' => $slug === 'chi-siamo' ? 1 : 0, 'show_in_footer' => 1, 'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
