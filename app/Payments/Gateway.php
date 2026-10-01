<?php
declare(strict_types=1);

namespace Alien\Payments;

use Alien\Core\Request;
use Alien\Core\Response;
use Alien\Core\Settings;

abstract class Gateway
{
    abstract public function id(): string;

    abstract public function label(): string;

    abstract public function fields(): array;

    abstract public function start(array $order): array;

    public function description(): string
    {
        return '';
    }

    public function enabled(): bool
    {
        return (bool)Settings::get('pay_' . $this->id() . '_enabled', false);
    }

    public function title(): string
    {
        return (string)Settings::get('pay_' . $this->id() . '_title', $this->label());
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get('pay_' . $this->id() . '_' . $key, $default);
    }

    public function handleReturn(Request $req, array $order): bool
    {
        return $order['payment_status'] === 'paid';
    }

    public function handleWebhook(Request $req): Response
    {
        return new Response('Not supported', 404);
    }

    protected function amount(int $cents, string $currency): string
    {
        $zero = in_array($currency, ['JPY'], true);
        return number_format($zero ? $cents : $cents / 100, $zero ? 0 : 2, '.', '');
    }
}
