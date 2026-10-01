<?php
declare(strict_types=1);

namespace Hub;

use Alien\Core\Http;
use Alien\Core\HubSign;

final class ShopClient
{
    public static function call(array $shop, string $method, string $path, array $body = [], int $timeout = 15): array
    {
        if (str_starts_with(Shops::url($shop), 'https://') && \Alien\Core\Http::publicIp(Shops::url($shop)) === null) {
            return ['ok' => false, 'error' => 'Il dominio non punta a un indirizzo pubblico (DNS non ancora configurato?).'];
        }
        $secret = Shops::secret($shop);
        $raw = $body ? json_encode($body) : '';
        $headers = HubSign::headers($secret, $method, $path, $raw);
        $headers[] = 'Content-Type: application/json';
        $res = Http::request($method, Shops::url($shop) . $path, $raw === '' ? null : $raw, $headers, $timeout);
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
