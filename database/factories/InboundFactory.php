<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InboundDirection;
use App\Enums\InboundStatus;
use App\Enums\OrgType;
use App\Models\Inbound;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inbound>
 */
class InboundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inbound_no' => 'IB-'.fake()->unique()->numerify('########-####'),
            'direction' => InboundDirection::SUPPLIER_TO_WH,
            'from_org_id' => Organization::factory()->state(['org_type' => OrgType::SUPPLIER]),
            'to_org_id' => Organization::factory()->state(['org_type' => OrgType::WAREHOUSE]),
            'status' => InboundStatus::PLANNED,
            'planned_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
        ];
    }

    public function status(InboundStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
