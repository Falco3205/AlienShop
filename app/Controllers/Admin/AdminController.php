<?php
declare(strict_types=1);

namespace Alien\Controllers\Admin;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\View;

abstract class AdminController
{
    protected function view(string $tpl, array $data = [], string $active = ''): Response
    {
        $data += ['active' => $active ?: strtok($tpl, '/')];
        return new Response(View::admin($tpl, $data), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    protected function back(string $to, ?string $msg = null, string $type = 'success'): Response
    {
        if ($msg) {
            flash($type, $msg);
        }
        return Response::redirect($to);
    }

    protected function page(Request $req): int
    {
        return max(1, $req->int('page', 1));
    }

    protected function paginate(int $total, int $page, int $per): array
    {
        return ['total' => $total, 'page' => $page, 'per' => $per, 'pages' => max(1, (int)ceil($total / $per))];
    }
}
