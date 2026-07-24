<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SettlementStatus;
use App\Enums\SettleType;
use App\Models\Organization;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Settlement>
 */
class SettlementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year_month' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m'),
            'org_id' => Organization::factory(),
            'settle_type' => SettleType::SALES,
            'status' => SettlementStatus::OPEN,
            'total_qty' => 0,
            'total_amount' => 0,
        ];
    }
}
