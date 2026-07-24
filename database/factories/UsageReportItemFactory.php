<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\UsageReport;
use App\Models\UsageReportItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageReportItem>
 */
class UsageReportItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 30);
        $unit = fake()->numberBetween(1_000, 500_000);

        return [
            'usage_report_id' => UsageReport::factory(),
            'product_id' => Product::factory(),
            'lot_id' => ProductLot::factory(),
            'qty' => $qty,
            'unit_price' => $unit,
            'amount' => $qty * $unit,
            'dept' => fake()->randomElement(['정형외과', '신경외과', '순환기내과', '일반외과']),
            'procedure_info' => null,
        ];
    }
}
