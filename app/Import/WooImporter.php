<?php
declare(strict_types=1);

namespace Alien\Import;

use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Core\Str;
use Alien\Services\Catalog;

final class WooImporter
{
    private const ALIASES = [
        'id' => ['id'],
        'type' => ['type', 'tipo'],
        'sku' => ['sku', 'codice articolo'],
        'name' => ['name', 'nome'],
        'published' => ['published', 'pubblicato'],
        'featured' => ['is featured?', 'in evidenza?'],
        'short' => ['short description', 'descrizione breve'],
        'description' => ['description', 'descrizione'],
        'instock' => ['in stock?', 'disponibile?', 'in magazzino?'],
        'stock' => ['stock', 'magazzino', 'quantità'],
        'regular' => ['regular price', 'prezzo di listino', 'prezzo regolare'],
        'sale' => ['sale price', 'prezzo in offerta'],
        'categories' => ['categories', 'categorie'],
        'tags' => ['tags', 'tag'],
        'images' => ['images', 'immagini'],
        'parent' => ['parent', 'genitore', 'superiore'],
        'weight' => ['weight (kg)', 'peso (kg)'],
        'seo_title' => ['meta: _yoast_wpseo_title', 'meta: rank_math_title'],
        'seo_desc' => ['meta: _yoast_wpseo_metadesc', 'meta: rank_math_description'],
        'brand' => ['brands', 'marchi'],
    ];

    private array $map = [];

    public function import(string $file, bool $downloadImages = true, bool $update = true): Result
    {
        $result = new Result();
        $reader = new CsvReader($file);
        $headers = array_map([CsvReader::class, 'norm'], $reader->headers());
        foreach (self::ALIASES as $key => $aliases) {
            foreach ($aliases as $a) {
                if (in_array($a, $headers, true)) {
                    $this->map[$key] = $a;
                    break;
                }
            }
        }
        if (!isset($this->map['name'])) {
            $result->error(__('Colonna "Name" non trovata: il file non sembra un export WooCommerce.'));
            return $result;
        }

        $writer = new Writer($result, $downloadImages, $update);
        $parents = [];
        $variations = [];
        foreach ($reader->rows() as $row) {
            if (strtolower($this->v($row, 'type')) === 'variation') {
                $variations[] = $row;
            } else {
                $parents[] = $row;
            }
        }

        $byRef = [];
        foreach ($parents as $row) {
            $ref = $this->v($row, 'id');
            if ($ref !== '') {
                $byRef['id:' . $ref] = $row['__line'];
            }
            if ($this->v($row, 'sku') !== '') {
                $byRef[$this->v($row, 'sku')] = $row['__line'];
            }
        }
        $children = [];
        foreach ($variations as $row) {
            $parentRef = $this->v($row, 'parent');
            if ($parentRef === '' || !isset($byRef[$parentRef])) {
                $result->skipped++;
                $result->error(__('Riga %d: variazione senza prodotto padre (%s).', $row['__line'], $parentRef));
                continue;
            }
            $children[$byRef[$parentRef]][] = $row;
        }

        foreach ($parents as $row) {
            try {
                $this->importProduct($row, $children[$row['__line']] ?? [], $writer, $result);
            } catch (\Throwable $e) {
                $result->skipped++;
                $result->error(__('Riga %d: %s', $row['__line'], $e->getMessage()));
            }
        }
        return $result;
    }

    private function v(array $row, string $key): string
    {
        return isset($this->map[$key]) ? ($row[$this->map[$key]] ?? '') : '';
    }

    private function importProduct(array $row, array $vars, Writer $writer, Result $result): void
    {
        $type = strtolower($this->v($row, 'type'));
        $name = $this->v($row, 'name');
        if ($name === '') {
            $result->skipped++;
            return;
        }
        $regular = $this->v($row, 'regular');
        $sale = $this->v($row, 'sale');
        $price = $sale !== '' ? Money::parse($sale) : Money::parse($regular);
        $compare = $sale !== '' && $regular !== '' ? Money::parse($regular) : 0;
        $published = $this->v($row, 'published');
        $stockRaw = $this->v($row, 'stock');
        $instock = $this->v($row, 'instock');
        $manage = $stockRaw !== '' || $instock === '0';

        $data = [
            'name' => $name,
            'type' => 'simple',
            'status' => $published === '1' || $published === '' ? 'active' : 'draft',
            'sku' => $this->v($row, 'sku'),
            'short_description' => $this->v($row, 'short'),
            'description' => $this->v($row, 'description'),
            'price' => $price,
            'compare_price' => $compare,
            'manage_stock' => $manage,
            'stock_qty' => $stockRaw !== '' ? (int)$stockRaw : 0,
            'weight' => (int)round((float)str_replace(',', '.', $this->v($row, 'weight') ?: '0') * 1000),
            'vendor' => $this->v($row, 'brand'),
            'tags' => $this->v($row, 'tags'),
            'seo_title' => $this->v($row, 'seo_title'),
            'seo_description' => $this->v($row, 'seo_desc'),
            'featured' => $this->v($row, 'featured') === '1',
        ];

        $catIds = [];
        foreach (preg_split('/\s*,\s*(?=[^>]*(?:>|$))/', $this->v($row, 'categories')) ?: [] as $path) {
            if (trim($path) !== '') {
                $catIds[] = Catalog::findOrCreateCategoryPath($path);
            }
        }
        $data['category_ids'] = $catIds;

        if ($type === 'variable' || $vars) {
            $attrs = $this->attributesFrom($row, $vars);
            if ($attrs && $vars) {
                $data['type'] = 'variable';
                $variantRows = [];
                $minPrice = PHP_INT_MAX;
                foreach ($vars as $vr) {
                    $options = [];
                    foreach ($attrs as $a) {
                        $val = $this->attrValue($vr, $a['index']);
                        if ($val !== '') {
                            $options[$a['name']] = $val;
                        }
                    }
                    if (count($options) !== count($attrs)) {
                        $result->error(__('Riga %d: variazione con attributi incompleti, ignorata.', $vr['__line']));
                        continue;
                    }
                    $vSale = $this->v($vr, 'sale');
                    $vReg = $this->v($vr, 'regular');
                    $vPrice = $vSale !== '' ? Money::parse($vSale) : ($vReg !== '' ? Money::parse($vReg) : $price);
                    $minPrice = min($minPrice, $vPrice);
                    $variantRows[] = [
                        'options' => $options,
                        'price' => $vPrice,
                        'compare' => $vSale !== '' && $vReg !== '' ? Money::parse($vReg) : null,
                        'sku' => $this->v($vr, 'sku'),
                        'stock' => (int)$this->v($vr, 'stock'),
                        'active' => $this->v($vr, 'published') !== '0',
                        'instock' => $this->v($vr, 'instock'),
                        'manage' => $this->v($vr, 'stock') !== '',
                    ];
                }
                if (!$variantRows) {
                    $data['type'] = 'simple';
                } else {
                    $base = $minPrice === PHP_INT_MAX ? $price : $minPrice;
                    $data['price'] = $base;
                    $data['compare_price'] = 0;
                    $data['manage_stock'] = (bool)array_filter($variantRows, static fn($v) => $v['manage'] || $v['instock'] === '0');
                    $single = [];
                    $attrList = array_map(static fn($a) => ['name' => $a['name'], 'values' => array_map(static fn($x) => ['value' => $x, 'price_delta' => 0], $a['values'])], $attrs);
                    if (count($attrList) === 1) {
                        foreach ($variantRows as $vr) {
                            $single[reset($vr['options'])] = $vr['price'];
                        }
                        Writer::deltasForSingleAttribute($attrList, $single, $base);
                    }
                    $data['attributes'] = $attrList;
                    $data['variants'] = $this->variantPosts($attrs, $variantRows, $attrList);
                }
            }
        }

        $images = array_values(array_filter(array_map('trim', explode(',', $this->v($row, 'images')))));
        $ref = $this->v($row, 'id');
        $writer->write($data, $images, $ref !== '' ? 'woo:' . $ref : '', Str::slug($name));
    }

    private function attrValue(array $row, int $i): string
    {
        $k = 'attribute ' . $i . ' value(s)';
        $alt = 'valore/i attributo ' . $i;
        return trim($row[$k] ?? $row[$alt] ?? '');
    }

    private function attributesFrom(array $parent, array $vars): array
    {
        $attrs = [];
        for ($i = 1; $i <= 10; $i++) {
            $name = trim($parent['attribute ' . $i . ' name'] ?? $parent['nome attributo ' . $i] ?? '');
            if ($name === '') {
                continue;
            }
            $values = array_values(array_filter(array_map('trim', explode(',', $this->attrValue($parent, $i))), 'strlen'));
            foreach ($vars as $vr) {
                $val = $this->attrValue($vr, $i);
                if ($val !== '' && !in_array($val, $values, true)) {
                    $values[] = $val;
                }
            }
            if ($values) {
                $attrs[] = ['index' => $i, 'name' => $name, 'values' => $values];
            }
        }
        return $attrs;
    }

    private function variantPosts(array $attrs, array $variantRows, array $attrList): array
    {
        $byKey = [];
        foreach ($variantRows as $vr) {
            $byKey[Catalog::optionsKey($vr['options'])] = $vr;
        }
        $posts = [];
        foreach (Catalog::combinations(array_map(static fn($a) => ['name' => $a['name'], 'values' => $a['values']], $attrs)) as $options) {
            $key = Catalog::optionsKey($options);
            $vr = $byKey[$key] ?? null;
            $posts[] = [
                'key' => $key,
                'sku' => $vr['sku'] ?? '',
                'price' => $vr ? Money::input($vr['price']) : '',
                'compare_price' => $vr && $vr['compare'] ? Money::input($vr['compare']) : '',
                'stock' => $vr ? ($vr['instock'] === '0' && !$vr['manage'] ? 0 : $vr['stock']) : 0,
                'active' => $vr !== null && $vr['active'],
            ];
        }
        return $posts;
    }
}
