<?php
declare(strict_types=1);

namespace Alien\Payments;

final class CodGateway extends Gateway
{
    public function id(): string
    {
        return 'cod';
    }

    public function label(): string
    {
        return __('Pagamento alla consegna');
    }

    public function fields(): array
    {
        return [
            'title' => ['label' => __('Titolo mostrato al cliente'), 'type' => 'text'],
            'instructions' => ['label' => __('Istruzioni per il cliente'), 'type' => 'textarea'],
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
