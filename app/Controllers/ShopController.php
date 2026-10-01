<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\DB;
use Alien\Core\Money;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Str;
use Alien\Services\Analytics;
use Alien\Services\Catalog;
use Alien\Services\Seo;
use Alien\Services\Stats;

final class ShopController extends Controller
{
    public function home(Request $req): Response
    {
        $title = (string)setting('seo_home_title', '');
        Seo::set([
            'title' => $title !== '' ? $title : (string)setting('store_name', ''),
            'description' => (string)setting('seo_home_description', setting('store_description', '')),
            'canonical' => url(),
        ]);
        $categories = array_slice(array_values(array_filter(Catalog::categoryTree(true), static fn($c) => (int)$c['show_in_menu'] === 1)), 0, 6);
        return $this->render('home', [
            'categories' => $categories,
            'featured' => Catalog::lookup(['status' => 'active', 'featured' => 1, 'per' => 8])['items'],
            'latest' => Catalog::lookup(['status' => 'active', 'per' => 8])['items'],
        ]);
    }

    public function collection(Request $req, array $params): Response
    {
        $slug = $params['slug'];
        $category = null;
        if ($slug !== 'all') {
            $category = DB::row('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]);
            if (!$category) {
                return $this->missing($req);
            }
        }
        $filters = ['status' => 'active', 'per' => (int)setting('products_per_page', 24), 'page' => max(1, $req->int('page', 1))];
        $filters['sort'] = in_array($req->str('sort'), ['price_asc', 'price_desc', 'name'], true) ? $req->str('sort') : 'new';
        if ($req->str('min') !== '') {
            $filters['min'] = Money::parse($req->str('min'));
        }
        if ($req->str('max') !== '') {
            $filters['max'] = Money::parse($req->str('max'));
        }
        if ($category) {
            $filters['category_id'] = (int)$category['id'];
        }
        $result = Catalog::lookup($filters);
        if ($filters['page'] > 1 && !$result['items']) {
            return $this->missing($req);
        }

        $base = url('collections/' . $slug);
        $title = $category ? $category['name'] : __('Tutti i prodotti');
        $trail = [[__('Home'), url()]];
        if ($category) {
            foreach (Catalog::categoryPath($category) as $c) {
                $trail[] = [$c['name'], url('collections/' . $c['slug'])];
            }
        } else {
            $trail[] = [$title, $base];
        }
        $filtered = isset($filters['min']) || isset($filters['max']) || $filters['sort'] !== 'new';
        Seo::set([
            'title' => $category && $category['seo_title'] !== '' ? $category['seo_title'] : $title . ($result['page'] > 1 ? ' — ' . __('Pagina %d', $result['page']) : ''),
            'description' => $category ? ($category['seo_description'] !== '' ? $category['seo_description'] : Str::excerpt($category['description'], 160)) : (string)setting('store_description', ''),
            'canonical' => $base . ($result['page'] > 1 ? '?page=' . $result['page'] : ''),
            'prev' => $result['page'] > 1 ? $base . ($result['page'] > 2 ? '?page=' . ($result['page'] - 1) : '') : '',
            'next' => $result['page'] < $result['pages'] ? $base . '?page=' . ($result['page'] + 1) : '',
        ]);
        if ($filtered) {
            Seo::noindex();
        }
        Seo::breadcrumbs($trail);

        return $this->render('collection', [
            'title' => $title,
            'description' => $category ? $category['description'] : '',
            'slug' => $slug,
            'tree' => Catalog::categoryTree(true),
            'result' => $result,
            'trail' => $trail,
            'base' => $base,
        ]);
    }

    public function product(Request $req, array $params): Response
    {
        $product = Catalog::productBySlug($params['slug']);
        if (!$product) {
            return $this->missing($req);
        }
        Seo::forProduct($product);
        Analytics::event('view_item', ['currency' => Money::currency(), 'value' => Analytics::amount((int)$product['price_min']), 'items' => [Analytics::item($product, (int)$product['price_min'])]]);
        return $this->render('product', [
            'product' => $product,
            'trail' => Seo::productTrail($product),
            'related' => Catalog::related($product, 4),
        ]);
    }

    public function search(Request $req): Response
    {
        $q = mb_substr($req->str('q'), 0, 100);
        $result = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        if ($q !== '') {
            $result = Catalog::lookup(['status' => 'active', 'q' => $q, 'per' => 24, 'page' => max(1, $req->int('page', 1))]);
        }
        if ($q !== '' && $result['page'] === 1) {
            Stats::search($q, (int)$result['total']);
        }
        Seo::set(['title' => $q !== '' ? __('Risultati per "%s"', $q) : __('Cerca')]);
        Seo::noindex();
        return $this->noStore($this->render('search', ['q' => $q, 'result' => $result]));
    }

    public function page(Request $req, array $params): Response
    {
        $page = DB::row("SELECT * FROM pages WHERE type = 'page' AND slug = ? AND is_active = 1", [$params['slug']]);
        return $page ? $this->renderPage($page) : $this->missing($req);
    }

    public function blog(Request $req): Response
    {
        $posts = DB::all("SELECT * FROM pages WHERE type = 'post' AND is_active = 1 ORDER BY id DESC LIMIT 50");
        Seo::set(['title' => __('Blog'), 'canonical' => url('blog')]);
        return $this->render('blog', ['posts' => $posts]);
    }

    public function post(Request $req, array $params): Response
    {
        $post = DB::row("SELECT * FROM pages WHERE type = 'post' AND slug = ? AND is_active = 1", [$params['slug']]);
        return $post ? $this->renderPage($post) : $this->missing($req);
    }

    private function renderPage(array $page): Response
    {
        $isPost = $page['type'] === 'post';
        $prefix = $isPost ? 'blog/' : 'pages/';
        Seo::set([
            'title' => $page['seo_title'] !== '' ? $page['seo_title'] : $page['title'],
            'description' => $page['seo_description'] !== '' ? $page['seo_description'] : Str::excerpt($page['excerpt'] ?: $page['content'], 160),
            'canonical' => url($prefix . $page['slug']),
            'type' => $isPost ? 'article' : 'website',
            'image' => $page['image'] ? upload_url($page['image']) : '',
        ]);
        $trail = [[__('Home'), url()], [$page['title'], url($prefix . $page['slug'])]];
        if ($isPost) {
            array_splice($trail, 1, 0, [[__('Blog'), url('blog')]]);
            Seo::schema([
                '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $page['title'],
                'datePublished' => date('c', strtotime($page['created_at'])), 'dateModified' => date('c', strtotime($page['updated_at'])),
                'image' => $page['image'] ? upload_url($page['image']) : null,
            ]);
        }
        Seo::breadcrumbs($trail);
        return $this->render('page', ['page' => $page, 'trail' => $trail]);
    }

    public function legacyCategory(Request $req, array $params): Response
    {
        $segments = explode('/', trim($params['path'], '/'));
        return Response::redirect('collections/' . end($segments), 301);
    }

    public function beacon(Request $req): Response
    {
        $id = $req->int('p');
        if ($id > 0 && DB::val("SELECT 1 FROM products WHERE id = ? AND status = 'active'", [$id])) {
            Stats::bump('views', $id);
        }
        return new Response('', 204);
    }
}
