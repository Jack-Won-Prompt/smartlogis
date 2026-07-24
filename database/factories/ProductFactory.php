<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgType;
use App\Enums\StorageType;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchase = fake()->numberBetween(1_000, 500_000);

        return [
            'product_code' => strtoupper(fake()->unique()->bothify('P-#####')),
            'product_name' => fake()->words(3, true),
            'udi_di' => fake()->numerify('##############'),
            'gtin' => fake()->unique()->numerify('088060########'),
            'edi_code' => fake()->numerify('########'),
            'spec' => fake()->bothify('MODEL-###'),
            'manufacturer' => fake()->company(),
            'supplier_id' => Organization::factory()->state(['org_type' => OrgType::SUPPLIER]),
            'unit' => 'EA',
            'box_qty' => fake()->randomElement([1, 5, 10, 20]),
            'purchase_price' => $purchase,
            'sales_price' => (int) ($purchase * fake()->randomFloat(2, 1.1, 1.5)),
            'storage_type' => fake()->randomElement(StorageType::cases()),
            'is_sterile' => fake()->boolean(70),
            'use_lot_control' => true,
            'use_expiry' => true,
            'is_active' => true,
        ];
    }
}
