<?php
declare(strict_types=1);

namespace Alien\Core;

final class Http
{
    /** @var null|callable */
    public static $fake = null;

    public static function request(string $method, string $url, array|string|null $body = null, array $headers = [], int $timeout = 20, ?string $basicAuth = null): array
    {
        if (self::$fake) {
            return (self::$fake)($method, $url, $body, $headers);
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'AlienShop/' . ALIEN_VERSION,
        ];
        if ($basicAuth !== null) {
            $opts[CURLOPT_USERPWD] = $basicAuth;
        }
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : $body;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['status' => 0, 'body' => '', 'json' => null, 'error' => $error];
        }
        return ['status' => $status, 'body' => (string)$raw, 'json' => json_decode((string)$raw, true), 'error' => ''];
    }

    public static function download(string $url, int $maxBytes = 15_000_000, array $headers = []): ?string
    {
        if (self::$fake && is_string($r = (self::$fake)('DOWNLOAD', $url, null, $headers))) {
            return $r;
        }
        for ($hop = 0; $hop <= 3; $hop++) {
            $ip = self::publicIp($url);
            if ($ip === null) {
                return null;
            }
            $p = parse_url($url);
            $port = (int)($p['port'] ?? (($p['scheme'] ?? '') === 'https' ? 443 : 80));
            $ch = curl_init($url);
            $data = '';
            $location = '';
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER => $hop === 0 ? $headers : [],
                CURLOPT_RESOLVE => [$p['host'] . ':' . $port . ':' . $ip],
                CURLOPT_USERAGENT => 'AlienShop/' . ALIEN_VERSION,
                CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$location) {
                    if (stripos($line, 'location:') === 0) {
                        $location = trim(substr($line, 9));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$data, $maxBytes) {
                    $data .= $chunk;
                    return strlen($data) > $maxBytes ? 0 : strlen($chunk);
                },
            ]);
            $ok = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($ok === false) {
                return null;
            }
            if ($status >= 300 && $status < 400 && $location !== '') {
                $url = self::absolute($url, $location);
                continue;
            }
            return $status >= 400 || $data === '' ? null : $data;
        }
        return null;
    }

    private static function absolute(string $base, string $loc): string
    {
        if (preg_match('#^https?://#i', $loc)) {
            return $loc;
        }
        $p = parse_url($base);
        $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($loc, '//')) {
            return $p['scheme'] . ':' . $loc;
        }
        if (str_starts_with($loc, '/')) {
            return $origin . $loc;
        }
        return $origin . rtrim(dirname($p['path'] ?? '/'), '/') . '/' . $loc;
    }

    public static function publicIp(string $url): ?string
    {
        $p = parse_url($url);
        if (!$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        $host = trim($p['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) {
            return null;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }
        return $ips[0];
    }

    public static function isPublicUrl(string $url): bool
    {
        return self::publicIp($url) !== null;
    }
}
