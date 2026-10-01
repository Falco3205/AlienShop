<?php
declare(strict_types=1);

namespace Alien\EInvoice;

use Alien\Core\Settings;

final class InvoiceData
{
    public static function seller(): array
    {
        $g = static fn(string $k) => trim((string)Settings::get('einv_' . $k, ''));
        return [
            'type' => $g('type') ?: 'company',
            'name' => $g('name'), 'vat' => $g('vat'), 'cf' => $g('cf'), 'regime' => $g('regime') ?: 'RF01',
            'address' => $g('address'), 'cap' => $g('cap'), 'city' => $g('city'), 'prov' => strtoupper($g('prov')), 'country' => 'IT',
            'email' => $g('email'), 'phone' => $g('phone'), 'rea_office' => $g('rea_office'), 'rea_number' => $g('rea_number'),
        ];
    }

    public static function sellerErrors(array $s): array
    {
        $e = [];
        if ($s['name'] === '') {
            $e[] = __('Manca la denominazione.');
        }
        if (!Fiscal::vatValid(preg_replace('/\D/', '', $s['vat']) ?? '')) {
            $e[] = __('La partita IVA non è valida.');
        }
        if ($s['cf'] !== '' && !Fiscal::cfValid($s['cf'])) {
            $e[] = __('Il codice fiscale non è valido.');
        }
        foreach (['address' => __('indirizzo'), 'cap' => 'CAP', 'city' => __('comune')] as $k => $label) {
            if ($s[$k] === '') {
                $e[] = __('Manca: %s.', $label);
            }
        }
        if (!Fiscal::provinceValid($s['prov'])) {
            $e[] = __('La sigla della provincia non è valida (2 lettere).');
        }
        if ($s['email'] !== '' && (!filter_var($s['email'], FILTER_VALIDATE_EMAIL) || strlen($s['email']) < 7)) {
            $e[] = __('L\'email non è valida (minimo 7 caratteri).');
        }
        if ($s['phone'] !== '' && !preg_match('/^[+0-9 ]{5,12}$/D', $s['phone'])) {
            $e[] = __('Il telefono deve avere da 5 a 12 caratteri (solo numeri).');
        }
        return $e;
    }

    public static function customer(array $order): array
    {
        $inv = (array)($order['billing']['invoice'] ?? []);
        $addr = $order['billing'] ?: $order['shipping_address'];
        $type = ($inv['type'] ?? '') === 'company' ? 'company' : 'private';
        return [
            'type' => $type,
            'name' => trim((string)($inv['name'] ?? ($addr['name'] ?? ''))),
            'vat' => preg_replace('/\s+/', '', (string)($inv['vat'] ?? '')) ?? '',
            'cf' => strtoupper(preg_replace('/\s+/', '', (string)($inv['cf'] ?? '')) ?? ''),
            'sdi' => strtoupper(trim((string)($inv['sdi'] ?? ''))),
            'pec' => trim((string)($inv['pec'] ?? '')),
            'address' => (string)($addr['address'] ?? ''), 'cap' => (string)($addr['zip'] ?? ''), 'city' => (string)($addr['city'] ?? ''),
            'prov' => strtoupper((string)($addr['state'] ?? '')), 'country' => strtoupper((string)($addr['country'] ?? 'IT')),
        ];
    }

    public static function customerErrors(array $c): array
    {
        $e = [];
        if ($c['name'] === '') {
            $e[] = __('Manca il nome o la ragione sociale del cliente.');
        }
        if ($c['address'] === '' || $c['city'] === '') {
            $e[] = __('Manca l\'indirizzo del cliente.');
        }
        if ($c['country'] === 'IT') {
            if ($c['vat'] !== '' && !Fiscal::vatValid(preg_replace('/\D/', '', $c['vat']) ?? '')) {
                $e[] = __('La partita IVA del cliente non è valida.');
            }
            if ($c['cf'] !== '' && !Fiscal::cfValid($c['cf'])) {
                $e[] = __('Il codice fiscale del cliente non è valido.');
            }
            if ($c['vat'] === '' && $c['cf'] === '') {
                $e[] = __('Servono la partita IVA o il codice fiscale del cliente.');
            }
            if (!Fiscal::provinceValid($c['prov'])) {
                $e[] = __('Manca la provincia del cliente (2 lettere).');
            }
            if ($c['sdi'] !== '' && !Fiscal::sdiCodeValid($c['sdi'])) {
                $e[] = __('Il codice destinatario deve avere 7 caratteri.');
            }
            if ($c['pec'] !== '' && (!filter_var($c['pec'], FILTER_VALIDATE_EMAIL) || strlen($c['pec']) < 7)) {
                $e[] = __('La PEC del cliente non è valida.');
            }
        }
        return $e;
    }

    public static function fromOrder(array $order, string $type, string $number, string $date, string $progressivo, ?array $related = null): array
    {
        $net = (int)$order['total'] - (int)$order['tax'];
        $rate = isset($order['tax_rate']) && $order['tax_rate'] !== null && (int)$order['tax_rate'] > 0
            ? (int)$order['tax_rate'] / 100
            : ((int)$order['tax'] > 0 && $net > 0 ? round((int)$order['tax'] / $net * 100, 2) : 0.0);
        $beforeTax = (int)$order['subtotal'] - (int)$order['discount'] + (int)$order['shipping'];
        $inclusive = (int)$order['tax'] > 0 && $beforeTax === (int)$order['total'];
        $factor = $rate > 0 && $inclusive ? 1 + $rate / 100 : 1.0;
        $dec = $order['currency'] === 'JPY' ? 1 : 100;

        $lines = [];
        $sum = 0.0;
        $push = static function (string $desc, float $qty, float $gross) use (&$lines, &$sum, $factor, $dec) {
            $lineNet = round($gross / $dec / $factor, 2);
            $lines[] = ['desc' => $desc, 'qty' => $qty, 'unit' => $qty != 0 ? $lineNet / $qty : $lineNet, 'total' => $lineNet];
            $sum += $lineNet;
        };
        foreach ($order['items'] as $it) {
            $push($it['name'] . ($it['variant_label'] !== '' ? ' (' . $it['variant_label'] . ')' : ''), (float)$it['qty'], (float)$it['total']);
        }
        if ((int)$order['shipping'] > 0) {
            $push(__('Spedizione') . ($order['shipping_method'] !== '' ? ' — ' . $order['shipping_method'] : ''), 1.0, (float)$order['shipping']);
        }
        if ((int)$order['discount'] > 0) {
            $push(__('Sconto') . ($order['coupon_code'] !== '' ? ' ' . $order['coupon_code'] : ''), 1.0, -(float)$order['discount']);
        }
        $netTotal = round($net / $dec, 2);
        $rounding = round($netTotal - $sum, 2);
        $total = round((int)$order['total'] / $dec, 2);

        $paymentDue = substr((string)$order['created_at'], 0, 10);
        return [
            'type' => $type, 'number' => $number, 'date' => $date, 'progressivo' => $progressivo, 'currency' => $order['currency'],
            'seller' => self::seller(), 'customer' => self::customer($order),
            'lines' => $lines, 'rate' => $rate, 'nature' => (string)Settings::get('einv_nature', 'N2.2'),
            'net' => $netTotal, 'vat' => round((int)$order['tax'] / $dec, 2), 'total' => $total, 'rounding' => $rounding,
            'payment' => ['mode' => Fiscal::paymentMode((string)$order['payment_method']), 'due' => $paymentDue, 'iban' => (string)Settings::get('einv_iban', '')],
            'related' => $related,
            'causale' => $type === 'TD04' ? __('Nota di credito relativa all\'ordine %s', $order['number']) : __('Ordine online %s', $order['number']),
            'bollo' => $rate == 0 && $total > 77.47 && (string)Settings::get('einv_bollo', '0') === '1',
        ];
    }
}
