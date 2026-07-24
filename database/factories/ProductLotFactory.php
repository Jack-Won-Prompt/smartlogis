<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductLot>
 */
class ProductLotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'lot_no' => strtoupper(fake()->bothify('??##?##')),
            'expiry_date' => fake()->dateTimeBetween('+1 month', '+3 years')->format('Y-m-d'),
        ];
    }

    public function expiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->addDays($days)->format('Y-m-d'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->subDays(fake()->numberBetween(1, 60))->format('Y-m-d'),
        ]);
    }
}
