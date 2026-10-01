<?php
declare(strict_types=1);

namespace Alien\EInvoice;

final class Mime
{
    public static function parse(string $raw): array
    {
        $parts = [];
        self::walk($raw, $parts, 0);
        return $parts;
    }

    private static function walk(string $raw, array &$out, int $depth): void
    {
        if ($depth > 6) {
            return;
        }
        [$headerText, $body] = self::split($raw);
        $h = self::headers($headerText);
        $ctype = $h['content-type'] ?? 'text/plain';
        $type = strtolower(trim(explode(';', $ctype)[0]));
        if (str_starts_with($type, 'multipart/') && preg_match('/boundary="?([^";\s]+)"?/i', $ctype, $m)) {
            foreach (self::multipart($body, $m[1]) as $chunk) {
                self::walk($chunk, $out, $depth + 1);
            }
            return;
        }
        $data = self::decode($body, strtolower(trim($h['content-transfer-encoding'] ?? '7bit')));
        if ($type === 'message/rfc822') {
            self::walk($data, $out, $depth + 1);
            return;
        }
        $name = self::filename($h['content-disposition'] ?? '') ?: self::filename($ctype);
        if ($name !== '') {
            $out[] = ['name' => $name, 'type' => $type, 'data' => $data];
        }
    }

    private static function split(string $raw): array
    {
        $raw = ltrim($raw, "\r\n");
        $pos = strpos($raw, "\r\n\r\n");
        $len = 4;
        $alt = strpos($raw, "\n\n");
        if ($pos === false || ($alt !== false && $alt < $pos)) {
            $pos = $alt;
            $len = 2;
        }
        return $pos === false ? [$raw, ''] : [substr($raw, 0, $pos), substr($raw, $pos + $len)];
    }

    private static function headers(string $text): array
    {
        $text = preg_replace("/\r?\n[ \t]+/", ' ', $text) ?? $text;
        $h = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $h[strtolower(trim($k))] = trim($v);
            }
        }
        return $h;
    }

    private static function multipart(string $body, string $boundary): array
    {
        $chunks = [];
        foreach (preg_split('/\r?\n?--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*\r?\n?/', $body) ?: [] as $i => $chunk) {
            if ($i === 0 || trim($chunk) === '') {
                continue;
            }
            $chunks[] = $chunk;
        }
        return $chunks;
    }

    private static function decode(string $body, string $enc): string
    {
        return match ($enc) {
            'base64' => (string)base64_decode(preg_replace('/\s+/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    public static function filename(string $header): string
    {
        if (preg_match('/filename\*=(?:[^\']*)\'[^\']*\'([^;]+)/i', $header, $m)) {
            return basename(rawurldecode(trim($m[1], '"')));
        }
        if (preg_match('/(?:file)?name="([^"]+)"/i', $header, $m) || preg_match('/(?:file)?name=([^;\s]+)/i', $header, $m)) {
            return basename(self::decodeWords($m[1]));
        }
        return '';
    }

    public static function decodeWords(string $s): string
    {
        return preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', static function ($m) {
            $d = strtoupper($m[2]) === 'B' ? base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            return strtoupper($m[1]) === 'UTF-8' ? (string)$d : (string)@iconv($m[1], 'UTF-8//IGNORE', (string)$d);
        }, $s) ?? $s;
    }
}
