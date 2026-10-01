<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\DB;
use Alien\Core\Money;

final class Accounting
{
    public static function period(string $type, string $value): array
    {
        $year = (int)date('Y');
        if ($type === 'year' && preg_match('/^\d{4}$/D', $value)) {
            return [$value . '-01-01', $value . '-12-31', $value];
        }
        if ($type === 'quarter' && preg_match('/^(\d{4})-Q([1-4])$/D', $value, $m)) {
            $startMonth = ((int)$m[2] - 1) * 3 + 1;
            return [sprintf('%s-%02d-01', $m[1], $startMonth), date('Y-m-t', strtotime(sprintf('%s-%02d-01', $m[1], $startMonth + 2))), $m[1] . ' T' . $m[2]];
        }
        if (!preg_match('/^\d{4}-\d{2}$/D', $value)) {
            $value = date('Y-m');
        }
        return [$value . '-01', date('Y-m-t', strtotime($value . '-01')), date('m/Y', strtotime($value . '-01'))];
    }

    private static function sign(string $docType): int
    {
        return $docType === 'TD04' ? -1 : 1;
    }

    private static function rateOf(int $net, int $vat): float
    {
        if ($net <= 0 || $vat <= 0) {
            return 0.0;
        }
        $r = $vat / $net * 100;
        foreach ([4, 5, 10, 22] as $std) {
            if (abs($r - $std) < 0.6) {
                return (float)$std;
            }
        }
        return round($r, 2);
    }

    public static function sales(string $from, string $to): array
    {
        $rows = [];
        $placeholders = DB::marks(EInvoices::VALID);
        foreach (DB::all("SELECT * FROM einvoices WHERE issue_date >= ? AND issue_date <= ? AND status IN ($placeholders) ORDER BY issue_date, id", [$from, $to, ...EInvoices::VALID]) as $e) {
            $s = self::sign($e['doc_type']);
            $rows[] = [
                'kind' => 'invoice', 'date' => $e['issue_date'], 'number' => $e['number'], 'type' => $e['doc_type'], 'party' => $e['customer_name'],
                'vat_id' => $e['customer_vat'] ?: $e['customer_cf'], 'net' => $s * (int)$e['net'], 'vat' => $s * (int)$e['vat'], 'total' => $s * (int)$e['total'],
                'rate' => self::rateOf((int)$e['net'], (int)$e['vat']), 'currency' => $e['currency'], 'ref' => (int)$e['order_id'],
            ];
        }
        $orders = DB::all("SELECT o.* FROM orders o WHERE o.payment_status = 'paid' AND o.status NOT IN ('cancelled','refunded') AND SUBSTR(o.created_at, 1, 10) >= ? AND SUBSTR(o.created_at, 1, 10) <= ?
            AND NOT EXISTS (SELECT 1 FROM einvoices e WHERE e.order_id = o.id AND e.doc_type = 'TD01' AND e.status IN ($placeholders)) ORDER BY o.created_at", [$from, $to, ...EInvoices::VALID]);
        foreach ($orders as $o) {
            $net = (int)$o['total'] - (int)$o['tax'];
            $rows[] = [
                'kind' => 'receipt', 'date' => substr($o['created_at'], 0, 10), 'number' => $o['number'], 'type' => 'COR', 'party' => __('Corrispettivo e-commerce'),
                'vat_id' => '', 'net' => $net, 'vat' => (int)$o['tax'], 'total' => (int)$o['total'],
                'rate' => $o['tax_rate'] !== null ? (int)$o['tax_rate'] / 100 : self::rateOf($net, (int)$o['tax']), 'currency' => $o['currency'], 'ref' => (int)$o['id'],
            ];
        }
        usort($rows, static fn($a, $b) => strcmp($a['date'] . $a['number'], $b['date'] . $b['number']));
        return $rows;
    }

    public static function purchases(string $from, string $to): array
    {
        $rows = [];
        foreach (DB::all('SELECT * FROM purchase_invoices WHERE issue_date >= ? AND issue_date <= ? ORDER BY issue_date, id', [$from, $to]) as $p) {
            $s = Purchases::sign($p);
            $vat = $s * (int)$p['vat'];
            $ded = (int)round($vat * (int)$p['deductible'] / 100);
            $rows[] = [
                'kind' => 'invoice', 'id' => (int)$p['id'], 'date' => $p['issue_date'], 'number' => $p['number'], 'type' => $p['doc_type'], 'party' => $p['supplier_name'],
                'vat_id' => $p['supplier_vat'] ?: $p['supplier_cf'], 'net' => $s * (int)$p['net'], 'vat' => $vat, 'vat_deductible' => $ded, 'total' => $s * (int)$p['total'],
                'rate' => self::rateOf((int)$p['net'], (int)$p['vat']), 'category' => $p['category'], 'paid_at' => $p['paid_at'], 'due' => $p['due_date'] ?: $p['issue_date'],
            ];
        }
        foreach (DB::all('SELECT * FROM expenses WHERE day >= ? AND day <= ? ORDER BY day, id', [$from, $to]) as $x) {
            $ded = (int)round((int)$x['vat'] * (int)$x['deductible'] / 100);
            $rows[] = [
                'kind' => 'expense', 'id' => (int)$x['id'], 'date' => $x['day'], 'number' => '', 'type' => 'SPE', 'party' => $x['supplier'] ?: $x['description'], 'vat_id' => '',
                'net' => (int)$x['net'], 'vat' => (int)$x['vat'], 'vat_deductible' => $ded, 'total' => (int)$x['total'], 'rate' => self::rateOf((int)$x['net'], (int)$x['vat']),
                'category' => $x['category'], 'paid_at' => $x['paid'] ? $x['day'] : null, 'due' => $x['day'],
            ];
        }
        usort($rows, static fn($a, $b) => strcmp($a['date'], $b['date']));
        return $rows;
    }

    public static function byRate(array $rows, string $vatKey = 'vat'): array
    {
        $out = [];
        foreach ($rows as $r) {
            $k = number_format((float)$r['rate'], 2, '.', '');
            $out[$k] ??= ['rate' => (float)$r['rate'], 'net' => 0, 'vat' => 0];
            $out[$k]['net'] += $r['net'];
            $out[$k]['vat'] += $r[$vatKey];
        }
        ksort($out, SORT_NUMERIC);
        return array_values($out);
    }

    public static function overview(string $from, string $to): array
    {
        $sales = self::sales($from, $to);
        $purchases = self::purchases($from, $to);
        $salesNet = array_sum(array_column($sales, 'net'));
        $salesVat = array_sum(array_column($sales, 'vat'));
        $purchNet = array_sum(array_column($purchases, 'net'));
        $purchVatDed = array_sum(array_column($purchases, 'vat_deductible'));
        $purchVat = array_sum(array_column($purchases, 'vat'));
        $undeductible = $purchVat - $purchVatDed;
        $costs = $purchNet + $undeductible;
        return [
            'sales' => $sales, 'purchases' => $purchases,
            'sales_net' => $salesNet, 'sales_vat' => $salesVat, 'sales_total' => array_sum(array_column($sales, 'total')),
            'invoiced' => count(array_filter($sales, static fn($r) => $r['kind'] === 'invoice')), 'receipts' => count(array_filter($sales, static fn($r) => $r['kind'] === 'receipt')),
            'purchases_net' => $purchNet, 'purchases_vat' => $purchVat, 'purchases_vat_deductible' => $purchVatDed, 'purchases_total' => array_sum(array_column($purchases, 'total')),
            'costs' => $costs, 'margin' => $salesNet - $costs, 'vat_balance' => $salesVat - $purchVatDed,
            'sales_by_rate' => self::byRate($sales), 'purchases_by_rate' => self::byRate($purchases, 'vat_deductible'),
        ];
    }

    public static function monthly(int $year): array
    {
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $from = sprintf('%d-%02d-01', $year, $m);
            $to = date('Y-m-t', strtotime($from));
            $sales = self::sales($from, $to);
            $purchases = self::purchases($from, $to);
            $out[] = ['month' => $m, 'sales' => array_sum(array_column($sales, 'net')), 'costs' => array_sum(array_column($purchases, 'net')) + array_sum(array_column($purchases, 'vat')) - array_sum(array_column($purchases, 'vat_deductible'))];
        }
        return $out;
    }

    public static function deadlines(): array
    {
        $payable = [];
        foreach (DB::all('SELECT * FROM purchase_invoices WHERE paid_at IS NULL ORDER BY COALESCE(due_date, issue_date)') as $p) {
            $payable[] = ['id' => (int)$p['id'], 'party' => $p['supplier_name'], 'number' => $p['number'], 'due' => $p['due_date'] ?: $p['issue_date'], 'amount' => Purchases::sign($p) * (int)$p['total'], 'currency' => $p['currency']];
        }
        $receivable = DB::all("SELECT id, number, email, total, currency, created_at, payment_method FROM orders WHERE payment_status = 'unpaid' AND status NOT IN ('cancelled','refunded') ORDER BY created_at");
        return ['payable' => $payable, 'receivable' => $receivable, 'payable_total' => array_sum(array_column($payable, 'amount')), 'receivable_total' => array_sum(array_column($receivable, 'total'))];
    }

    public static function suppliers(string $from, string $to, int $limit = 10): array
    {
        $by = [];
        foreach (self::purchases($from, $to) as $r) {
            $k = $r['vat_id'] . '|' . $r['party'];
            $by[$k] ??= ['name' => $r['party'], 'vat_id' => $r['vat_id'], 'count' => 0, 'net' => 0, 'total' => 0];
            $by[$k]['count']++;
            $by[$k]['net'] += $r['net'];
            $by[$k]['total'] += $r['total'];
        }
        usort($by, static fn($a, $b) => $b['total'] <=> $a['total']);
        return array_slice($by, 0, $limit);
    }

    public static function csv(string $kind, string $from, string $to): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        $dec = static fn(int $c) => Money::input($c);
        if ($kind === 'iva') {
            $o = self::overview($from, $to);
            fputcsv($fh, ['registro', 'aliquota', 'imponibile', 'iva'], ',', '"', '\\');
            foreach ($o['sales_by_rate'] as $r) {
                fputcsv($fh, ['vendite', $r['rate'], $dec($r['net']), $dec($r['vat'])], ',', '"', '\\');
            }
            foreach ($o['purchases_by_rate'] as $r) {
                fputcsv($fh, ['acquisti_detraibile', $r['rate'], $dec($r['net']), $dec($r['vat'])], ',', '"', '\\');
            }
            fputcsv($fh, ['saldo_iva', '', '', $dec($o['vat_balance'])], ',', '"', '\\');
        } elseif ($kind === 'acquisti') {
            fputcsv($fh, ['data', 'tipo', 'numero', 'fornitore', 'partita_iva', 'categoria', 'imponibile', 'iva', 'iva_detraibile', 'totale', 'pagata_il'], ',', '"', '\\');
            foreach (self::purchases($from, $to) as $r) {
                fputcsv($fh, \Alien\Core\Str::csvRow([$r['date'], $r['type'], $r['number'], $r['party'], $r['vat_id'], $r['category'], $dec($r['net']), $dec($r['vat']), $dec($r['vat_deductible']), $dec($r['total']), (string)$r['paid_at']]), ',', '"', '\\');
            }
        } else {
            fputcsv($fh, ['data', 'tipo', 'numero', 'cliente', 'partita_iva_cf', 'aliquota', 'imponibile', 'iva', 'totale'], ',', '"', '\\');
            foreach (self::sales($from, $to) as $r) {
                fputcsv($fh, \Alien\Core\Str::csvRow([$r['date'], $r['type'], $r['number'], $r['party'], $r['vat_id'], $r['rate'], $dec($r['net']), $dec($r['vat']), $dec($r['total'])]), ',', '"', '\\');
            }
        }
        rewind($fh);
        return (string)stream_get_contents($fh);
    }

    public static function xmlArchive(string $from, string $to): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }
        $path = ROOT . '/storage/tmp';
        @mkdir($path, 0750, true);
        $file = $path . '/xml-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (DB::all('SELECT * FROM einvoices WHERE issue_date >= ? AND issue_date <= ?', [$from, $to]) as $e) {
            if (is_file(EInvoices::xmlPath($e))) {
                $zip->addFile(EInvoices::xmlPath($e), 'emesse/' . basename($e['file_name']));
            }
        }
        foreach (DB::all('SELECT xml_hash, number, supplier_name FROM purchase_invoices WHERE issue_date >= ? AND issue_date <= ?', [$from, $to]) as $p) {
            $f = Purchases::dir() . '/' . preg_replace('/-\d+$/', '', $p['xml_hash']) . '.xml';
            if (is_file($f)) {
                $zip->addFile($f, 'ricevute/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $p['supplier_name'] . '_' . $p['number']) . '.xml');
            }
        }
        $zip->close();
        return $file;
    }
}
