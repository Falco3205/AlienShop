<?php
declare(strict_types=1);

namespace Alien\Core;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $regex = preg_replace_callback('#\{(\w+)(\*)?\}#', static function ($m) {
            return '(?P<' . $m[1] . '>' . (isset($m[2]) && $m[2] === '*' ? '.+' : '[^/]+') . ')';
        }, $pattern);
        $this->routes[] = [$method, '#^' . $regex . '$#u', $handler];
    }

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(Request $req): ?Response
    {
        $methodAllowed = false;
        foreach ($this->routes as [$method, $regex, $handler]) {
            if (!preg_match($regex, $req->path, $m)) {
                continue;
            }
            if ($method !== $req->method && !($method === 'GET' && $req->method === 'HEAD')) {
                $methodAllowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $result = is_array($handler)
                ? (new $handler[0]())->{$handler[1]}($req, $params)
                : $handler($req, $params);
            return $result instanceof Response ? $result : Response::html((string)$result);
        }
        return $methodAllowed ? new Response('Method Not Allowed', 405) : null;
    }
}
