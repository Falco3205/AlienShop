<?php
declare(strict_types=1);

namespace Alien\Core;

final class Str
{
    public static function slug(string $text, int $max = 90): string
    {
        $text = trim(strip_tags($text));
        if (class_exists(\Transliterator::class)) {
            $t = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if ($t) {
                $text = $t->transliterate($text) ?: $text;
            }
        } else {
            $text = strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string)$text, '-');
        if (strlen($text) > $max) {
            $text = trim(substr($text, 0, $max), '-');
        }
        return $text !== '' ? $text : 'item';
    }

    public static function uniqueSlug(string $table, string $base, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $slug = $base = self::slug($base);
        $i = 2;
        while (true) {
            $sql = "SELECT id FROM $table WHERE $column = ?" . ($ignoreId ? ' AND id <> ?' : '');
            $params = $ignoreId ? [$slug, $ignoreId] : [$slug];
            if (!DB::row($sql, $params)) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    public static function excerpt(?string $html, int $len = 160): string
    {
        $text = trim((string)preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)preg_replace('/<[^>]+>/', ' $0', (string)$html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (mb_strlen($text) <= $len) {
            return $text;
        }
        $cut = mb_substr($text, 0, $len - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space !== false && $space > $len * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:-") . '…';
    }

    public static function randomToken(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*/?>#is', '', $html) ?? '';
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';
        $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*(javascript|data|vbscript):[^"\']*\2#i', '$1=$2#$2', $html) ?? '';
        return $html;
    }
}
