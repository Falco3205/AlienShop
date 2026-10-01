<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\DB;
use Alien\Core\RateLimit;
use Alien\Core\Request;
use Alien\Core\Response;
use Hub\Jobs;
use Hub\Nodes;
use Hub\Shops;

final class ApiController
{
    private function node(Request $req): ?array
    {
        if (RateLimit::blocked('nodeauth', 30, 600)) {
            return null;
        }
        $h = $req->header('Authorization');
        $node = str_starts_with($h, 'Bearer ') ? Nodes::authenticate(trim(substr($h, 7))) : null;
        if (!$node) {
            RateLimit::hit('nodeauth', 1000, 600);
        }
        return $node;
    }

    private function json(array $data, int $status = 200): Response
    {
        $r = Response::json($data, $status);
        $r->headers['Cache-Control'] = 'no-store';
        return $r;
    }

    private function body(Request $req): array
    {
        return json_decode($req->body(), true) ?: [];
    }

    public function poll(Request $req): Response
    {
        $node = $this->node($req);
        if (!$node) {
            return $this->json(['error' => 'unauthorized'], 401);
        }
        $info = (array)($this->body($req)['info'] ?? []);
        Nodes::touch((int)$node['id'], array_slice($info, 0, 30, true) + ['ip' => $req->ip()]);
        return $this->json(['ok' => true, 'jobs' => Jobs::claimFor((int)$node['id'])]);
    }

    public function result(Request $req, array $params): Response
    {
        $node = $this->node($req);
        if (!$node) {
            return $this->json(['error' => 'unauthorized'], 401);
        }
        $job = DB::row('SELECT * FROM jobs WHERE id = ? AND node_id = ?', [(int)$params['id'], $node['id']]);
        if (!$job) {
            return $this->json(['error' => 'not_found'], 404);
        }
        $b = $this->body($req);
        Jobs::complete((int)$job['id'], !empty($b['ok']), (array)($b['result'] ?? []) + (!empty($b['ok']) ? [] : ['error' => (string)($b['error'] ?? 'errore')]), (string)($b['log'] ?? ''));
        return $this->json(['ok' => true]);
    }
}
