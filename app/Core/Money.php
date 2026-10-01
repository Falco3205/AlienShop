<?php
declare(strict_types=1);

namespace Alien\Core;

final class Money
{
    private const SYMBOLS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'CHF' => 'CHF', 'JPY' => '¥', 'CAD' => 'CA$', 'AUD' => 'A$', 'SEK' => 'kr', 'PLN' => 'zł'];
    private const ZERO_DECIMAL = ['JPY'];

    public static function currency(): string
    {
        return (string)Settings::get('currency', 'EUR');
    }

    public static function decimals(?string $currency = null): int
    {
        return in_array($currency ?? self::currency(), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    public static function factor(?string $currency = null): int
    {
        return self::decimals($currency) === 0 ? 1 : 100;
    }

    public static function symbol(?string $currency = null): string
    {
        $c = $currency ?? self::currency();
        return self::SYMBOLS[$c] ?? $c;
    }

    public static function currencies(): array
    {
        return array_keys(self::SYMBOLS);
    }

    public static function format(int $cents, ?string $currency = null): string
    {
        $dec = self::decimals($currency);
        $value = $cents / self::factor($currency);
        $thousands = Settings::get('locale', 'it') === 'it' ? '.' : ',';
        $point = $thousands === '.' ? ',' : '.';
        $num = number_format($value, $dec, $point, $thousands);
        $symbol = self::symbol($currency);
        return Settings::get('locale', 'it') === 'it' ? $num . ' ' . $symbol : $symbol . $num;
    }

    public static function parse(string|float|int|null $input, ?string $currency = null): int
    {
        if ($input === null || $input === '') {
            return 0;
        }
        $s = trim((string)$input);
        $neg = str_starts_with($s, '-');
        $s = preg_replace('/[^0-9.,]/', '', $s);
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && $lastDot !== false) {
            $dec = max($lastComma, $lastDot);
            $s = str_replace([',', '.'], '', substr($s, 0, $dec)) . '.' . substr($s, $dec + 1);
        } elseif ($lastComma !== false) {
            $s = str_replace(',', '.', $s);
        }
        $cents = (int)round((float)$s * self::factor($currency));
        return $neg ? -$cents : $cents;
    }

    public static function input(int $cents): string
    {
        return number_format($cents / self::factor(), self::decimals(), '.', '');
    }

    public static function stripeAmount(int $cents): int
    {
        return $cents;
    }
}
