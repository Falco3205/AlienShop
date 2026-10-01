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

    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'sup', 'sub', 'mark', 'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];
    private const DROP_TAGS = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta', 'base', 'svg', 'math', 'noscript', 'template', 'audio', 'video', 'source', 'canvas', 'title', 'head'];
    private const ALLOWED_ATTRS = [
        '*' => ['class', 'title', 'lang', 'dir'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan', 'scope'],
        'ol' => ['start', 'type'],
    ];

    public static function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="as-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('as-root') ?? $doc->documentElement;
        if (!$root) {
            return '';
        }
        self::cleanNode($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function cleanNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }
            if (!$child instanceof \DOMElement) {
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }
            self::cleanNode($child);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $allowed = array_merge(self::ALLOWED_ATTRS['*'], self::ALLOWED_ATTRS[$tag] ?? []);
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowed, true) || !self::attrValueSafe($name, $attr->value)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && strtolower($child->getAttribute('target')) === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private static function attrValueSafe(string $name, string $value): bool
    {
        if (!in_array($name, ['href', 'src'], true)) {
            return !preg_match('/[<>]/', $value);
        }
        $v = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $value) ?? '');
        if ($v === '' || preg_match('#^(https?:|mailto:|tel:|/|\#|\?|\./|\.\./)#', $v)) {
            return true;
        }
        if ($name === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$#D', $v)) {
            return true;
        }
        return !preg_match('#^[a-z][a-z0-9+.\-]*:#', $v);
    }

    public static function csvCell(mixed $v): mixed
    {
        if (!is_string($v) || $v === '' || is_numeric($v)) {
            return $v;
        }
        return in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $v : $v;
    }

    public static function csvRow(array $row): array
    {
        return array_map([self::class, 'csvCell'], $row);
    }
}
