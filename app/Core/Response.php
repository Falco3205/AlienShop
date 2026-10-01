<?php
declare(strict_types=1);

namespace Alien\Core;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(json_encode($data, json_flags()), $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function text(string $body, string $type = 'text/plain', int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => $type . '; charset=utf-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        if (!preg_match('#^https?://#', $to)) {
            $to = url($to);
        }
        return new self('', $status, ['Location' => $to]);
    }

    public static function notFound(string $body = ''): self
    {
        return new self($body, 404, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function download(string $body, string $filename, string $type = 'text/csv'): self
    {
        return new self($body, 200, [
            'Content-Type' => $type . '; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function header(string $k, string $v): self
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $this->body;
    }
}
