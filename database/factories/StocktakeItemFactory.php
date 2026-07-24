<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StocktakeItem>
 */
class StocktakeItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $system = fake()->numberBetween(0, 200);
        $counted = $system + fake()->numberBetween(-5, 5);

        return [
            'stocktake_id' => Stocktake::factory(),
            'product_id' => Product::factory(),
            'lot_id' => ProductLot::factory(),
            'system_qty' => $system,
            'counted_qty' => $counted,
            'diff_qty' => $counted - $system,
            'reason' => null,
        ];
    }
}
