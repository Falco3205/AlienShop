<?php
declare(strict_types=1);

namespace Alien\Payments;

use Alien\Core\Http;
use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Services\Orders;

final class MollieGateway extends Gateway
{
    private const API = 'https://api.mollie.com/v2';

    public function id(): string
    {
        return 'mollie';
    }

    public function label(): string
    {
        return __('Mollie (carte, Bancontact, iDEAL, Satispay…)');
    }

    public function fields(): array
    {
        return [
            'title' => ['label' => __('Titolo mostrato al cliente'), 'type' => 'text'],
            'api_key' => ['label' => __('Chiave API (live_… / test_…)'), 'type' => 'password', 'help' => __('Il webhook viene impostato automaticamente su %s', url('webhooks/mollie'))],
        ];
    }

    private function call(string $method, string $path, ?array $json = null): array
    {
        return Http::request($method, self::API . $path, $json === null ? null : json_encode($json, json_flags()), [
            'Authorization: Bearer ' . $this->setting('api_key'),
            'Content-Type: application/json',
        ], 25);
    }

    public function start(array $order): array
    {
        $res = $this->call('POST', '/payments', [
            'amount' => ['currency' => $order['currency'], 'value' => $this->amount((int)$order['total'], $order['currency'])],
            'description' => __('Ordine %s', $order['number']),
            'redirectUrl' => url('pay/return/mollie?order=' . $order['token']),
            'cancelUrl' => url('pay/cancel/' . $order['token']),
            'webhookUrl' => url('webhooks/mollie'),
            'locale' => \Alien\Core\Lang::locale() === 'en' ? 'en_GB' : 'it_IT',
            'metadata' => ['order_token' => $order['token'], 'order_number' => $order['number']],
        ]);
        $checkout = $res['json']['_links']['checkout']['href'] ?? null;
        if ($res['status'] !== 201 || !$checkout || empty($res['json']['id'])) {
            return ['error' => __('Mollie non disponibile: %s', $res['json']['detail'] ?? ($res['error'] ?: 'HTTP ' . $res['status']))];
        }
        return ['redirect' => $checkout, 'ref' => $res['json']['id']];
    }

    private function settle(array $payment, array $order): bool
    {
        $ok = ($payment['status'] ?? '') === 'paid'
            && ($payment['metadata']['order_token'] ?? '') === $order['token']
            && ($payment['amount']['value'] ?? '') === $this->amount((int)$order['total'], $order['currency'])
            && ($payment['amount']['currency'] ?? '') === $order['currency'];
        if ($ok) {
            Orders::markPaid($order, (string)$payment['id']);
        } elseif (in_array($payment['status'] ?? '', ['failed', 'expired', 'canceled'], true) && ($payment['metadata']['order_token'] ?? '') === $order['token']) {
            Orders::markFailed($order);
        }
        return $ok;
    }

    public function handleReturn(Request $req, array $order): bool
    {
        if ($order['payment_status'] === 'paid') {
            return true;
        }
        $id = (string)$order['payment_ref'];
        if (!preg_match('/^tr_[A-Za-z0-9]+$/D', $id)) {
            return false;
        }
        return $this->settle($this->call('GET', '/payments/' . $id)['json'] ?? [], $order);
    }

    public function handleWebhook(Request $req): Response
    {
        $id = (string)($req->post['id'] ?? '');
        if (!preg_match('/^tr_[A-Za-z0-9]+$/D', $id)) {
            return new Response('ok');
        }
        $payment = $this->call('GET', '/payments/' . $id)['json'] ?? [];
        $order = Orders::findByToken((string)($payment['metadata']['order_token'] ?? ''));
        if ($order && $order['payment_ref'] === $id) {
            $this->settle($payment, $order);
        }
        return new Response('ok');
    }

    public function refund(array $order): ?string
    {
        $id = (string)$order['payment_ref'];
        if (!str_starts_with($id, 'tr_')) {
            return __('Riferimento di pagamento Mollie mancante.');
        }
        $res = $this->call('POST', '/payments/' . $id . '/refunds', ['amount' => ['currency' => $order['currency'], 'value' => $this->amount((int)$order['total'], $order['currency'])]]);
        return in_array($res['status'], [200, 201], true) ? null : (string)($res['json']['detail'] ?? ($res['error'] ?: 'HTTP ' . $res['status']));
    }
}
