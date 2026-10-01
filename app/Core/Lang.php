<?php
declare(strict_types=1);

namespace Alien\Core;

final class Lang
{
    private static array $map = [];
    private static string $locale = 'it';

    public static function load(string $locale): void
    {
        $locale = preg_match('/^[a-z]{2}\z/', $locale) ? $locale : 'it';
        self::$locale = $locale;
        $file = ROOT . '/lang/' . $locale . '.php';
        self::$map = $locale !== 'it' && is_file($file) ? (require $file) : [];
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function available(): array
    {
        return ['it' => 'Italiano', 'en' => 'English'];
    }

    public static function translate(string $text): string
    {
        return self::$map[$text] ?? $text;
    }
}
