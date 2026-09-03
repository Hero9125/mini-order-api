<?php

namespace App\Exceptions;

use Exception;
use App\Models\Product;

class InsufficientStockException extends Exception
{
    public readonly string $productName;
    public readonly int $available;
    public readonly int $requested;

    public function __construct(Product $product, int $requested)
    {
        $this->productName = $product->name;
        $this->available   = $product->stock;
        $this->requested   = $requested;

        parent::__construct(
            "Insufficient stock for \"{$product->name}\". Requested: {$requested}, Available: {$product->stock}."
        );
    }
}
