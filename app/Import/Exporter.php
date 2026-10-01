<?php
declare(strict_types=1);

namespace Alien\Import;

use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Services\Catalog;

final class Exporter
{
    private static function csv(array $header, iterable $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header, ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($fh, array_map(static fn($h) => $r[$h] ?? '', $header), ',', '"', '\\');
        }
        rewind($fh);
        $out = (string)stream_get_contents($fh);
        fclose($fh);
        return $out;
    }

    private static function products(): \Generator
    {
        foreach (DB::col('SELECT id FROM products ORDER BY id') as $id) {
            yield Catalog::product((int)$id);
        }
    }

    private static function dec(int $cents): string
    {
        return Money::input($cents);
    }

    private static function categoryPath(array $c): string
    {
        return implode(' > ', array_map(static fn($x) => $x['name'], Catalog::categoryPath($c)));
    }

    public static function woo(): string
    {
        $header = ['ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Short description', 'Description', 'In stock?', 'Stock', 'Regular price', 'Sale price', 'Categories', 'Tags', 'Images', 'Parent', 'Weight (kg)'];
        for ($i = 1; $i <= 3; $i++) {
            array_push($header, "Attribute $i name", "Attribute $i value(s)", "Attribute $i visible", "Attribute $i global");
        }
        $rows = [];
        foreach (self::products() as $p) {
            $images = implode(', ', array_map(static fn($i) => upload_url($i['path']), $p['images']));
            $isVar = $p['type'] === 'variable' && $p['variants'];
            $row = [
                'ID' => $p['id'], 'Type' => $isVar ? 'variable' : 'simple', 'SKU' => $p['sku'], 'Name' => $p['name'],
                'Published' => $p['status'] === 'active' ? 1 : 0, 'Is featured?' => $p['featured'] ? 1 : 0,
                'Short description' => $p['short_description'], 'Description' => $p['description'],
                'In stock?' => $p['in_stock'] ? 1 : 0, 'Stock' => $p['manage_stock'] && !$isVar ? $p['stock_qty'] : '',
                'Regular price' => $isVar ? '' : self::dec((int)($p['compare_price'] > $p['price'] ? $p['compare_price'] : $p['price'])),
                'Sale price' => !$isVar && $p['compare_price'] > $p['price'] ? self::dec((int)$p['price']) : '',
                'Categories' => implode(', ', array_map([self::class, 'categoryPath'], $p['categories'])),
                'Tags' => $p['tags'], 'Images' => $images, 'Weight (kg)' => $p['weight'] ? $p['weight'] / 1000 : '',
            ];
            foreach (array_slice($p['attributes'], 0, 3) as $i => $a) {
                $n = $i + 1;
                $row["Attribute $n name"] = $a['name'];
                $row["Attribute $n value(s)"] = implode(', ', array_column($a['values'], 'value'));
                $row["Attribute $n visible"] = 1;
                $row["Attribute $n global"] = 0;
            }
            $rows[] = $row;
            if ($isVar) {
                foreach ($p['variants'] as $v) {
                    $vr = [
                        'ID' => '', 'Type' => 'variation', 'SKU' => $v['sku'], 'Name' => $p['name'] . ' - ' . implode(', ', $v['options']),
                        'Published' => $v['active'] ? 1 : 0, 'In stock?' => Catalog::inStock($p, $v) ? 1 : 0,
                        'Stock' => $p['manage_stock'] ? $v['stock'] : '',
                        'Regular price' => self::dec((int)($v['compare_price'] ?: $v['final_price'])),
                        'Sale price' => $v['compare_price'] ? self::dec((int)$v['final_price']) : '',
                        'Parent' => 'id:' . $p['id'],
                    ];
                    foreach (array_slice($p['attributes'], 0, 3) as $i => $a) {
                        $n = $i + 1;
                        $vr["Attribute $n name"] = $a['name'];
                        $vr["Attribute $n value(s)"] = $v['options'][$a['name']] ?? '';
                        $vr["Attribute $n visible"] = '';
                        $vr["Attribute $n global"] = 0;
                    }
                    $rows[] = $vr;
                }
            }
        }
        return self::csv($header, $rows);
    }

    public static function shopify(): string
    {
        $header = ['Handle', 'Title', 'Body (HTML)', 'Vendor', 'Product Category', 'Type', 'Tags', 'Published',
            'Option1 Name', 'Option1 Value', 'Option2 Name', 'Option2 Value', 'Option3 Name', 'Option3 Value',
            'Variant SKU', 'Variant Grams', 'Variant Inventory Tracker', 'Variant Inventory Qty', 'Variant Inventory Policy',
            'Variant Price', 'Variant Compare At Price', 'Image Src', 'Image Position', 'Image Alt Text', 'SEO Title', 'SEO Description', 'Status'];
        $rows = [];
        foreach (self::products() as $p) {
            $isVar = $p['type'] === 'variable' && $p['variants'];
            $attrs = array_slice($p['attributes'], 0, 3);
            $variants = $isVar ? $p['variants'] : [null];
            foreach ($variants as $idx => $v) {
                $r = ['Handle' => $p['slug']];
                if ($idx === 0) {
                    $r += [
                        'Title' => $p['name'], 'Body (HTML)' => $p['description'], 'Vendor' => $p['vendor'], 'Product Category' => '',
                        'Type' => $p['categories'] ? $p['categories'][0]['name'] : '', 'Tags' => $p['tags'],
                        'Published' => $p['status'] === 'active' ? 'true' : 'false', 'SEO Title' => $p['seo_title'],
                        'SEO Description' => $p['seo_description'], 'Status' => $p['status'] === 'active' ? 'active' : 'draft',
                    ];
                }
                if ($isVar) {
                    foreach ($attrs as $i => $a) {
                        $n = $i + 1;
                        if ($idx === 0) {
                            $r["Option$n Name"] = $a['name'];
                        }
                        $r["Option$n Value"] = $v['options'][$a['name']] ?? '';
                    }
                } elseif ($idx === 0) {
                    $r['Option1 Name'] = 'Title';
                    $r['Option1 Value'] = 'Default Title';
                }
                $price = $v ? (int)$v['final_price'] : (int)$p['price'];
                $compare = $v ? (int)($v['compare_price'] ?? 0) : (int)$p['compare_price'];
                $r += [
                    'Variant SKU' => $v ? $v['sku'] : $p['sku'], 'Variant Grams' => $p['weight'],
                    'Variant Inventory Tracker' => $p['manage_stock'] ? 'shopify' : '',
                    'Variant Inventory Qty' => $p['manage_stock'] ? ($v ? $v['stock'] : $p['stock_qty']) : '',
                    'Variant Inventory Policy' => 'deny', 'Variant Price' => self::dec($price),
                    'Variant Compare At Price' => $compare > $price ? self::dec($compare) : '',
                ];
                if (isset($p['images'][$idx])) {
                    $r['Image Src'] = upload_url($p['images'][$idx]['path']);
                    $r['Image Position'] = $idx + 1;
                    $r['Image Alt Text'] = $p['images'][$idx]['alt'];
                }
                $rows[] = $r;
            }
            for ($i = count($variants); $i < count($p['images']); $i++) {
                $rows[] = ['Handle' => $p['slug'], 'Image Src' => upload_url($p['images'][$i]['path']), 'Image Position' => $i + 1, 'Image Alt Text' => $p['images'][$i]['alt']];
            }
        }
        return self::csv($header, $rows);
    }
}
