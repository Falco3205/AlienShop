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
use Alien\Services\Modules;
use Alien\Services\Reviews;
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
        $attrs = [];
        foreach ((array)($req->query['attr'] ?? []) as $k => $v) {
            if (is_string($k) && is_string($v) && $v !== '' && count($attrs) < 5) {
                $attrs[mb_substr($k, 0, 120)] = mb_substr($v, 0, 190);
            }
        }
        $filters['attrs'] = $attrs;
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
        $filtered = isset($filters['min']) || isset($filters['max']) || $filters['sort'] !== 'new' || $attrs !== [];
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
            'facets' => Catalog::facets($category ? (int)$category['id'] : null),
            'activeAttrs' => $attrs,
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
            'reviews' => Modules::on('reviews') ? Reviews::forProduct((int)$product['id']) : [],
            'trail' => Seo::productTrail($product),
            'related' => Catalog::related($product, 4),
        ]);
    }

    public function review(Request $req, array $params): Response
    {
        $product = Catalog::productBySlug($params['slug']);
        if (!$product || !Modules::on('reviews')) {
            return $this->missing($req);
        }
        $error = trim((string)($req->post['website'] ?? '')) !== '' ? null : Reviews::submit((int)$product['id'], $req->post, $req->ip());
        $message = $error ?? ((string)setting('reviews_auto', '0') === '1' ? __('Grazie! La tua recensione è stata pubblicata.') : __('Grazie! La tua recensione sarà pubblicata dopo la moderazione.'));
        if ($req->isAjax()) {
            return Response::json(['ok' => $error === null, 'message' => $message], $error === null ? 200 : 422);
        }
        return Response::redirect('products/' . $product['slug'] . '?reviewed=' . ($error === null ? 'ok' : 'error') . '#reviews');
    }

    public function notify(Request $req, array $params): Response
    {
        $product = Catalog::productBySlug($params['slug']);
        if (!$product || !Modules::on('stock_alerts')) {
            return $this->missing($req);
        }
        $error = trim((string)($req->post['website'] ?? '')) !== '' ? null : (!\Alien\Core\RateLimit::hit('notify', 10, 3600) ? __('Troppe richieste: riprova più tardi.') : \Alien\Services\StockAlerts::subscribe((int)$product['id'], $req->str('email')));
        $message = $error ?? __('Perfetto! Ti scriveremo appena sarà di nuovo disponibile.');
        if ($req->isAjax()) {
            return Response::json(['ok' => $error === null, 'message' => $message], $error === null ? 200 : 422);
        }
        return $this->noStore($this->render('message', ['heading' => $error === null ? __('Fatto!') : __('Qualcosa non ha funzionato'), 'text' => $message]));
    }

    public function wishlistPage(Request $req): Response
    {
        if (!Modules::on('wishlist')) {
            return $this->missing($req);
        }
        Seo::set(['title' => __('I miei preferiti')]);
        Seo::noindex();
        return $this->render('wishlist');
    }

    public function wishlistCards(Request $req): Response
    {
        $ids = array_slice(array_filter(array_map('intval', explode(',', $req->str('ids')))), 0, 60);
        $html = '';
        if ($ids && Modules::on('wishlist')) {
            foreach (DB::all("SELECT * FROM products WHERE status = 'active' AND id IN (" . DB::marks($ids) . ')', $ids) as $p) {
                $html .= \Alien\Core\View::partial('product-card', ['p' => $p]);
            }
        }
        return Response::json(['html' => $html])->header('Cache-Control', 'private, no-store');
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
