<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Stats;

final class StatsController extends AdminController
{
    public function index(Request $req): Response
    {
        $days = in_array($req->int('days', 30), [7, 30, 90], true) ? $req->int('days', 30) : 30;
        return $this->view('stats', [
            'title' => __('Statistiche'),
            'subtitle' => __('Cosa guardano, aggiungono al carrello e comprano i tuoi clienti'),
            'actions' => '<a class="btn sec" href="' . e(url('admin/analytics')) . '">📊 Google Analytics</a>',
            'days' => $days,
            'r' => Stats::report($days),
        ], 'stats');
    }
}
