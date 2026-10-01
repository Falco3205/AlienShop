<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\Config;
use Alien\Core\DB;
use Alien\Core\Secret;
use Alien\Core\Settings;

final class Shops
{
    public const STATUS = ['pending' => 'In coda', 'installing' => 'Installazione', 'active' => 'Attivo', 'error' => 'Errore', 'suspended' => 'Sospeso'];

    public static function url(array $shop): string
    {
        return (string)Settings::get('shop_scheme', 'https') . '://' . $shop['domain'] . ($shop['path'] !== '' ? '/' . $shop['path'] : '');
    }

    public static function secret(array $shop): string
    {
        return Secret::open((string)$shop['secret']);
    }

    public static function metrics(array $shop): array
    {
        return json_decode((string)$shop['metrics'], true) ?: [];
    }

    public static function find(int $id): ?array
    {
        return DB::row('SELECT * FROM shops WHERE id = ?', [$id]);
    }

    public static function all(): array
    {
        return DB::all('SELECT * FROM shops ORDER BY name');
    }

    public static function validate(array $in): array
    {
        $errors = [];
        if (!preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/D', $in['domain'])) {
            $errors[] = 'Dominio non valido (es. negozio.cliente.it).';
        }
        if ($in['path'] !== '' && !preg_match('/^[a-z0-9][a-z0-9_-]{0,40}$/D', $in['path'])) {
            $errors[] = 'La cartella può contenere solo lettere minuscole, numeri, trattino e underscore.';
        }
        if (!in_array($in['path'], ['', 'alienshop'], true) && in_array($in['path'], ['admin', 'assets', 'uploads', 'wp-admin', 'wp-content', 'cgi-bin', 'hub', 'hestia'], true)) {
            $errors[] = 'Nome cartella riservato.';
        }
        if (trim($in['name']) === '') {
            $errors[] = 'Inserisci il nome del negozio.';
        }
        if (!filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email amministratore non valida.';
        }
        $node = Nodes::find((int)$in['node_id']);
        if (!$node || $node['role'] !== 'backend') {
            $errors[] = 'Scegli un server backend.';
        }
        if ($node && $node['tunnel_host'] !== '' && $in['mode'] !== 'edge') {
            $errors[] = 'Questo backend è raggiungibile solo tramite il tunnel: scegli la pubblicazione tramite frontend.';
        }
        if ($in['mode'] === 'edge') {
            $edge = Nodes::find((int)$in['edge_node_id']);
            if (!$edge || $edge['role'] !== 'edge') {
                $errors[] = 'Scegli un server frontend.';
            }
            if ($node && $node['upstream'] === '') {
                $errors[] = 'Il server backend non ha un indirizzo raggiungibile dal frontend.';
            }
        }
        if (DB::val('SELECT COUNT(*) FROM shops WHERE domain = ? AND path = ?', [$in['domain'], $in['path']])) {
            $errors[] = 'Esiste già un negozio su questo dominio e cartella.';
        }
        if (!preg_match('/^[a-z0-9_-]{1,40}$/D', $in['theme'])) {
            $errors[] = 'Tema non valido.';
        }
        return $errors;
    }

    private static function trustedProxies(array $backend, array $edge): array
    {
        $ranges = array_values(array_filter(array_map('trim', explode(',', (string)$backend['trusted']))));
        if ($ranges) {
            return $ranges;
        }
        if (Nodes::forwarded($backend)) {
            return array_values(array_filter(['127.0.0.1', $backend['address']]));
        }
        return $edge['address'] ? [$edge['address']] : [];
    }

    public static function installPayload(array $shop, string $password): array
    {
        $node = Nodes::find((int)$shop['node_id']);
        $edge = (int)$shop['edge_node_id'] ? Nodes::find((int)$shop['edge_node_id']) : null;
        return [
            'shop_id' => (int)$shop['id'],
            'domain' => $shop['domain'], 'path' => $shop['path'], 'hestia_user' => $shop['hestia_user'] ?: $node['hestia_user'],
            'mode' => $edge ? 'edge' : 'direct',
            'url' => self::url($shop),
            'repo' => (string)Settings::get('shop_repo', 'Falco3205/AlienShop'), 'branch' => (string)Settings::get('shop_branch', 'main'),
            'store_name' => $shop['name'], 'admin_email' => $shop['admin_email'], 'admin_password' => $password,
            'theme' => $shop['theme'], 'lang' => $shop['lang'], 'demo' => (int)$shop['demo'],
            'hub_url' => Config::baseUrl(), 'hub_secret' => self::secret($shop),
            'trusted_proxies' => $edge ? self::trustedProxies($node, $edge) : [],
        ];
    }

    public static function create(array $in): array
    {
        $in += ['path' => '', 'mode' => 'direct', 'edge_node_id' => 0, 'demo' => 0, 'lang' => (string)Settings::get('default_lang', 'it'), 'theme' => (string)Settings::get('default_theme', 'aurora')];
        $in['domain'] = mb_strtolower(trim((string)$in['domain']));
        $in['path'] = trim(mb_strtolower((string)$in['path']), '/ ');
        $in['admin_email'] = mb_strtolower(trim((string)$in['admin_email']));
        $errors = self::validate($in);
        if ($errors) {
            return [null, $errors];
        }
        $node = Nodes::find((int)$in['node_id']);
        $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'xz'), '=');
        $id = DB::insert('shops', [
            'name' => trim($in['name']), 'domain' => $in['domain'], 'path' => $in['path'], 'node_id' => (int)$in['node_id'],
            'edge_node_id' => $in['mode'] === 'edge' ? (int)$in['edge_node_id'] : 0, 'hestia_user' => $node['hestia_user'],
            'status' => 'pending', 'admin_email' => $in['admin_email'], 'admin_password' => Secret::seal($password),
            'theme' => $in['theme'], 'lang' => $in['lang'] === 'en' ? 'en' : 'it', 'demo' => (int)(bool)$in['demo'],
            'secret' => Secret::seal(bin2hex(random_bytes(24))), 'created_at' => now(),
        ]);
        $shop = self::find($id);
        Jobs::queue((int)$shop['node_id'], 'install_shop', self::installPayload($shop, $password), $id);
        return [$id, []];
    }

    public static function claim(array $node, string $user, string $domain, string $path): array
    {
        $domain = mb_strtolower($domain);
        $shop = DB::row('SELECT * FROM shops WHERE domain = ? AND path = ?', [$domain, $path]);
        if ($shop && !in_array($shop['status'], ['pending', 'error'], true)) {
            return [null, 'Esiste già un negozio attivo su questo dominio.'];
        }
        if (!$shop) {
            [$id, $errors] = self::create([
                'name' => $domain, 'domain' => $domain, 'path' => $path, 'node_id' => $node['id'],
                'admin_email' => (string)Settings::get('default_admin_email', ''),
            ]);
            if (!$id) {
                return [null, implode(' ', $errors)];
            }
            DB::exec("UPDATE jobs SET status = 'cancelled' WHERE shop_id = ? AND status = 'queued'", [$id]);
            $shop = self::find($id);
        } else {
            DB::exec("UPDATE jobs SET status = 'cancelled' WHERE shop_id = ? AND status = 'queued'", [$shop['id']]);
        }
        $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'xz'), '=');
        DB::update('shops', ['admin_password' => Secret::seal($password), 'hestia_user' => $user, 'status' => 'installing'], 'id = ?', [$shop['id']]);
        $shop = self::find((int)$shop['id']);
        $payload = self::installPayload($shop, $password);
        $payload['hestia_user'] = $user;
        $jobId = Jobs::startRunning((int)$node['id'], (int)$shop['id'], 'install_shop', $payload);
        return [['job_id' => $jobId, 'payload' => $payload], null];
    }

    public static function onJobDone(array $job, bool $ok, array $result, string $log): void
    {
        $shop = self::find((int)$job['shop_id']);
        if (!$shop) {
            return;
        }
        $payload = json_decode((string)$job['payload'], true) ?: [];
        if ($job['type'] === 'install_shop') {
            if (!$ok) {
                DB::update('shops', ['status' => 'error', 'last_error' => mb_substr((string)($result['error'] ?? 'Installazione non riuscita'), 0, 500)], 'id = ?', [$shop['id']]);
                return;
            }
            DB::update('shops', ['last_error' => ''], 'id = ?', [$shop['id']]);
            if ((int)$shop['edge_node_id'] > 0) {
                $node = Nodes::find((int)$shop['node_id']);
                Jobs::queue((int)$shop['edge_node_id'], 'add_edge', [
                    'shop_id' => (int)$shop['id'], 'domain' => $shop['domain'], 'path' => $shop['path'],
                    'hestia_user' => (Nodes::find((int)$shop['edge_node_id']) ?? [])['hestia_user'] ?? '',
                    'upstream' => $node['upstream'], 'origin_host' => $node['tunnel_host'], 'relay_secret' => $node['relay_secret'],
                ], (int)$shop['id']);
                return;
            }
            self::activate((int)$shop['id']);
            return;
        }
        if ($job['type'] === 'add_edge') {
            if ($ok) {
                self::activate((int)$shop['id']);
            } else {
                DB::update('shops', ['status' => 'error', 'last_error' => mb_substr((string)($result['error'] ?? 'Configurazione del frontend non riuscita'), 0, 500)], 'id = ?', [$shop['id']]);
            }
            return;
        }
        if ($job['type'] === 'suspend_shop' && $ok) {
            DB::update('shops', ['status' => 'suspended'], 'id = ?', [$shop['id']]);
        }
        if ($job['type'] === 'unsuspend_shop' && $ok) {
            DB::update('shops', ['status' => 'active'], 'id = ?', [$shop['id']]);
        }
    }

    private static function activate(int $id): void
    {
        DB::update('shops', ['status' => 'active', 'last_error' => ''], 'id = ?', [$id]);
        try {
            Poller::pollShop(self::find($id));
        } catch (\Throwable) {
        }
    }

    public static function password(array $shop): string
    {
        return Secret::open((string)$shop['admin_password']);
    }

    public static function forgetPassword(int $id): void
    {
        DB::update('shops', ['admin_password' => ''], 'id = ?', [$id]);
    }
}
