<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;
use Alien\Payments\Registry;
use Alien\Services\Updater;

final class WebhookController extends Controller
{
    public function github(Request $req): Response
    {
        if (!Updater::verifySignature($req->body(), $req->header('X-Hub-Signature-256'), Updater::webhookSecret())) {
            return new Response('Invalid signature', 403);
        }
        $event = $req->header('X-GitHub-Event');
        $payload = json_decode($req->body(), true) ?: [];
        if ($event === 'push' && ($payload['ref'] ?? '') === 'refs/heads/' . Updater::branch()) {
            Settings::set('update_pending', '1');
            return Response::json(['queued' => true], 202);
        }
        return Response::json(['queued' => false]);
    }

    public function handle(Request $req, array $params): Response
    {
        $gateway = Registry::get($params['gateway']);
        if (!$gateway || !$gateway->enabled()) {
            return new Response('Not found', 404);
        }
        return $gateway->handleWebhook($req);
    }
}
