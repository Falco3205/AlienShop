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
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
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
        $host = parse_url(Config::baseUrl(), PHP_URL_HOST);
        $origin = $this->header('Origin') ?: $this->header('Referer');
        if ($origin === '') {
            return true;
        }
        return parse_url($origin, PHP_URL_HOST) === $host;
    }
}
