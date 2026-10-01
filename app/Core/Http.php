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

    public static function download(string $url, int $maxBytes = 15_000_000): ?string
    {
        if (!self::isPublicUrl($url)) {
            return null;
        }
        $ch = curl_init($url);
        $data = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'AlienShop/' . ALIEN_VERSION,
            CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$data, $maxBytes) {
                $data .= $chunk;
                return strlen($data) > $maxBytes ? 0 : strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        if ($ok === false || $status >= 400 || $data === '' || !self::isPublicUrl($finalUrl)) {
            return null;
        }
        return $data;
    }

    public static function isPublicUrl(string $url): bool
    {
        $p = parse_url($url);
        if (!$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host'])) {
            return false;
        }
        $host = $p['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }
}
