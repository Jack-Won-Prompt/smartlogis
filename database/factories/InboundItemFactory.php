<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inbound;
use App\Models\InboundItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundItem>
 */
class InboundItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inbound_id' => Inbound::factory(),
            'product_id' => Product::factory(),
            'lot_no' => strtoupper(fake()->bothify('??##K##')),
            'expiry_date' => fake()->dateTimeBetween('+3 months', '+2 years')->format('Y-m-d'),
            'qty' => fake()->numberBetween(1, 200),
            'unit_price' => fake()->numberBetween(1_000, 500_000),
            'scanned_barcode' => null,
        ];
    }
}
