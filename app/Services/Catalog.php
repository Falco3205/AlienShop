<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Cache;
use Alien\Core\DB;
use Alien\Core\ImageProcessor;
use Alien\Core\Money;
use Alien\Core\Str;

final class Catalog
{
    public static function product(int $id): ?array
    {
        $p = DB::row('SELECT * FROM products WHERE id = ?', [$id]);
        return $p ? self::hydrate($p) : null;
    }

    public static function productBySlug(string $slug, bool $onlyActive = true): ?array
    {
        $p = DB::row('SELECT * FROM products WHERE slug = ?' . ($onlyActive ? " AND status = 'active'" : ''), [$slug]);
        return $p ? self::hydrate($p) : null;
    }

    public static function hydrate(array $p): array
    {
        $id = (int)$p['id'];
        $p['images'] = DB::all('SELECT * FROM product_images WHERE product_id = ? ORDER BY position, id', [$id]);
        $p['categories'] = DB::all('SELECT c.* FROM categories c JOIN product_categories pc ON pc.category_id = c.id WHERE pc.product_id = ? ORDER BY c.position', [$id]);
        $attrs = DB::all('SELECT * FROM product_attributes WHERE product_id = ? ORDER BY position, id', [$id]);
        foreach ($attrs as &$a) {
            $a['values'] = DB::all('SELECT * FROM attribute_values WHERE attribute_id = ? ORDER BY position, id', [$a['id']]);
        }
        $p['attributes'] = $attrs;
        $variants = DB::all('SELECT * FROM variants WHERE product_id = ? ORDER BY position, id', [$id]);
        foreach ($variants as &$v) {
            $v['options'] = json_decode((string)$v['options'], true) ?: [];
            $v['final_price'] = self::variantPrice($p, $attrs, $v);
        }
        $p['variants'] = $variants;
        return $p;
    }

    public static function variantPrice(array $product, array $attrs, array $variant): int
    {
        if ($variant['price'] !== null && $variant['price'] !== '') {
            return (int)$variant['price'];
        }
        $price = (int)$product['price'];
        foreach ($attrs as $a) {
            $chosen = $variant['options'][$a['name']] ?? null;
            foreach ($a['values'] as $val) {
                if ($val['value'] === $chosen) {
                    $price += (int)$val['price_delta'];
                }
            }
        }
        return max(0, $price);
    }

    public static function findVariant(array $product, array $options): ?array
    {
        foreach ($product['variants'] as $v) {
            if ($v['active'] && $v['options'] == $options) {
                return $v;
            }
        }
        return null;
    }

    public static function inStock(array $product, ?array $variant = null): bool
    {
        if (!(int)$product['manage_stock']) {
            return true;
        }
        return $variant ? (int)$variant['stock'] > 0 : (int)$product['stock_qty'] > 0;
    }

    public static function lookup(array $filters): array
    {
        $where = [];
        $params = [];
        $join = '';
        if (!empty($filters['status'])) {
            $where[] = 'p.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $ids = self::categoryWithChildren((int)$filters['category_id']);
            $join = ' JOIN product_categories pc ON pc.product_id = p.id AND pc.category_id IN (' . DB::marks($ids) . ')';
            $params = [...$ids, ...$params];
        }
        if (!empty($filters['q'])) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string)$filters['q']) . '%';
            $where[] = "(p.name LIKE ? ESCAPE '\\' OR p.sku LIKE ? ESCAPE '\\' OR p.tags LIKE ? ESCAPE '\\' OR p.short_description LIKE ? ESCAPE '\\')";
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['min'])) {
            $where[] = 'p.price_max >= ?';
            $params[] = (int)$filters['min'];
        }
        if (!empty($filters['max'])) {
            $where[] = 'p.price_min <= ?';
            $params[] = (int)$filters['max'];
        }
        if (!empty($filters['featured'])) {
            $where[] = 'p.featured = 1';
        }
        if (!empty($filters['in_stock'])) {
            $where[] = 'p.in_stock = 1';
        }
        if (!empty($filters['low_stock'])) {
            $where[] = 'p.manage_stock = 1 AND p.stock_qty <= 3';
        }
        $order = match ($filters['sort'] ?? 'new') {
            'price_asc' => 'p.price_min ASC, p.id DESC',
            'price_desc' => 'p.price_min DESC, p.id DESC',
            'name' => 'p.name ASC',
            default => 'p.id DESC',
        };
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $distinct = $join ? 'DISTINCT ' : '';
        $total = (int)DB::val("SELECT COUNT({$distinct}p.id) FROM products p$join$sqlWhere", $params);
        $per = max(1, (int)($filters['per'] ?? 24));
        $page = max(1, (int)($filters['page'] ?? 1));
        $items = DB::all("SELECT {$distinct}p.* FROM products p$join$sqlWhere ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per), $params);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per' => $per, 'pages' => (int)ceil($total / $per)];
    }

    public static function categoryWithChildren(int $id): array
    {
        $ids = [$id];
        $queue = [$id];
        while ($queue) {
            $children = DB::col('SELECT id FROM categories WHERE parent_id IN (' . DB::marks($queue) . ')', $queue);
            $queue = array_diff($children, $ids);
            $ids = [...$ids, ...$queue];
        }
        return array_map('intval', $ids);
    }

    public static function categories(bool $onlyActive = true): array
    {
        return DB::all('SELECT * FROM categories' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY position, name');
    }

    public static function categoryTree(bool $onlyActive = true, int $parent = 0, ?array $all = null): array
    {
        $all ??= self::categories($onlyActive);
        $out = [];
        foreach ($all as $c) {
            if ((int)$c['parent_id'] === $parent) {
                $c['children'] = self::categoryTree($onlyActive, (int)$c['id'], $all);
                $out[] = $c;
            }
        }
        return $out;
    }

    public static function categoryOptions(?int $exclude = null): array
    {
        $out = [];
        $walk = static function (array $nodes, string $prefix) use (&$walk, &$out, $exclude) {
            foreach ($nodes as $n) {
                if ($exclude && (int)$n['id'] === $exclude) {
                    continue;
                }
                $out[(int)$n['id']] = $prefix . $n['name'];
                $walk($n['children'], $prefix . '— ');
            }
        };
        $walk(self::categoryTree(false), '');
        return $out;
    }

    public static function categoryPath(array $category): array
    {
        $path = [$category];
        $guard = 0;
        while ((int)$category['parent_id'] && $guard++ < 10) {
            $category = DB::row('SELECT * FROM categories WHERE id = ?', [$category['parent_id']]);
            if (!$category) {
                break;
            }
            array_unshift($path, $category);
        }
        return $path;
    }

    public static function categoryUrl(array|string $c): string
    {
        return 'collections/' . (is_array($c) ? $c['slug'] : $c);
    }

    public static function productUrl(array|string $p): string
    {
        return 'products/' . (is_array($p) ? $p['slug'] : $p);
    }

    public static function related(array $product, int $limit = 4): array
    {
        $ids = array_map(static fn($c) => (int)$c['id'], $product['categories']);
        if (!$ids) {
            return DB::all("SELECT * FROM products WHERE status = 'active' AND id <> ? ORDER BY id DESC LIMIT $limit", [$product['id']]);
        }
        return DB::all(
            "SELECT DISTINCT p.* FROM products p JOIN product_categories pc ON pc.product_id = p.id WHERE p.status = 'active' AND p.id <> ? AND pc.category_id IN (" . DB::marks($ids) . ") ORDER BY p.id DESC LIMIT $limit",
            [$product['id'], ...$ids]
        );
    }

    public static function parseAttributeLines(string $text): array
    {
        $values = [];
        foreach (preg_split('/\R+/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $values[] = ['value' => $parts[0], 'price_delta' => isset($parts[1]) && $parts[1] !== '' ? Money::parse($parts[1]) : 0];
        }
        return $values;
    }

    public static function formatAttributeLines(array $values): string
    {
        return implode("\n", array_map(static function ($v) {
            return $v['value'] . ((int)$v['price_delta'] !== 0 ? '|' . ((int)$v['price_delta'] > 0 ? '+' : '') . Money::input((int)$v['price_delta']) : '');
        }, $values));
    }

    public static function optionsKey(array $options): string
    {
        ksort($options);
        return md5(json_encode($options, JSON_UNESCAPED_UNICODE));
    }

    public static function save(array $d, ?int $id = null): int
    {
        return (int)DB::transaction(function () use ($d, $id) {
            $now = now();
            $existing = $id ? DB::row('SELECT * FROM products WHERE id = ?', [$id]) : null;
            $name = trim((string)$d['name']);
            $slugBase = trim((string)($d['slug'] ?? '')) !== '' ? $d['slug'] : $name;
            $slug = Str::uniqueSlug('products', $slugBase, $id);
            $type = ($d['type'] ?? 'simple') === 'variable' ? 'variable' : 'simple';
            $row = [
                'name' => $name,
                'slug' => $slug,
                'type' => $type,
                'status' => ($d['status'] ?? 'active') === 'draft' ? 'draft' : 'active',
                'sku' => trim((string)($d['sku'] ?? '')),
                'short_description' => Str::sanitizeHtml((string)($d['short_description'] ?? '')),
                'description' => Str::sanitizeHtml((string)($d['description'] ?? '')),
                'price' => max(0, (int)($d['price'] ?? 0)),
                'compare_price' => max(0, (int)($d['compare_price'] ?? 0)),
                'manage_stock' => !empty($d['manage_stock']) ? 1 : 0,
                'stock_qty' => (int)($d['stock_qty'] ?? 0),
                'weight' => max(0, (int)($d['weight'] ?? 0)),
                'vendor' => trim((string)($d['vendor'] ?? '')),
                'tags' => trim((string)($d['tags'] ?? '')),
                'seo_title' => trim((string)($d['seo_title'] ?? '')),
                'seo_description' => trim((string)($d['seo_description'] ?? '')),
                'noindex' => !empty($d['noindex']) ? 1 : 0,
                'featured' => !empty($d['featured']) ? 1 : 0,
                'external_id' => (string)($d['external_id'] ?? ($existing['external_id'] ?? '')),
                'updated_at' => $now,
            ];
            if ($id) {
                DB::update('products', $row, 'id = ?', [$id]);
                if ($existing && $existing['slug'] !== $slug) {
                    Redirects::add('products/' . $existing['slug'], 'products/' . $slug);
                }
            } else {
                $row['created_at'] = $now;
                $id = DB::insert('products', $row);
            }
            Redirects::remove('products/' . $slug);

            if (array_key_exists('category_ids', $d)) {
                DB::delete('product_categories', 'product_id = ?', [$id]);
                foreach (array_unique(array_map('intval', (array)$d['category_ids'])) as $cid) {
                    if ($cid > 0) {
                        DB::insert('product_categories', ['product_id' => $id, 'category_id' => $cid]);
                    }
                }
            }

            if (array_key_exists('attributes', $d)) {
                self::saveAttributes($id, $type === 'variable' ? (array)$d['attributes'] : [], (array)($d['variants'] ?? []), (int)$row['price'], $type);
            }
            self::refreshDerived($id);
            return $id;
        });
    }

    private static function saveAttributes(int $productId, array $attributes, array $postedVariants, int $basePrice, string $type): void
    {
        $oldIds = DB::col('SELECT id FROM product_attributes WHERE product_id = ?', [$productId]);
        if ($oldIds) {
            DB::exec('DELETE FROM attribute_values WHERE attribute_id IN (' . DB::marks($oldIds) . ')', $oldIds);
        }
        DB::delete('product_attributes', 'product_id = ?', [$productId]);

        $clean = [];
        foreach ($attributes as $pos => $a) {
            $name = trim((string)($a['name'] ?? ''));
            $values = is_array($a['values'] ?? null) ? $a['values'] : self::parseAttributeLines((string)($a['values'] ?? ''));
            if ($name === '' || !$values) {
                continue;
            }
            $aid = DB::insert('product_attributes', ['product_id' => $productId, 'name' => $name, 'position' => $pos]);
            $seen = [];
            foreach ($values as $vpos => $val) {
                if (isset($seen[$val['value']])) {
                    continue;
                }
                $seen[$val['value']] = true;
                DB::insert('attribute_values', ['attribute_id' => $aid, 'value' => $val['value'], 'price_delta' => (int)$val['price_delta'], 'position' => $vpos]);
            }
            $clean[] = ['name' => $name, 'values' => array_map('strval', array_keys($seen))];
        }

        $existing = [];
        foreach (DB::all('SELECT * FROM variants WHERE product_id = ?', [$productId]) as $v) {
            $existing[$v['options_key']] = $v;
        }
        $posted = [];
        foreach ($postedVariants as $pv) {
            if (isset($pv['key'])) {
                $posted[(string)$pv['key']] = $pv;
            }
        }

        $combos = $type === 'variable' && $clean ? self::combinations($clean) : [];
        $keep = [];
        foreach ($combos as $pos => $options) {
            $key = self::optionsKey($options);
            $keep[] = $key;
            $in = $posted[$key] ?? null;
            $fields = [
                'position' => $pos,
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
            ];
            if ($in !== null) {
                $fields['sku'] = trim((string)($in['sku'] ?? ''));
                $fields['price'] = ($in['price'] ?? '') === '' ? null : Money::parse($in['price']);
                $fields['compare_price'] = ($in['compare_price'] ?? '') === '' ? null : Money::parse($in['compare_price']);
                $fields['stock'] = (int)($in['stock'] ?? 0);
                $fields['active'] = !empty($in['active']) ? 1 : 0;
            }
            if (isset($existing[$key])) {
                DB::update('variants', $fields, 'id = ?', [$existing[$key]['id']]);
            } else {
                DB::insert('variants', $fields + [
                    'product_id' => $productId,
                    'options_key' => $key,
                    'sku' => '',
                    'price' => null,
                    'stock' => 0,
                    'active' => 1,
                ]);
            }
        }
        foreach ($existing as $key => $v) {
            if (!in_array($key, $keep, true)) {
                DB::delete('variants', 'id = ?', [$v['id']]);
            }
        }
    }

    public static function combinations(array $attrs): array
    {
        $result = [[]];
        foreach ($attrs as $a) {
            $next = [];
            foreach ($result as $combo) {
                foreach ($a['values'] as $val) {
                    $next[] = $combo + [$a['name'] => $val];
                }
            }
            $result = $next;
        }
        return $result;
    }

    public static function refreshDerived(int $id): void
    {
        $p = self::product($id);
        if (!$p) {
            return;
        }
        $min = $max = (int)$p['price'];
        $inStock = self::inStock($p);
        if ($p['type'] === 'variable' && $p['variants']) {
            $active = array_values(array_filter($p['variants'], static fn($v) => $v['active']));
            if ($active) {
                $prices = array_map(static fn($v) => $v['final_price'], $active);
                $min = min($prices);
                $max = max($prices);
                $inStock = !(int)$p['manage_stock'] || (bool)array_filter($active, static fn($v) => self::inStock($p, $v));
            }
        }
        $image = $p['images'][0]['path'] ?? null;
        DB::update('products', [
            'price_min' => $min,
            'price_max' => $max,
            'in_stock' => $inStock ? 1 : 0,
            'image' => $image,
        ], 'id = ?', [$id]);
    }

    public static function addImage(int $productId, string $path, string $alt = ''): void
    {
        $pos = (int)DB::val('SELECT COALESCE(MAX(position), -1) + 1 FROM product_images WHERE product_id = ?', [$productId]);
        DB::insert('product_images', ['product_id' => $productId, 'path' => $path, 'alt' => $alt, 'position' => $pos]);
        self::refreshDerived($productId);
    }

    public static function setMainImage(int $productId, int $imageId): void
    {
        $ids = DB::col('SELECT id FROM product_images WHERE product_id = ? ORDER BY position, id', [$productId]);
        if (!in_array((string)$imageId, array_map('strval', $ids), true)) {
            return;
        }
        $ordered = [$imageId, ...array_filter(array_map('intval', $ids), static fn($i) => $i !== $imageId)];
        foreach ($ordered as $pos => $id) {
            DB::update('product_images', ['position' => $pos], 'id = ?', [$id]);
        }
        self::refreshDerived($productId);
    }

    public static function removeImage(int $imageId): void
    {
        $img = DB::row('SELECT * FROM product_images WHERE id = ?', [$imageId]);
        if (!$img) {
            return;
        }
        DB::delete('product_images', 'id = ?', [$imageId]);
        if (!DB::val('SELECT COUNT(*) FROM product_images WHERE path = ?', [$img['path']])) {
            ImageProcessor::delete($img['path']);
        }
        self::refreshDerived((int)$img['product_id']);
    }

    public static function delete(int $id): void
    {
        $p = DB::row('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$p) {
            return;
        }
        foreach (DB::all('SELECT * FROM product_images WHERE product_id = ?', [$id]) as $img) {
            self::removeImage((int)$img['id']);
        }
        $attrIds = DB::col('SELECT id FROM product_attributes WHERE product_id = ?', [$id]);
        if ($attrIds) {
            DB::exec('DELETE FROM attribute_values WHERE attribute_id IN (' . DB::marks($attrIds) . ')', $attrIds);
        }
        DB::delete('product_attributes', 'product_id = ?', [$id]);
        DB::delete('variants', 'product_id = ?', [$id]);
        DB::delete('product_categories', 'product_id = ?', [$id]);
        DB::delete('products', 'id = ?', [$id]);
        Redirects::add('products/' . $p['slug'], 'collections/all');
    }

    public static function saveCategory(array $d, ?int $id = null): int
    {
        $name = trim((string)$d['name']);
        $slug = Str::uniqueSlug('categories', trim((string)($d['slug'] ?? '')) !== '' ? $d['slug'] : $name, $id);
        $parent = (int)($d['parent_id'] ?? 0);
        if ($id && ($parent === $id || in_array($parent, self::categoryWithChildren($id), true))) {
            $parent = 0;
        }
        $row = [
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parent,
            'description' => Str::sanitizeHtml((string)($d['description'] ?? '')),
            'seo_title' => trim((string)($d['seo_title'] ?? '')),
            'seo_description' => trim((string)($d['seo_description'] ?? '')),
            'position' => (int)($d['position'] ?? 0),
            'is_active' => !empty($d['is_active']) ? 1 : 0,
            'show_in_menu' => !empty($d['show_in_menu']) ? 1 : 0,
        ];
        if (array_key_exists('image', $d)) {
            $row['image'] = $d['image'];
        }
        if ($id) {
            $old = DB::row('SELECT slug FROM categories WHERE id = ?', [$id]);
            DB::update('categories', $row, 'id = ?', [$id]);
            if ($old && $old['slug'] !== $slug) {
                Redirects::add('collections/' . $old['slug'], 'collections/' . $slug);
            }
        } else {
            $id = DB::insert('categories', $row);
        }
        Redirects::remove('collections/' . $slug);
        return $id;
    }

    public static function deleteCategory(int $id): void
    {
        $c = DB::row('SELECT * FROM categories WHERE id = ?', [$id]);
        if (!$c) {
            return;
        }
        DB::update('categories', ['parent_id' => (int)$c['parent_id']], 'parent_id = ?', [$id]);
        DB::delete('product_categories', 'category_id = ?', [$id]);
        DB::delete('categories', 'id = ?', [$id]);
        Redirects::add('collections/' . $c['slug'], 'collections/all');
    }

    public static function findOrCreateCategoryPath(string $path): int
    {
        $parent = 0;
        foreach (array_filter(array_map('trim', explode('>', $path))) as $name) {
            $row = DB::row('SELECT id FROM categories WHERE name = ? AND parent_id = ?', [$name, $parent]);
            $parent = $row ? (int)$row['id'] : self::saveCategory(['name' => $name, 'parent_id' => $parent, 'is_active' => 1, 'show_in_menu' => $parent === 0 ? 1 : 0]);
        }
        return $parent;
    }

    public static function flushCache(): void
    {
        Cache::flush();
    }
}
