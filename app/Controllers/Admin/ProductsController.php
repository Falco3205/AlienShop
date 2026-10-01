<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\ImageProcessor;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Catalog;
use Alien\Services\IndexNow;

final class ProductsController extends AdminController
{
    public function index(Request $req): Response
    {
        $filters = [
            'q' => $req->str('q'),
            'status' => in_array($req->str('status'), ['active', 'draft'], true) ? $req->str('status') : '',
            'category_id' => $req->int('category'),
            'page' => $this->page($req),
            'per' => 25,
            'sort' => 'new',
            'low_stock' => $req->str('stock') === 'low',
            'out_of_stock' => $req->str('stock') === 'out',
        ];
        $result = Catalog::lookup($filters);
        return $this->view('products/index', [
            'title' => __('Prodotti'),
            'subtitle' => __('Il tuo catalogo: clicca un prodotto per modificarlo'),
            'actions' => '<a class="btn" href="' . e(url('admin/products/new')) . '">+ ' . e(__('Nuovo prodotto')) . '</a>',
            'result' => $result,
            'filters' => $filters,
            'categories' => Catalog::categoryOptions(),
        ], 'products');
    }

    public function form(Request $req, array $params = []): Response
    {
        $id = isset($params['id']) ? (int)$params['id'] : null;
        $product = $id ? Catalog::product($id) : null;
        if ($id && !$product) {
            return $this->back('admin/products', __('Prodotto non trovato.'), 'error');
        }
        if ($req->isPost()) {
            return $this->save($req, $id);
        }
        return $this->view('products/form', [
            'title' => $product ? $product['name'] : __('Nuovo prodotto'),
            'p' => $product,
            'categories' => Catalog::categoryOptions(),
        ], 'products');
    }

    private function save(Request $req, ?int $id): Response
    {
        $post = $req->post;
        if (trim((string)($post['name'] ?? '')) === '') {
            return $this->back($id ? 'admin/products/' . $id : 'admin/products/new', __('Il nome del prodotto è obbligatorio.'), 'error');
        }
        $attributes = [];
        foreach ((array)($post['attributes'] ?? []) as $a) {
            $attributes[] = ['name' => (string)($a['name'] ?? ''), 'values' => Catalog::parseAttributeLines((string)($a['values'] ?? ''))];
        }
        $variants = [];
        foreach ((array)($post['variants'] ?? []) as $v) {
            $variants[] = [
                'key' => (string)($v['key'] ?? ''),
                'sku' => (string)($v['sku'] ?? ''),
                'price' => (string)($v['price'] ?? ''),
                'compare_price' => (string)($v['compare_price'] ?? ''),
                'stock' => (int)($v['stock'] ?? 0),
                'active' => !empty($v['active']),
            ];
        }
        $data = [
            'name' => $post['name'],
            'slug' => $post['slug'] ?? '',
            'type' => $post['type'] ?? 'simple',
            'status' => $post['status'] ?? 'active',
            'sku' => $post['sku'] ?? '',
            'short_description' => $post['short_description'] ?? '',
            'description' => $post['description'] ?? '',
            'price' => Money::parse($post['price'] ?? 0),
            'compare_price' => Money::parse($post['compare_price'] ?? 0),
            'manage_stock' => !empty($post['manage_stock']),
            'stock_qty' => (int)($post['stock_qty'] ?? 0),
            'weight' => (int)($post['weight'] ?? 0),
            'vendor' => $post['vendor'] ?? '',
            'tags' => $post['tags'] ?? '',
            'seo_title' => $post['seo_title'] ?? '',
            'seo_description' => $post['seo_description'] ?? '',
            'noindex' => !empty($post['noindex']),
            'featured' => !empty($post['featured']),
            'category_ids' => (array)($post['category_ids'] ?? []),
            'attributes' => $attributes,
            'variants' => $variants,
        ];
        $id = Catalog::save($data, $id);

        foreach ((array)($post['remove_image'] ?? []) as $imgId) {
            Catalog::removeImage((int)$imgId);
        }
        if (($main = (int)($post['main_image'] ?? 0)) > 0) {
            Catalog::setMainImage($id, $main);
        }
        $files = $_FILES['images'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $n) {
                $path = ImageProcessor::fromUpload([
                    'name' => $n, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i],
                ], 'products');
                if ($path) {
                    Catalog::addImage($id, $path, (string)$post['name']);
                }
            }
        }
        foreach (preg_split('/\R+/', (string)($post['image_urls'] ?? '')) ?: [] as $url) {
            $url = trim($url);
            if ($url !== '' && ($path = ImageProcessor::fromUrl($url, 'products', (string)$post['name']))) {
                Catalog::addImage($id, $path, (string)$post['name']);
            }
        }
        $p = Catalog::product($id);
        if ($p && $p['status'] === 'active') {
            IndexNow::ping(['products/' . $p['slug']]);
        }
        return $this->back('admin/products/' . $id, __('Prodotto salvato.'));
    }

    public function duplicate(Request $req, array $params): Response
    {
        $p = Catalog::product((int)$params['id']);
        if (!$p) {
            return $this->back('admin/products', __('Prodotto non trovato.'), 'error');
        }
        $variants = array_map(static fn($v) => [
            'key' => $v['options_key'], 'sku' => '', 'price' => $v['price'] === null ? '' : Money::input((int)$v['price']),
            'compare_price' => $v['compare_price'] === null ? '' : Money::input((int)$v['compare_price']), 'stock' => $v['stock'], 'active' => $v['active'],
        ], $p['variants']);
        $id = Catalog::save([
            'name' => $p['name'] . ' (' . __('copia') . ')', 'type' => $p['type'], 'status' => 'draft', 'sku' => '',
            'short_description' => $p['short_description'], 'description' => $p['description'], 'price' => $p['price'],
            'compare_price' => $p['compare_price'], 'manage_stock' => $p['manage_stock'], 'stock_qty' => $p['stock_qty'],
            'weight' => $p['weight'], 'vendor' => $p['vendor'], 'tags' => $p['tags'], 'seo_title' => $p['seo_title'],
            'seo_description' => $p['seo_description'], 'featured' => 0,
            'category_ids' => array_column($p['categories'], 'id'),
            'attributes' => array_map(static fn($a) => ['name' => $a['name'], 'values' => array_map(static fn($v) => ['value' => $v['value'], 'price_delta' => $v['price_delta']], $a['values'])], $p['attributes']),
            'variants' => $variants,
        ]);
        foreach ($p['images'] as $img) {
            Catalog::addImage($id, $img['path'], $img['alt']);
        }
        return $this->back('admin/products/' . $id, __('Prodotto duplicato come bozza.'));
    }

    public function delete(Request $req, array $params): Response
    {
        Catalog::delete((int)$params['id']);
        return $this->back('admin/products', __('Prodotto eliminato.'));
    }

    public function bulk(Request $req): Response
    {
        $ids = array_map('intval', (array)($req->post['ids'] ?? []));
        $action = $req->str('action');
        if (!$ids) {
            return $this->back('admin/products', __('Nessun prodotto selezionato.'), 'error');
        }
        foreach ($ids as $id) {
            match ($action) {
                'delete' => Catalog::delete($id),
                'publish' => DB::update('products', ['status' => 'active', 'updated_at' => now()], 'id = ?', [$id]),
                'draft' => DB::update('products', ['status' => 'draft', 'updated_at' => now()], 'id = ?', [$id]),
                default => null,
            };
        }
        return $this->back('admin/products', __('Operazione completata su %d prodotti.', count($ids)));
    }
}
