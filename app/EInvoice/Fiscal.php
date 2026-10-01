<?php
declare(strict_types=1);

namespace Alien\EInvoice;

final class Fiscal
{
    public const REGIMES = ['RF01' => 'Ordinario', 'RF19' => 'Forfettario', 'RF18' => 'Altro', 'RF02' => 'Contribuenti minimi', 'RF04' => 'Agricoltura e attività connesse'];

    public const DOC_TYPES = [
        'TD01' => 'Fattura', 'TD02' => 'Acconto/anticipo su fattura', 'TD03' => 'Acconto/anticipo su parcella', 'TD04' => 'Nota di credito',
        'TD05' => 'Nota di debito', 'TD06' => 'Parcella', 'TD16' => 'Integrazione fattura reverse charge interno', 'TD17' => 'Integrazione/autofattura acquisto servizi estero',
        'TD18' => 'Integrazione acquisto beni intracomunitari', 'TD19' => 'Integrazione/autofattura acquisto beni art.17', 'TD20' => 'Autofattura / regolarizzazione',
        'TD24' => 'Fattura differita', 'TD25' => 'Fattura differita (triangolazione)', 'TD26' => 'Cessione beni ammortizzabili', 'TD27' => 'Autofattura per autoconsumo',
        'TD28' => 'Acquisti da San Marino con IVA',
    ];

    public const NATURES = [
        'N1' => 'Escluse ex art. 15', 'N2.1' => 'Non soggette ex artt. da 7 a 7-septies', 'N2.2' => 'Non soggette – altri casi (es. forfettari)',
        'N3.1' => 'Non imponibili – esportazioni', 'N3.2' => 'Non imponibili – cessioni intracomunitarie', 'N4' => 'Esenti', 'N5' => 'Regime del margine',
        'N6.1' => 'Inversione contabile – rottami', 'N7' => 'IVA assolta in altro stato UE',
    ];

    public const PAYMENT_MODES = [
        'MP01' => 'Contanti', 'MP02' => 'Assegno', 'MP05' => 'Bonifico', 'MP08' => 'Carta di pagamento', 'MP12' => 'RIBA',
        'MP16' => 'Domiciliazione bancaria', 'MP18' => 'Bollettino postale', 'MP19' => 'SEPA Direct Debit', 'MP23' => 'PagoPA',
    ];

    public static function vatValid(string $vat): bool
    {
        if (!preg_match('/^\d{11}$/', $vat)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $d = (int)$vat[$i];
            if ($i % 2 === 1) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }
        return (10 - $sum % 10) % 10 === (int)$vat[10];
    }

    public static function cfValid(string $cf): bool
    {
        $cf = strtoupper(trim($cf));
        if (preg_match('/^\d{11}$/', $cf)) {
            return self::vatValid($cf);
        }
        if (!preg_match('/^[A-Z]{6}\d{2}[A-EHLMPR-T]\d{2}[A-Z]\d{3}[A-Z]$/', $cf)) {
            return false;
        }
        static $odd = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23];
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $c = $cf[$i];
            $v = ctype_digit($c) ? (int)$c : ord($c) - 65;
            $sum += $i % 2 === 0 ? $odd[$v] : $v;
        }
        return chr(65 + $sum % 26) === $cf[15];
    }

    public static function sdiCodeValid(string $code): bool
    {
        return (bool)preg_match('/^[A-Z0-9]{7}$/', strtoupper($code));
    }

    public static function provinceValid(string $p): bool
    {
        return (bool)preg_match('/^[A-Z]{2}$/', strtoupper($p));
    }

    public static function paymentMode(string $method): string
    {
        return match ($method) {
            'bank' => 'MP05',
            'cod' => 'MP01',
            default => 'MP08',
        };
    }

    public static function amount(float|int $v, int $dec = 2): string
    {
        return number_format((float)$v, $dec, '.', '');
    }

    public static function xml(string $s): string
    {
        return htmlspecialchars(self::basicLatin($s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function basicLatin(string $s): string
    {
        $s = strtr($s, ['—' => '-', '–' => '-', '‘' => "'", '’' => "'", '“' => '"', '”' => '"', '…' => '...', '€' => 'EUR', '•' => '-', '×' => 'x', '№' => 'N.', "\t" => ' ', "\n" => ' ', "\r" => ' ']);
        $out = '';
        $trans = class_exists(\Transliterator::class) ? \Transliterator::create('Any-Latin; Latin-ASCII') : null;
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $cp = mb_ord($ch);
            if (($cp >= 0x20 && $cp <= 0x7E) || ($cp >= 0xA0 && $cp <= 0xFF)) {
                $out .= $ch;
            } elseif ($trans && ($t = $trans->transliterate($ch)) !== false && preg_match('/^[\x20-\x7E]+$/', $t)) {
                $out .= $t;
            } else {
                $out .= '?';
            }
        }
        return $out;
    }

    public static function latin(string $s, int $max): string
    {
        $s = trim(preg_replace('/\s+/', ' ', self::basicLatin($s)) ?? '');
        return mb_substr($s, 0, $max);
    }
}
