<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\DB;
use Alien\Core\Secret;

final class Jobs
{
    private const SECRET_KEYS = ['admin_password', 'hub_secret', 'relay_secret'];

    public const TYPES = ['install_shop' => 'Installa negozio', 'add_edge' => 'Pubblica sul frontend', 'suspend_shop' => 'Sospendi negozio', 'unsuspend_shop' => 'Riattiva negozio', 'node_update' => 'Aggiorna agente'];

    public static function queue(int $nodeId, string $type, array $payload, int $shopId = 0): int
    {
        return DB::insert('jobs', ['node_id' => $nodeId, 'shop_id' => $shopId, 'type' => $type, 'payload' => json_encode(self::seal($payload), JSON_UNESCAPED_UNICODE), 'status' => 'queued', 'created_at' => now()]);
    }

    public static function claimFor(int $nodeId): array
    {
        self::expireStuck();
        $out = [];
        foreach (DB::all("SELECT * FROM jobs WHERE node_id = ? AND status = 'queued' ORDER BY id LIMIT 5", [$nodeId]) as $j) {
            if (DB::exec("UPDATE jobs SET status = 'running', started_at = ? WHERE id = ? AND status = 'queued'", [now(), $j['id']]) === 1) {
                $out[] = ['id' => (int)$j['id'], 'type' => $j['type'], 'payload' => self::open(json_decode((string)$j['payload'], true) ?: [])];
                if ($j['shop_id'] && $j['type'] === 'install_shop') {
                    DB::update('shops', ['status' => 'installing'], 'id = ?', [$j['shop_id']]);
                }
            }
        }
        return $out;
    }

    private static function seal(array $payload): array
    {
        foreach (self::SECRET_KEYS as $k) {
            if (isset($payload[$k]) && $payload[$k] !== '') {
                $payload[$k] = Secret::seal((string)$payload[$k]);
            }
        }
        return $payload;
    }

    private static function open(array $payload): array
    {
        foreach (self::SECRET_KEYS as $k) {
            if (isset($payload[$k]) && $payload[$k] !== '') {
                $payload[$k] = Secret::open((string)$payload[$k]);
            }
        }
        return $payload;
    }

    public static function startRunning(int $nodeId, int $shopId, string $type, array $payload): int
    {
        $id = self::queue($nodeId, $type, $payload, $shopId);
        DB::update('jobs', ['status' => 'running', 'started_at' => now()], 'id = ?', [$id]);
        return $id;
    }

    private static function expireStuck(): void
    {
        $cut = date('Y-m-d H:i:s', time() - 1800);
        foreach (DB::all("SELECT id FROM jobs WHERE status = 'running' AND started_at < ?", [$cut]) as $j) {
            self::complete((int)$j['id'], false, [], 'Nessuna risposta dall\'agente entro 30 minuti.');
        }
    }

    public static function complete(int $id, bool $ok, array $result, string $log): void
    {
        $job = DB::row('SELECT * FROM jobs WHERE id = ?', [$id]);
        if (!$job || in_array($job['status'], ['ok', 'error'], true)) {
            return;
        }
        $payload = json_decode((string)$job['payload'], true) ?: [];
        foreach (self::SECRET_KEYS as $k) {
            if (isset($payload[$k])) {
                $payload[$k] = '';
            }
        }
        DB::update('jobs', ['status' => $ok ? 'ok' : 'error', 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'result' => json_encode($result, JSON_UNESCAPED_UNICODE), 'log' => mb_substr($log, 0, 20000), 'finished_at' => now()], 'id = ?', [$id]);
        if ((int)$job['shop_id'] > 0) {
            Shops::onJobDone($job, $ok, $result, $log);
        }
    }

    public static function recent(int $limit = 50, int $shopId = 0): array
    {
        return $shopId
            ? DB::all('SELECT * FROM jobs WHERE shop_id = ? ORDER BY id DESC LIMIT ' . (int)$limit, [$shopId])
            : DB::all('SELECT * FROM jobs ORDER BY id DESC LIMIT ' . (int)$limit);
    }
}
