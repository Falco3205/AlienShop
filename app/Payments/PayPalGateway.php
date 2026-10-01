<?php
declare(strict_types=1);

namespace Alien\Payments;

use Alien\Core\Http;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Orders;

final class PayPalGateway extends Gateway
{
    public function id(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function fields(): array
    {
        return [
            'title' => ['label' => __('Titolo mostrato al cliente'), 'type' => 'text'],
            'client_id' => ['label' => 'Client ID', 'type' => 'text'],
            'secret' => ['label' => 'Secret', 'type' => 'password'],
            'sandbox' => ['label' => __('Modalità sandbox (test)'), 'type' => 'checkbox'],
            'webhook_id' => ['label' => 'Webhook ID', 'type' => 'text', 'help' => __('Endpoint webhook: %s — evento: PAYMENT.CAPTURE.COMPLETED', url('webhooks/paypal'))],
        ];
    }

    private function base(): string
    {
        return $this->setting('sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private function token(): ?string
    {
        $r = Http::request('POST', $this->base() . '/v1/oauth2/token', 'grant_type=client_credentials', ['Content-Type: application/x-www-form-urlencoded'], 20, $this->setting('client_id') . ':' . $this->setting('secret'));
        return $r['json']['access_token'] ?? null;
    }

    private function api(string $method, string $path, ?array $json = null, array $extra = []): array
    {
        $token = $this->token();
        if (!$token) {
            return ['status' => 0, 'json' => null, 'body' => '', 'error' => 'auth'];
        }
        return Http::request($method, $this->base() . $path, $json === null ? ($method === 'POST' ? '{}' : null) : json_encode($json, json_flags()), array_merge([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ], $extra));
    }

    public function start(array $order): array
    {
        $cur = $order['currency'];
        $res = $this->api('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order['number'],
                'custom_id' => $order['token'],
                'description' => mb_substr(__('Ordine %s', $order['number']), 0, 120),
                'amount' => ['currency_code' => $cur, 'value' => $this->amount((int)$order['total'], $cur)],
            ]],
            'application_context' => [
                'return_url' => url('pay/return/paypal?order=' . $order['token']),
                'cancel_url' => url('pay/cancel/' . $order['token']),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'brand_name' => (string)\Alien\Core\Settings::get('store_name', 'Shop'),
            ],
        ], ['PayPal-Request-Id: ' . $order['token']]);
        $approve = null;
        foreach ($res['json']['links'] ?? [] as $l) {
            if (in_array($l['rel'], ['approve', 'payer-action'], true)) {
                $approve = $l['href'];
            }
        }
        if (!$approve || empty($res['json']['id'])) {
            return ['error' => __('PayPal non disponibile: %s', $res['json']['message'] ?? ($res['error'] ?: 'HTTP ' . $res['status']))];
        }
        return ['redirect' => $approve, 'ref' => $res['json']['id']];
    }

    public function handleReturn(Request $req, array $order): bool
    {
        if ($order['payment_status'] === 'paid') {
            return true;
        }
        $ppOrder = (string)$req->input('token', '');
        if ($ppOrder === '' || $ppOrder !== $order['payment_ref']) {
            return false;
        }
        $res = $this->api('POST', '/v2/checkout/orders/' . rawurlencode($ppOrder) . '/capture', null, ['PayPal-Request-Id: cap-' . $order['token']]);
        return $this->settle($res['json'] ?? [], $order);
    }

    private function settle(array $ppOrder, array $order): bool
    {
        $unit = $ppOrder['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];
        $ok = ($ppOrder['status'] ?? '') === 'COMPLETED'
            && ($capture['status'] ?? '') === 'COMPLETED'
            && ($unit['custom_id'] ?? $capture['custom_id'] ?? '') === $order['token']
            && ($capture['amount']['value'] ?? '') === $this->amount((int)$order['total'], $order['currency'])
            && ($capture['amount']['currency_code'] ?? '') === $order['currency'];
        if ($ok) {
            Orders::markPaid($order, (string)$capture['id']);
        }
        return $ok;
    }

    public function handleWebhook(Request $req): Response
    {
        $body = $req->body();
        $event = json_decode($body, true) ?: [];
        $verify = $this->api('POST', '/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $req->header('Paypal-Auth-Algo'),
            'cert_url' => $req->header('Paypal-Cert-Url'),
            'transmission_id' => $req->header('Paypal-Transmission-Id'),
            'transmission_sig' => $req->header('Paypal-Transmission-Sig'),
            'transmission_time' => $req->header('Paypal-Transmission-Time'),
            'webhook_id' => (string)$this->setting('webhook_id', ''),
            'webhook_event' => $event,
        ]);
        if (($verify['json']['verification_status'] ?? '') !== 'SUCCESS') {
            return new Response('Invalid signature', 400);
        }
        if (($event['event_type'] ?? '') === 'PAYMENT.CAPTURE.COMPLETED') {
            $res = $event['resource'] ?? [];
            $order = Orders::findByToken((string)($res['custom_id'] ?? ''));
            if ($order && ($res['amount']['value'] ?? '') === $this->amount((int)$order['total'], $order['currency'])) {
                Orders::markPaid($order, (string)($res['id'] ?? ''));
            }
        }
        return new Response('ok');
    }
}
