<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\Response;
use Hub\Fleet;
use Hub\Nodes;
use Hub\Poller;
use Hub\Shops;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $shops = Shops::all();
        $nodes = Nodes::all();
        return $this->view('dashboard', [
            'title' => 'Panoramica',
            'subtitle' => 'Tutti i tuoi negozi in un colpo d\'occhio',
            'actions' => '<a class="btn" href="' . e(url('shops/new')) . '">+ Nuovo negozio</a>',
            'shops' => $shops,
            'nodes' => $nodes,
            'fleet' => Fleet::summary($shops),
            'alerts' => Fleet::alerts($shops, $nodes),
            'lastPoll' => (int)setting('last_poll', 0),
        ], 'dashboard');
    }

    public function poll(): Response
    {
        $r = Poller::pollAll();
        return $this->back('', sprintf('Interrogati %d negozi: %d ok, %d con problemi.', $r['total'], $r['ok'], $r['failed']), $r['failed'] ? 'error' : 'success');
    }
}
