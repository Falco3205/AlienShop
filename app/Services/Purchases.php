<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\EInvoice\P7m;
use Alien\EInvoice\XmlParser;
use Alien\Core\DB;

final class Purchases
{
    public const CREDIT_TYPES = ['TD04', 'TD08'];

    public static function dir(): string
    {
        $d = ROOT . '/storage/einvoice/in';
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    public static function sign(array $row): int
    {
        return in_array($row['doc_type'], self::CREDIT_TYPES, true) ? -1 : 1;
    }

    public static function ingest(string $bytes, string $source = 'upload', string $filename = ''): array
    {
        $result = ['added' => 0, 'duplicates' => 0, 'errors' => []];
        $xml = P7m::xml($bytes);
        if ($xml === null) {
            $result['errors'][] = __('%s: impossibile leggere il file (firma P7M non valida).', $filename);
            return $result;
        }
        try {
            $docs = XmlParser::parse($xml);
        } catch (\Throwable $e) {
            $result['errors'][] = $filename . ': ' . $e->getMessage();
            return $result;
        }
        $hash = sha1($xml);
        $file = self::dir() . '/' . $hash . '.xml';
        if (!is_file($file)) {
            file_put_contents($file, $xml);
        }
        foreach ($docs as $i => $d) {
            $s = $d['supplier'];
            $due = null;
            foreach ($d['payments'] as $p) {
                if ($p['due'] !== '') {
                    $due = $p['due'];
                    break;
                }
            }
            $dedupe = [$s['vat'], $s['name'], $d['number'], $d['date'], $d['doc_type']];
            if (DB::val('SELECT 1 FROM purchase_invoices WHERE supplier_vat = ? AND supplier_name = ? AND number = ? AND issue_date = ? AND doc_type = ?', $dedupe)) {
                $result['duplicates']++;
                continue;
            }
            DB::insert('purchase_invoices', [
                'supplier_name' => mb_substr($s['name'], 0, 190), 'supplier_vat' => $s['vat'], 'supplier_cf' => $s['cf'], 'country' => $s['country'] ?: 'IT',
                'doc_type' => $d['doc_type'], 'number' => mb_substr($d['number'], 0, 40), 'issue_date' => $d['date'], 'due_date' => $due, 'currency' => $d['currency'],
                'net' => $d['net'], 'vat' => $d['vat'], 'total' => $d['total'], 'deductible' => 100, 'source' => $source,
                'xml_hash' => $hash . ($i ? '-' . $i : ''), 'data' => json_encode($d, json_flags()), 'received_at' => now(),
            ]);
            $result['added']++;
        }
        return $result;
    }

    public static function ingestMany(array $files, string $source = 'upload'): array
    {
        $total = ['added' => 0, 'duplicates' => 0, 'errors' => []];
        foreach ($files as $f) {
            if (preg_match('/\.zip$/i', $f['name']) && class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                if ($zip->open($f['path']) === true) {
                    for ($i = 0; $i < min($zip->numFiles, 500); $i++) {
                        $name = (string)$zip->getNameIndex($i);
                        if (preg_match('/\.(xml|p7m)$/i', $name) && !preg_match('/_(MT|RC|NS|MC|NE|DT|AT)_/i', $name)) {
                            $r = self::ingest((string)$zip->getFromIndex($i), $source, $name);
                            $total['added'] += $r['added'];
                            $total['duplicates'] += $r['duplicates'];
                            array_push($total['errors'], ...$r['errors']);
                        }
                    }
                    $zip->close();
                }
                continue;
            }
            $r = self::ingest((string)file_get_contents($f['path']), $source, $f['name']);
            $total['added'] += $r['added'];
            $total['duplicates'] += $r['duplicates'];
            array_push($total['errors'], ...$r['errors']);
        }
        return $total;
    }
}
