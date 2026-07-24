<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SafetyStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyStock>
 */
class SafetyStockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $safety = fake()->numberBetween(10, 100);

        return [
            'hospital_id' => Organization::factory()->state(['org_type' => OrgType::HOSPITAL]),
            'product_id' => Product::factory(),
            'safety_qty' => $safety,
            'max_qty' => $safety * 3,
            'reorder_qty' => $safety * 2,
        ];
    }
}
