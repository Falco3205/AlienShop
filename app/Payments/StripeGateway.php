<?php
declare(strict_types=1);

namespace Alien\Payments;

use Alien\Core\Http;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Orders;

final class StripeGateway extends Gateway
{
    private const API = 'https://api.stripe.com/v1';

    public function id(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return __('Carta di credito (Stripe)');
    }

    public function fields(): array
    {
        return [
            'title' => ['label' => __('Titolo mostrato al cliente'), 'type' => 'text'],
            'secret_key' => ['label' => __('Chiave segreta (sk_live_… / sk_test_…)'), 'type' => 'password'],
            'webhook_secret' => ['label' => __('Segreto webhook (whsec_…)'), 'type' => 'password', 'help' => __('Endpoint webhook: %s — eventi: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed', url('webhooks/stripe'))],
        ];
    }

    private function call(string $method, string $path, ?array $params = null): array
    {
        return Http::request($method, self::API . $path, $params, [], 25, (string)$this->setting('secret_key') . ':');
    }

    public function start(array $order): array
    {
        $summary = implode(', ', array_map(static fn($i) => $i['name'] . ' x' . $i['qty'], $order['items']));
        $res = $this->call('POST', '/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => url('pay/return/stripe?order=' . $order['token'] . '&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('pay/cancel/' . $order['token']),
            'client_reference_id' => $order['number'],
            'customer_email' => $order['email'],
            'metadata' => ['order_token' => $order['token'], 'order_number' => $order['number']],
            'payment_intent_data' => ['metadata' => ['order_token' => $order['token'], 'order_number' => $order['number']]],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order['currency']),
                    'unit_amount' => (int)$order['total'],
                    'product_data' => [
                        'name' => __('Ordine %s', $order['number']),
                        'description' => mb_substr($summary, 0, 480),
                    ],
                ],
            ]],
        ]);
        if ($res['status'] !== 200 || empty($res['json']['url'])) {
            $msg = $res['json']['error']['message'] ?? ($res['error'] ?: 'HTTP ' . $res['status']);
            return ['error' => __('Stripe non disponibile: %s', $msg)];
        }
        return ['redirect' => $res['json']['url'], 'ref' => $res['json']['id']];
    }

    public function handleReturn(Request $req, array $order): bool
    {
        $sid = (string)$req->input('session_id', '');
        if ($order['payment_status'] === 'paid') {
            return true;
        }
        if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sid)) {
            return false;
        }
        $res = $this->call('GET', '/checkout/sessions/' . $sid);
        return $this->settle($res['json'] ?? [], $order);
    }

    private function settle(array $session, array $order): bool
    {
        $ok = ($session['payment_status'] ?? '') === 'paid'
            && ($session['metadata']['order_token'] ?? '') === $order['token']
            && (int)($session['amount_total'] ?? -1) === (int)$order['total']
            && strtolower((string)($session['currency'] ?? '')) === strtolower($order['currency']);
        if ($ok) {
            Orders::markPaid($order, (string)($session['payment_intent'] ?? $session['id'] ?? ''));
        }
        return $ok;
    }

    public static function verifySignature(string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = (int)$v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if ($t === null || !$sigs || abs(($now ?? time()) - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($sigs as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }
        return false;
    }

    public function handleWebhook(Request $req): Response
    {
        $payload = $req->body();
        $secret = (string)$this->setting('webhook_secret', '');
        if ($secret === '' || !self::verifySignature($payload, $req->header('Stripe-Signature'), $secret)) {
            return new Response('Invalid signature', 400);
        }
        $event = json_decode($payload, true) ?: [];
        $object = $event['data']['object'] ?? [];
        $token = (string)($object['metadata']['order_token'] ?? '');
        $order = $token !== '' ? Orders::findByToken($token) : null;
        if (!$order) {
            return new Response('ok');
        }
        switch ($event['type'] ?? '') {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $this->settle($object, $order);
                break;
            case 'checkout.session.async_payment_failed':
                Orders::markFailed($order);
                break;
        }
        return new Response('ok');
    }
}
