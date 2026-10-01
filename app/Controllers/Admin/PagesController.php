<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\DB;
use Alien\Core\ImageProcessor;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Str;
use Alien\Services\Redirects;

final class PagesController extends AdminController
{
    public function index(): Response
    {
        return $this->view('pages/index', [
            'title' => __('Pagine e blog'),
            'actions' => '<a class="btn sec" href="' . e(url('admin/pages/new?type=post')) . '">+ ' . e(__('Articolo')) . '</a> <a class="btn" href="' . e(url('admin/pages/new')) . '">+ ' . e(__('Pagina')) . '</a>',
            'rows' => DB::all('SELECT * FROM pages ORDER BY type, id DESC'),
        ], 'pages');
    }

    public function form(Request $req, array $params = []): Response
    {
        $id = isset($params['id']) ? (int)$params['id'] : null;
        $p = $id ? DB::row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
        if ($id && !$p) {
            return $this->back('admin/pages', __('Pagina non trovata.'), 'error');
        }
        if ($req->isPost()) {
            $title = $req->str('title');
            if ($title === '') {
                return $this->back($id ? 'admin/pages/' . $id : 'admin/pages/new', __('Il titolo è obbligatorio.'), 'error');
            }
            $type = $req->str('type') === 'post' ? 'post' : 'page';
            $slugBase = Str::slug($req->str('slug') !== '' ? $req->str('slug') : $title);
            $slug = $slugBase;
            for ($i = 2; DB::row('SELECT id FROM pages WHERE type = ? AND slug = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$type, $slug, $id] : [$type, $slug]); $i++) {
                $slug = $slugBase . '-' . $i;
            }
            $row = [
                'type' => $type, 'title' => $title, 'slug' => $slug,
                'content' => Str::sanitizeHtml((string)($req->post['content'] ?? '')),
                'excerpt' => $req->str('excerpt'),
                'seo_title' => $req->str('seo_title'), 'seo_description' => $req->str('seo_description'),
                'show_in_menu' => $req->str('show_in_menu') === '1' ? 1 : 0,
                'show_in_footer' => $req->str('show_in_footer') === '1' ? 1 : 0,
                'is_active' => $req->str('is_active') === '1' ? 1 : 0,
                'updated_at' => now(),
            ];
            if (!empty($_FILES['image']['name']) && ($path = ImageProcessor::fromUpload($_FILES['image'], 'pages'))) {
                $row['image'] = $path;
            }
            $prefix = $type === 'post' ? 'blog/' : 'pages/';
            if ($id) {
                DB::update('pages', $row, 'id = ?', [$id]);
                if ($p['slug'] !== $slug || $p['type'] !== $type) {
                    Redirects::add(($p['type'] === 'post' ? 'blog/' : 'pages/') . $p['slug'], $prefix . $slug);
                }
            } else {
                $id = DB::insert('pages', $row + ['created_at' => now()]);
            }
            Redirects::remove($prefix . $slug);
            return $this->back('admin/pages/' . $id, __('Pagina salvata.'));
        }
        return $this->view('pages/form', ['title' => $p ? $p['title'] : __('Nuova pagina'), 'p' => $p, 'newType' => $req->str('type') === 'post' ? 'post' : 'page'], 'pages');
    }

    public function delete(Request $req, array $params): Response
    {
        $p = DB::row('SELECT * FROM pages WHERE id = ?', [(int)$params['id']]);
        if ($p) {
            DB::delete('pages', 'id = ?', [$p['id']]);
            Redirects::add(($p['type'] === 'post' ? 'blog/' : 'pages/') . $p['slug'], '');
        }
        return $this->back('admin/pages', __('Pagina eliminata.'));
    }
}
