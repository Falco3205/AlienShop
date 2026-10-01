<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\DB;

final class Nodes
{
    public const ROLES = ['backend' => 'Backend (ospita i negozi)', 'edge' => 'Frontend (pubblica i negozi)'];

    public static function create(string $name, string $role, string $address, string $upstream, string $hestiaUser, string $trusted = '', string $tunnelHost = ''): array
    {
        $errors = [];
        $name = trim($name);
        $upstream = trim($upstream);
        if ($name === '') {
            $errors[] = 'Dai un nome al server.';
        }
        if (!isset(self::ROLES[$role])) {
            $errors[] = 'Ruolo non valido.';
        }
        $tunnelHost = mb_strtolower(trim($tunnelHost));
        if ($role === 'backend' && $tunnelHost !== '') {
            if (!preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/D', $tunnelHost)) {
                $errors[] = 'Hostname del tunnel non valido (es. backend-origin.tuodominio.it).';
            }
            $upstream = 'https://' . $tunnelHost;
        } elseif ($role === 'backend' && !preg_match('#^https?://[\w.\-\[\]:]+$#D', $upstream)) {
            $errors[] = 'Indirizzo del backend raggiungibile dal frontend non valido (es. http://10.0.0.2:80), oppure indica l\'hostname del tunnel.';
        }
        $trusted = trim($trusted);
        foreach (array_filter(array_map('trim', explode(',', $trusted))) as $range) {
            if (!preg_match('#^[0-9a-fA-F:.]+(/\d{1,3})?$#D', $range)) {
                $errors[] = 'Intervallo IP del frontend non valido (es. 10.0.0.0/24, separati da virgola).';
                break;
            }
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $hestiaUser)) {
            $errors[] = 'Utente Hestia non valido.';
        }
        if ($errors) {
            return [null, null, $errors];
        }
        $token = bin2hex(random_bytes(24));
        $id = DB::insert('nodes', [
            'name' => $name, 'role' => $role, 'token_hash' => hash('sha256', $token), 'address' => trim($address),
            'upstream' => $role === 'backend' ? $upstream : '', 'trusted' => $role === 'backend' ? $trusted : '', 'tunnel_host' => $role === 'backend' ? $tunnelHost : '', 'relay_secret' => $role === 'backend' && $tunnelHost !== '' ? bin2hex(random_bytes(16)) : '', 'hestia_user' => $hestiaUser, 'created_at' => now(),
        ]);
        return [$id, $token, []];
    }

    public static function regenerateToken(int $id): string
    {
        $token = bin2hex(random_bytes(24));
        DB::update('nodes', ['token_hash' => hash('sha256', $token)], 'id = ?', [$id]);
        return $token;
    }

    public static function find(int $id): ?array
    {
        return DB::row('SELECT * FROM nodes WHERE id = ?', [$id]);
    }

    public static function authenticate(string $bearer): ?array
    {
        if (!preg_match('/^[0-9a-f]{48}$/D', $bearer)) {
            return null;
        }
        return DB::row('SELECT * FROM nodes WHERE token_hash = ?', [hash('sha256', $bearer)]);
    }

    public static function all(): array
    {
        return DB::all('SELECT * FROM nodes ORDER BY role, name');
    }

    public static function byRole(string $role): array
    {
        return DB::all('SELECT * FROM nodes WHERE role = ? ORDER BY name', [$role]);
    }

    public static function online(array $node): bool
    {
        return !empty($node['last_seen']) && strtotime($node['last_seen']) > time() - 300;
    }

    public static function touch(int $id, array $info): void
    {
        DB::update('nodes', ['last_seen' => now(), 'info' => json_encode($info, JSON_UNESCAPED_UNICODE)], 'id = ?', [$id]);
    }

    public static function info(array $node): array
    {
        return json_decode((string)$node['info'], true) ?: [];
    }
}
