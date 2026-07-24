<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\UsageReportItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettlementItem>
 */
class SettlementItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 50);
        $unit = fake()->numberBetween(1_000, 500_000);

        return [
            'settlement_id' => Settlement::factory(),
            'usage_report_item_id' => UsageReportItem::factory(),
            'product_id' => Product::factory(),
            'qty' => $qty,
            'unit_price' => $unit,
            'amount' => $qty * $unit,
        ];
    }
}
