<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\ImageProcessor;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Catalog;

final class CategoriesController extends AdminController
{
    public function index(): Response
    {
        $counts = [];
        foreach (DB::all('SELECT category_id, COUNT(*) AS n FROM product_categories GROUP BY category_id') as $r) {
            $counts[$r['category_id']] = $r['n'];
        }
        return $this->view('categories/index', [
            'title' => __('Categorie'), 'subtitle' => __('Organizza i prodotti in gruppi, anche annidati'),
            'actions' => '<a class="btn" href="' . e(url('admin/categories/new')) . '">+ ' . e(__('Nuova categoria')) . '</a>',
            'options' => Catalog::categoryOptions(),
            'all' => array_column(Catalog::categories(false), null, 'id'),
            'counts' => $counts,
        ], 'categories');
    }

    public function form(Request $req, array $params = []): Response
    {
        $id = isset($params['id']) ? (int)$params['id'] : null;
        $cat = $id ? DB::row('SELECT * FROM categories WHERE id = ?', [$id]) : null;
        if ($id && !$cat) {
            return $this->back('admin/categories', __('Categoria non trovata.'), 'error');
        }
        if ($req->isPost()) {
            $post = $req->post;
            if (trim((string)($post['name'] ?? '')) === '') {
                return $this->back($id ? 'admin/categories/' . $id : 'admin/categories/new', __('Il nome è obbligatorio.'), 'error');
            }
            $data = $post;
            if (!empty($_FILES['image']['name']) && ($path = ImageProcessor::fromUpload($_FILES['image'], 'categories'))) {
                if ($cat && $cat['image']) {
                    ImageProcessor::delete($cat['image']);
                }
                $data['image'] = $path;
            } elseif (!empty($post['remove_image']) && $cat) {
                ImageProcessor::delete($cat['image']);
                $data['image'] = null;
            }
            $saved = Catalog::saveCategory($data, $id);
            return $this->back('admin/categories/' . $saved, __('Categoria salvata.'));
        }
        return $this->view('categories/form', [
            'title' => $cat ? $cat['name'] : __('Nuova categoria'),
            'c' => $cat,
            'options' => Catalog::categoryOptions($id),
        ], 'categories');
    }

    public function delete(Request $req, array $params): Response
    {
        Catalog::deleteCategory((int)$params['id']);
        return $this->back('admin/categories', __('Categoria eliminata.'));
    }
}
