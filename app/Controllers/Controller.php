<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\App;
use Alien\Core\Csrf;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\View;
use Alien\Services\Redirects;

abstract class Controller
{
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        $theme = null;
        if (isset($_GET['preview_theme']) && \Alien\Core\Auth::isAdmin() && \Alien\Services\Themes::get((string)$_GET['preview_theme'])) {
            $theme = (string)$_GET['preview_theme'];
            View::$override = $theme;
        }
        $data += ['previewTheme' => $theme];
        return new Response(View::render($template, $data), $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    protected function missing(Request $req): Response
    {
        if ($r = Redirects::resolve($req->path)) {
            return Response::redirect(preg_match('#^https?://#', $r['to_path']) ? $r['to_path'] : url($r['to_path']), (int)$r['code']);
        }
        return App::notFound();
    }

    protected function noStore(Response $r): Response
    {
        $r->headers['Cache-Control'] = 'private, no-store, max-age=0';
        return $r;
    }

    protected function csrfFails(Request $req): bool
    {
        return !Csrf::valid($req);
    }
}
