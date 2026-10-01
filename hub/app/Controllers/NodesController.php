<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\Config;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Hub\Nodes;

final class NodesController extends Controller
{
    public function index(): Response
    {
        return $this->view('nodes/index', [
            'title' => 'Server',
            'subtitle' => 'VPS backend e frontend collegate all\'hub',
            'actions' => '<a class="btn" href="' . e(url('nodes/new')) . '">+ Collega un server</a>',
            'nodes' => Nodes::all(),
        ], 'nodes');
    }

    public function create(Request $req): Response
    {
        $errors = [];
        $in = ['name' => '', 'role' => 'backend', 'address' => '', 'upstream' => '', 'trusted' => '', 'hestia_user' => ''];
        if ($req->isPost()) {
            foreach (array_keys($in) as $k) {
                $in[$k] = $req->str($k);
            }
            [$id, $token, $errors] = Nodes::create($in['name'], $in['role'], $in['address'], $in['upstream'], $in['hestia_user'], $in['trusted']);
            if ($id) {
                $_SESSION['new_token_' . $id] = $token;
                return $this->back('nodes/' . $id);
            }
        }
        return $this->view('nodes/new', ['title' => 'Collega un server', 'subtitle' => 'Backend per i negozi o frontend che li pubblica', 'in' => $in, 'errors' => $errors, 'roles' => Nodes::ROLES], 'nodes');
    }

    public function show(Request $req, array $params): Response
    {
        $node = Nodes::find((int)$params['id']);
        if (!$node) {
            return $this->back('nodes', 'Server non trovato.', 'error');
        }
        \Alien\Core\Session::start();
        $token = $_SESSION['new_token_' . $node['id']] ?? null;
        unset($_SESSION['new_token_' . $node['id']]);
        return $this->view('nodes/show', [
            'title' => $node['name'],
            'subtitle' => Nodes::ROLES[$node['role']] ?? '',
            'node' => $node,
            'info' => Nodes::info($node),
            'token' => $token,
            'command' => $token ? $this->command($node, $token) : '',
            'shops' => \Alien\Core\DB::all('SELECT id, name, domain, path, status FROM shops WHERE node_id = ? OR edge_node_id = ? ORDER BY name', [$node['id'], $node['id']]),
        ], 'nodes');
    }

    public function token(Request $req, array $params): Response
    {
        $node = Nodes::find((int)$params['id']);
        if (!$node) {
            return $this->back('nodes', 'Server non trovato.', 'error');
        }
        \Alien\Core\Session::start();
        $_SESSION['new_token_' . $node['id']] = Nodes::regenerateToken((int)$node['id']);
        return $this->back('nodes/' . $node['id'], 'Nuovo token generato: aggiorna il server con il comando qui sotto.');
    }

    private function command(array $node, string $token): string
    {
        $repo = (string)Settings::get('shop_repo', 'Falco3205/AlienShop');
        $branch = (string)Settings::get('shop_branch', 'main');
        $flags = $node['role'] === 'edge' ? ' --user ' . $node['hestia_user'] . ' --cloudflare' : '';
        return 'curl -fsSL https://raw.githubusercontent.com/' . $repo . '/' . $branch . '/node/setup.sh -o setup.sh && sudo ALIEN_NODE_TOKEN=' . $token . ' bash setup.sh --hub ' . Config::baseUrl()
            . ' --role ' . $node['role'] . $flags . ' --repo ' . $repo . ' --branch ' . $branch;
    }
}
