<?php
declare(strict_types=1);

namespace Alien\Services;

final class OutOfStock extends \RuntimeException
{
    public function __construct(public readonly string $productName)
    {
        parent::__construct('Out of stock: ' . $productName);
    }
}
