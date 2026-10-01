<?php
declare(strict_types=1);

namespace Hub\Controllers;

use Alien\Core\Response;
use Alien\Core\View;

abstract class Controller
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
        return Response::redirect($to, 303);
    }
}
