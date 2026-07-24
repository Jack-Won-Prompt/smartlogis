<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgType;
use App\Enums\StocktakeStatus;
use App\Models\Organization;
use App\Models\Stocktake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stocktake>
 */
class StocktakeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stocktake_no' => 'ST-'.fake()->unique()->numerify('########-####'),
            'org_id' => Organization::factory()->state(['org_type' => OrgType::WAREHOUSE]),
            'status' => StocktakeStatus::DRAFT,
            'count_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
        ];
    }

    public function status(StocktakeStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
