<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\DB;
use Alien\Core\Request;
use Alien\Core\Response;
use Hub\Jobs;

final class JobsController extends Controller
{
    public function index(): Response
    {
        return $this->view('jobs/index', [
            'title' => 'Attività dei server',
            'subtitle' => 'Installazioni e operazioni eseguite dagli agenti',
            'jobs' => Jobs::recent(100),
            'nodes' => array_column(DB::all('SELECT id, name FROM nodes'), 'name', 'id'),
            'shops' => array_column(DB::all('SELECT id, name FROM shops'), 'name', 'id'),
        ], 'jobs');
    }

    public function show(Request $req, array $params): Response
    {
        $job = DB::row('SELECT * FROM jobs WHERE id = ?', [(int)$params['id']]);
        if (!$job) {
            return $this->back('jobs', 'Attività non trovata.', 'error');
        }
        $payload = json_decode((string)$job['payload'], true) ?: [];
        foreach (['admin_password', 'hub_secret'] as $secret) {
            if (isset($payload[$secret])) {
                $payload[$secret] = '••••••••';
            }
        }
        return $this->view('jobs/show', ['title' => 'Attività #' . $job['id'], 'subtitle' => Jobs::TYPES[$job['type']] ?? $job['type'], 'job' => $job, 'payload' => $payload, 'result' => json_decode((string)$job['result'], true) ?: []], 'jobs');
    }
}
