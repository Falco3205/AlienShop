<?php
declare(strict_types=1);

namespace Alien\Core;

final class Request
{
    private static ?self $current = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = rawurldecode((string)parse_url($uri, PHP_URL_PATH));
        $base = (string)parse_url(Config::baseUrl(), PHP_URL_PATH);
        $base = rtrim($base, '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim(preg_replace('#/+#', '/', $path), '/');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method']) && in_array(strtoupper($_POST['_method']), ['PUT', 'DELETE'], true)) {
            $method = strtoupper($_POST['_method']);
        }
        return self::$current = new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER);
    }

    public static function current(): self
    {
        return self::$current ??= self::capture();
    }

    public static function detectBaseUrl(): string
    {
        $scheme = is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (str_ends_with($dir, '/public') && !str_starts_with($uri, $dir)) {
            $dir = substr($dir, 0, -7);
        }
        $dir = $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
        return $scheme . '://' . $host . $dir;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string)$v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int)$v : $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isAjax(): bool
    {
        return strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function ip(): string
    {
        $remote = (string)($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = (array)Config::get('app.trusted_proxies', []);
        if (!$trusted || !self::inRanges($remote, $trusted)) {
            return $remote;
        }
        $chain = array_map('trim', explode(',', (string)($this->server['HTTP_X_FORWARDED_FOR'] ?? '')));
        foreach (array_reverse(array_filter($chain)) as $hop) {
            if (filter_var($hop, FILTER_VALIDATE_IP) && !self::inRanges($hop, $trusted)) {
                return $hop;
            }
        }
        $first = reset($chain);
        return $first !== false && filter_var($first, FILTER_VALIDATE_IP) ? $first : $remote;
    }

    public static function inRanges(string $ip, array $ranges): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        foreach ($ranges as $range) {
            $range = trim((string)$range);
            if ($range === '*') {
                return true;
            }
            [$net, $bits] = array_pad(explode('/', $range, 2), 2, null);
            $netBin = @inet_pton((string)$net);
            if ($netBin === false || strlen($netBin) !== strlen($bin)) {
                continue;
            }
            $bits = $bits === null ? strlen($bin) * 8 : max(0, min(strlen($bin) * 8, (int)$bits));
            $bytes = intdiv($bits, 8);
            if (substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
                continue;
            }
            $rest = $bits % 8;
            if ($rest === 0 || (ord($bin[$bytes]) >> (8 - $rest)) === (ord($netBin[$bytes]) >> (8 - $rest))) {
                return true;
            }
        }
        return false;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? '';
    }

    public function body(): string
    {
        return (string)file_get_contents('php://input');
    }

    public function sameOrigin(): bool
    {
        if (strtolower($this->header('Sec-Fetch-Site')) === 'cross-site') {
            return false;
        }
        $origin = $this->header('Origin');
        if ($origin === 'null') {
            return false;
        }
        $origin = $origin ?: $this->header('Referer');
        if ($origin === '') {
            return true;
        }
        return self::sameHost($origin);
    }

    private static function sameHost(string $url): bool
    {
        $host = parse_url(Config::baseUrl(), PHP_URL_HOST);
        $their = parse_url($url, PHP_URL_HOST);
        return is_string($their) && is_string($host) && strtolower($their) === strtolower($host);
    }

    public function backTo(string $default): string
    {
        $ref = $this->header('Referer');
        return $ref !== '' && self::sameHost($ref) && preg_match('#^https?://#i', $ref) ? $ref : $default;
    }
}
