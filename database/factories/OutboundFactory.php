<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgType;
use App\Enums\OutboundSourceType;
use App\Enums\OutboundStatus;
use App\Models\Organization;
use App\Models\Outbound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outbound>
 */
class OutboundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'outbound_no' => 'OB-'.fake()->unique()->numerify('########-####'),
            'warehouse_id' => Organization::factory()->state(['org_type' => OrgType::WAREHOUSE]),
            'hospital_id' => Organization::factory()->state(['org_type' => OrgType::HOSPITAL]),
            'status' => OutboundStatus::DRAFT,
            'source_type' => OutboundSourceType::MANUAL,
            'planned_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
        ];
    }

    public function status(OutboundStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function autoReplenish(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => OutboundSourceType::AUTO_REPLENISH,
        ]);
    }
}
