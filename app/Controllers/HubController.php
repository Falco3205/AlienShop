<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Auth;
use Alien\Core\DB;
use Alien\Core\HubSign;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\HubAgent;
use Alien\Services\Updater;

final class HubController extends Controller
{
    private function authorize(Request $req): ?Response
    {
        if (!HubAgent::configured() || !HubSign::verify(HubAgent::secret(), $req->method, $req->path, $req->body(), $req->header('X-Alien-Time'), $req->header('X-Alien-Signature'))) {
            return $this->json(['error' => 'unauthorized'], 403);
        }
        return null;
    }

    private function json(array $data, int $status = 200): Response
    {
        $r = Response::json($data, $status);
        $r->headers['Cache-Control'] = 'private, no-store';
        $r->headers['X-Robots-Tag'] = 'noindex';
        return $r;
    }

    public function ping(Request $req): Response
    {
        return $this->authorize($req) ?? $this->json(['ok' => true, 'version' => ALIEN_VERSION, 'time' => time()]);
    }

    public function stats(Request $req): Response
    {
        return $this->authorize($req) ?? $this->json(['ok' => true, 'metrics' => HubAgent::metrics()]);
    }

    public function update(Request $req): Response
    {
        if ($denied = $this->authorize($req)) {
            return $denied;
        }
        @set_time_limit(600);
        $before = Updater::currentCommit();
        $r = Updater::apply();
        Updater::forgetCommit();
        return $this->json(['ok' => $r['ok'], 'message' => $r['message'], 'from' => $before, 'to' => $r['ok'] ? Updater::currentCommit() : $before]);
    }

    public function sso(Request $req): Response
    {
        if ($denied = $this->authorize($req)) {
            return $denied;
        }
        return $this->json(['ok' => true, 'token' => HubAgent::createSso()]);
    }

    public function login(Request $req): Response
    {
        if (!HubAgent::consumeSso($req->str('t'))) {
            return $this->missing($req);
        }
        $admin = DB::row("SELECT id, email, name, phone, role FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
        if (!$admin) {
            return $this->missing($req);
        }
        Auth::login($admin);
        setcookie('as_admin', '1', ['expires' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
        return Response::redirect('admin');
    }
}
