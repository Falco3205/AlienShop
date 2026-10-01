<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Hub\Jobs;
use Hub\Nodes;
use Hub\Poller;
use Hub\ShopClient;
use Hub\Shops;

final class ShopsController extends Controller
{
    public function index(): Response
    {
        return $this->view('shops/index', [
            'title' => 'Negozi',
            'subtitle' => 'I negozi installati per i tuoi clienti',
            'actions' => '<a class="btn" href="' . e(url('shops/new')) . '">+ Nuovo negozio</a>',
            'shops' => Shops::all(),
        ], 'shops');
    }

    public function create(Request $req): Response
    {
        $errors = [];
        $in = ['name' => '', 'domain' => '', 'path' => '', 'admin_email' => (string)Settings::get('default_admin_email', ''), 'node_id' => 0, 'mode' => 'direct', 'edge_node_id' => 0, 'theme' => (string)Settings::get('default_theme', 'aurora'), 'lang' => (string)Settings::get('default_lang', 'it'), 'demo' => 0, 'location' => 'root'];
        if ($req->isPost()) {
            foreach (array_keys($in) as $k) {
                $in[$k] = $req->str($k);
            }
            if ($in['location'] === 'root') {
                $in['path'] = '';
            }
            [$id, $errors] = Shops::create($in);
            if ($id) {
                return $this->back('shops/' . $id, 'Negozio messo in coda: il server lo installa entro un minuto.');
            }
        }
        $themes = array_map('basename', glob(REPO . '/themes/*', GLOB_ONLYDIR) ?: []);
        return $this->view('shops/new', [
            'title' => 'Nuovo negozio',
            'subtitle' => 'Scegli dominio, server e dove installarlo',
            'in' => $in,
            'errors' => $errors,
            'backends' => Nodes::byRole('backend'),
            'edges' => Nodes::byRole('edge'),
            'themes' => array_values(array_filter($themes, static fn($t) => $t !== '_base')),
        ], 'shops');
    }

    public function show(Request $req, array $params): Response
    {
        $shop = Shops::find((int)$params['id']);
        if (!$shop) {
            return $this->back('shops', 'Negozio non trovato.', 'error');
        }
        return $this->view('shops/show', [
            'title' => $shop['name'],
            'subtitle' => Shops::url($shop),
            'shop' => $shop,
            'm' => Shops::metrics($shop),
            'jobs' => Jobs::recent(15, (int)$shop['id']),
            'password' => $shop['admin_password'] !== '' ? Shops::password($shop) : '',
            'node' => Nodes::find((int)$shop['node_id']),
            'edge' => (int)$shop['edge_node_id'] ? Nodes::find((int)$shop['edge_node_id']) : null,
        ], 'shops');
    }

    private function shop(array $params): ?array
    {
        return Shops::find((int)$params['id']);
    }

    public function poll(Request $req, array $params): Response
    {
        $shop = $this->shop($params);
        $ok = $shop && Poller::pollShop($shop);
        return $this->back('shops/' . $params['id'], $ok ? 'Dati aggiornati.' : 'Il negozio non ha risposto.', $ok ? 'success' : 'error');
    }

    public function update(Request $req, array $params): Response
    {
        $shop = $this->shop($params);
        if (!$shop) {
            return $this->back('shops', 'Negozio non trovato.', 'error');
        }
        $r = ShopClient::call($shop, 'POST', '/hub/update', [], 600);
        Poller::pollShop($shop);
        $ok = (bool)$r['ok'];
        return $this->back('shops/' . $shop['id'], $ok ? ((string)($r['message'] ?? '') ?: 'Aggiornato.') : (string)($r['error'] ?? $r['message'] ?? 'Aggiornamento non riuscito.'), $ok ? 'success' : 'error');
    }

    public function open(Request $req, array $params): Response
    {
        $shop = $this->shop($params);
        if (!$shop) {
            return $this->back('shops', 'Negozio non trovato.', 'error');
        }
        $r = ShopClient::call($shop, 'POST', '/hub/sso');
        if (!$r['ok'] || empty($r['token'])) {
            return $this->back('shops/' . $shop['id'], $r['error'] ?? 'Accesso non riuscito.', 'error');
        }
        return Response::redirect(Shops::url($shop) . '/hub/login?t=' . $r['token'], 303);
    }

    public function suspend(Request $req, array $params): Response
    {
        return $this->node($params, 'suspend_shop', 'Sospensione richiesta al server.');
    }

    public function unsuspend(Request $req, array $params): Response
    {
        return $this->node($params, 'unsuspend_shop', 'Riattivazione richiesta al server.');
    }

    private function node(array $params, string $type, string $msg): Response
    {
        $shop = $this->shop($params);
        if (!$shop) {
            return $this->back('shops', 'Negozio non trovato.', 'error');
        }
        Jobs::queue((int)$shop['node_id'], $type, ['shop_id' => (int)$shop['id'], 'domain' => $shop['domain'], 'hestia_user' => $shop['hestia_user']], (int)$shop['id']);
        return $this->back('shops/' . $shop['id'], $msg);
    }

    public function notes(Request $req, array $params): Response
    {
        DB::update('shops', ['notes' => mb_substr((string)($req->post['notes'] ?? ''), 0, 4000), 'name' => $req->str('name') ?: 'Negozio'], 'id = ?', [(int)$params['id']]);
        return $this->back('shops/' . $params['id'], 'Salvato.');
    }

    public function retry(Request $req, array $params): Response
    {
        $shop = $this->shop($params);
        if (!$shop || $shop['status'] !== 'error') {
            return $this->back('shops', 'Niente da ripetere.', 'error');
        }
        $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'xz'), '=');
        DB::update('shops', ['status' => 'pending', 'last_error' => '', 'admin_password' => \Alien\Core\Secret::seal($password)], 'id = ?', [$shop['id']]);
        Jobs::queue((int)$shop['node_id'], 'install_shop', Shops::installPayload(Shops::find((int)$shop['id']), $password), (int)$shop['id']);
        return $this->back('shops/' . $shop['id'], 'Installazione rimessa in coda.');
    }

    public function forgetPassword(Request $req, array $params): Response
    {
        Shops::forgetPassword((int)$params['id']);
        return $this->back('shops/' . $params['id'], 'Password iniziale rimossa dal pannello.');
    }
}
