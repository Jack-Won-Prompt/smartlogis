<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgType;
use App\Enums\UsageStatus;
use App\Models\Organization;
use App\Models\UsageReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageReport>
 */
class UsageReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_no' => 'UR-'.fake()->unique()->numerify('######-HOSP-####'),
            'hospital_id' => Organization::factory()->state(['org_type' => OrgType::HOSPITAL]),
            'status' => UsageStatus::DRAFT,
            'usage_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'total_amount' => 0,
        ];
    }

    public function status(UsageStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UsageStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
    }
}
