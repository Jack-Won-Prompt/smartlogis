<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Outbound;
use App\Models\OutboundItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboundItem>
 */
class OutboundItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'outbound_id' => Outbound::factory(),
            'product_id' => Product::factory(),
            'lot_id' => null,   // 피킹(FEFO) 시점에 배정
            'qty' => fake()->numberBetween(1, 100),
            'unit_price' => fake()->numberBetween(1_000, 500_000),
        ];
    }
}
