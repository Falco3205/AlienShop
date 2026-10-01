<?php
declare(strict_types=1);

namespace Alien\Import;

use Alien\Core\Money;
use Alien\Services\Catalog;

final class ShopifyImporter
{
    public function import(string $file, bool $downloadImages = true, bool $update = true): Result
    {
        $result = new Result();
        $reader = new CsvReader($file);
        $headers = array_map([CsvReader::class, 'norm'], $reader->headers());
        if (!in_array('handle', $headers, true) || !in_array('title', $headers, true)) {
            $result->error(__('Colonne "Handle" e "Title" non trovate: il file non sembra un export Shopify.'));
            return $result;
        }

        $groups = [];
        foreach ($reader->rows() as $row) {
            if ($row['handle'] !== '') {
                $groups[$row['handle']][] = $row;
            }
        }

        $writer = new Writer($result, $downloadImages, $update);
        foreach ($groups as $handle => $rows) {
            try {
                $this->importGroup((string)$handle, $rows, $writer, $result);
            } catch (\Throwable $e) {
                $result->skipped++;
                $result->error($handle . ': ' . $e->getMessage());
            }
        }
        return $result;
    }

    private function importGroup(string $handle, array $rows, Writer $writer, Result $result): void
    {
        $main = $rows[0];
        foreach ($rows as $r) {
            if (($r['title'] ?? '') !== '') {
                $main = $r;
                break;
            }
        }
        $title = $main['title'] ?? '';
        if ($title === '') {
            $result->skipped++;
            return;
        }

        $optionNames = [];
        for ($i = 1; $i <= 3; $i++) {
            $n = trim($main['option' . $i . ' name'] ?? '');
            if ($n !== '' && !($n === 'Title' && ($main['option' . $i . ' value'] ?? '') === 'Default Title')) {
                $optionNames[$i] = $n;
            }
        }

        $variantRows = [];
        $images = [];
        foreach ($rows as $r) {
            if (($r['image src'] ?? '') !== '') {
                $images[(int)($r['image position'] ?? 0) ?: count($images) + 1000] = $r['image src'];
            }
            if (($r['variant price'] ?? '') === '' && ($r['variant sku'] ?? '') === '' && !($r['option1 value'] ?? '')) {
                continue;
            }
            $options = [];
            foreach ($optionNames as $i => $name) {
                $val = trim($r['option' . $i . ' value'] ?? '');
                if ($val !== '') {
                    $options[$name] = $val;
                }
            }
            $variantRows[] = [
                'options' => $options,
                'price' => Money::parse($r['variant price'] ?? ''),
                'compare' => ($r['variant compare at price'] ?? '') !== '' ? Money::parse($r['variant compare at price']) : null,
                'sku' => $r['variant sku'] ?? '',
                'stock' => (int)($r['variant inventory qty'] ?? 0),
                'tracked' => ($r['variant inventory tracker'] ?? '') !== '',
                'grams' => (int)($r['variant grams'] ?? 0),
            ];
        }
        ksort($images);

        $status = strtolower($main['status'] ?? '');
        $published = strtolower($main['published'] ?? '');
        $first = $variantRows[0] ?? ['price' => 0, 'compare' => null, 'sku' => '', 'stock' => 0, 'tracked' => false, 'grams' => 0];
        $isVariable = $optionNames && count($variantRows) > 0;

        $data = [
            'name' => $title,
            'slug' => $handle,
            'type' => 'simple',
            'status' => ($status === 'draft' || $status === 'archived' || $published === 'false') ? 'draft' : 'active',
            'description' => $main['body (html)'] ?? '',
            'vendor' => $main['vendor'] ?? '',
            'tags' => $main['tags'] ?? '',
            'seo_title' => $main['seo title'] ?? '',
            'seo_description' => $main['seo description'] ?? '',
            'weight' => $first['grams'],
            'price' => $first['price'],
            'compare_price' => (int)$first['compare'],
            'sku' => $first['sku'],
            'manage_stock' => $first['tracked'],
            'stock_qty' => $first['stock'],
        ];

        $category = trim($main['type'] ?? '');
        if ($category === '' && ($main['product category'] ?? '') !== '') {
            $category = trim((string)(explode('>', $main['product category'])[count(explode('>', $main['product category'])) - 1]));
        }
        $data['category_ids'] = $category !== '' ? [Catalog::findOrCreateCategoryPath($category)] : [];

        if ($isVariable) {
            $attrs = [];
            foreach ($optionNames as $name) {
                $values = [];
                foreach ($variantRows as $vr) {
                    if (isset($vr['options'][$name]) && !in_array($vr['options'][$name], $values, true)) {
                        $values[] = $vr['options'][$name];
                    }
                }
                $attrs[] = ['name' => $name, 'values' => array_map(static fn($v) => ['value' => $v, 'price_delta' => 0], $values)];
            }
            $base = min(array_column($variantRows, 'price'));
            if (count($attrs) === 1) {
                $byValue = [];
                foreach ($variantRows as $vr) {
                    $byValue[reset($vr['options'])] = $vr['price'];
                }
                Writer::deltasForSingleAttribute($attrs, $byValue, $base);
            }
            $byKey = [];
            foreach ($variantRows as $vr) {
                $byKey[Catalog::optionsKey($vr['options'])] = $vr;
            }
            $posts = [];
            foreach (Catalog::combinations(array_map(static fn($a) => ['name' => $a['name'], 'values' => array_column($a['values'], 'value')], $attrs)) as $options) {
                $key = Catalog::optionsKey($options);
                $vr = $byKey[$key] ?? null;
                $posts[] = [
                    'key' => $key,
                    'sku' => $vr['sku'] ?? '',
                    'price' => $vr ? Money::input($vr['price']) : '',
                    'compare_price' => $vr && $vr['compare'] ? Money::input($vr['compare']) : '',
                    'stock' => $vr['stock'] ?? 0,
                    'active' => $vr !== null,
                ];
            }
            $data['type'] = 'variable';
            $data['price'] = $base;
            $data['compare_price'] = 0;
            $data['sku'] = '';
            $data['manage_stock'] = (bool)array_filter($variantRows, static fn($v) => $v['tracked']);
            $data['attributes'] = $attrs;
            $data['variants'] = $posts;
        }

        $writer->write($data, array_values($images), 'shopify:' . $handle, $handle);
    }
}
