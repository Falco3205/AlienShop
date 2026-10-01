<?php
declare(strict_types=1);

namespace Alien\Import;

use Alien\Core\DB;
use Alien\Core\ImageProcessor;
use Alien\Services\Catalog;

final class Writer
{
    public function __construct(
        private readonly Result $result,
        private readonly bool $downloadImages = true,
        private readonly bool $updateExisting = true,
    ) {
    }

    public function findExisting(string $externalId, string $sku, string $slug = ''): ?array
    {
        if ($externalId !== '' && ($p = DB::row('SELECT * FROM products WHERE external_id = ?', [$externalId]))) {
            return $p;
        }
        if ($sku !== '' && ($p = DB::row('SELECT * FROM products WHERE sku = ?', [$sku]))) {
            return $p;
        }
        if ($slug !== '' && ($p = DB::row('SELECT * FROM products WHERE slug = ?', [$slug]))) {
            return $p;
        }
        return null;
    }

    public function write(array $data, array $images, string $externalId = '', string $slugHint = ''): ?int
    {
        $existing = $this->findExisting($externalId, (string)($data['sku'] ?? ''), $slugHint);
        if ($existing && !$this->updateExisting) {
            $this->result->skipped++;
            return null;
        }
        if ($externalId !== '') {
            $data['external_id'] = $externalId;
        }
        if ($slugHint !== '' && empty($data['slug']) && !$existing) {
            $data['slug'] = $slugHint;
        }
        $id = Catalog::save($data, $existing ? (int)$existing['id'] : null);
        $existing ? $this->result->updated++ : $this->result->created++;

        $hasImages = $existing && (int)DB::val('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$id]) > 0;
        if (!$hasImages) {
            foreach (array_slice(array_unique($images), 0, 12) as $i => $url) {
                $path = $this->downloadImages ? ImageProcessor::fromUrl($url, 'products', (string)$data['name']) : null;
                if ($path) {
                    Catalog::addImage($id, $path, (string)$data['name']);
                    $this->result->images++;
                } elseif ($this->downloadImages) {
                    $this->result->error(__('Immagine non scaricabile: %s', $url));
                }
            }
        }
        return $id;
    }

    public static function deltasForSingleAttribute(array &$attributes, array $variantPrices, int $base): void
    {
        if (count($attributes) !== 1) {
            return;
        }
        foreach ($attributes[0]['values'] as &$val) {
            $price = $variantPrices[$val['value']] ?? null;
            if ($price !== null) {
                $val['price_delta'] = $price - $base;
            }
        }
    }
}
