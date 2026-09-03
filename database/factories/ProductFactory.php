<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'name'        => fake()->words(3, true) . ' ' . fake()->word(),
            'description' => fake()->paragraph(2),
            'price'       => fake()->randomFloat(2, 5, 999),
            'stock'       => fake()->numberBetween(0, 200),
            'sku'         => 'SKU-' . str_pad($counter, 5, '0', STR_PAD_LEFT),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function inStock(int $qty = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $qty,
        ]);
    }
}
