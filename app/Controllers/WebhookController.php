<?php
declare(strict_types=1);

namespace Alien\Controllers;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Payments\Registry;

final class WebhookController extends Controller
{
    public function handle(Request $req, array $params): Response
    {
        $gateway = Registry::get($params['gateway']);
        if (!$gateway || !$gateway->enabled()) {
            return new Response('Not found', 404);
        }
        return $gateway->handleWebhook($req);
    }
}
