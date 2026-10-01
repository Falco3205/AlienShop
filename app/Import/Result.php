<?php
declare(strict_types=1);

namespace Alien\Import;

final class Result
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public int $images = 0;
    public array $errors = [];

    public function error(string $message): void
    {
        if (count($this->errors) < 50) {
            $this->errors[] = $message;
        }
    }

    public function summary(): string
    {
        return __('Creati: %d · Aggiornati: %d · Saltati: %d · Immagini: %d', $this->created, $this->updated, $this->skipped, $this->images);
    }
}
