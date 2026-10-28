<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStock extends RuntimeException
{
    public function __construct(public readonly string $sku, public readonly int $available, public readonly int $requested)
    {
        parent::__construct("Not enough {$sku}: {$available} in stock, {$requested} requested.");
    }
}
