<?php
declare(strict_types=1);

namespace Alien\Payments;

final class BankGateway extends Gateway
{
    public function id(): string
    {
        return 'bank';
    }

    public function label(): string
    {
        return __('Bonifico bancario');
    }

    public function fields(): array
    {
        return [
            'title' => ['label' => __('Titolo mostrato al cliente'), 'type' => 'text'],
            'instructions' => ['label' => __('Istruzioni di pagamento (IBAN, causale...)'), 'type' => 'textarea'],
        ];
    }

    public function description(): string
    {
        return (string)$this->setting('instructions', '');
    }

    public function start(array $order): array
    {
        return ['offline' => true, 'message' => $this->description()];
    }
}
