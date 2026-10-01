<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\Http;
use Alien\Core\HubSign;

final class ShopClient
{
    public static function target(array $shop, string $path): array
    {
        $backend = Nodes::find((int)$shop['node_id']);
        if ($backend && Nodes::tailnet($backend)) {
            return [rtrim((string)$backend['upstream'], '/') . ($shop['path'] !== '' ? '/' . $shop['path'] : '') . $path, ['Host: ' . $shop['domain'], 'X-Forwarded-Proto: https'], true];
        }
        return [Shops::url($shop) . $path, [], false];
    }

    public static function call(array $shop, string $method, string $path, array $body = [], int $timeout = 15): array
    {
        [$url, $extra, $private] = self::target($shop, $path);
        if (!$private && str_starts_with($url, 'https://') && Http::publicIp($url) === null) {
            return ['ok' => false, 'error' => 'Il dominio non punta a un indirizzo pubblico (DNS non ancora configurato?).'];
        }
        $secret = Shops::secret($shop);
        $raw = $body ? json_encode($body) : '';
        $headers = [...HubSign::headers($secret, $method, $path, $raw), ...$extra, 'Content-Type: application/json'];
        $res = Http::request($method, $url, $raw === '' ? null : $raw, $headers, $timeout);
        if ($res['status'] === 0) {
            return ['ok' => false, 'error' => 'Negozio non raggiungibile: ' . ($res['error'] ?: 'nessuna risposta')];
        }
        if ($res['status'] === 403) {
            return ['ok' => false, 'error' => 'Il negozio rifiuta il collegamento (secret non corrispondente o hub non configurato).'];
        }
        if ($res['status'] !== 200 || !is_array($res['json'])) {
            return ['ok' => false, 'error' => 'Risposta inattesa dal negozio (HTTP ' . $res['status'] . ').'];
        }
        return $res['json'] + ['ok' => true];
    }
}
