<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'org_type' => OrgType::HOSPITAL,
            'code' => strtoupper(fake()->unique()->bothify('ORG-####')),
            'name' => fake()->company(),
            'biz_reg_no' => fake()->numerify('###-##-#####'),
            'hpid_no' => null,
            'address' => fake()->address(),
            'tel' => fake()->numerify('02-###-####'),
            'is_active' => true,
        ];
    }

    public function type(OrgType $type): static
    {
        return $this->state(fn (array $attributes) => ['org_type' => $type]);
    }

    public function hospital(): static
    {
        return $this->state(fn (array $attributes) => [
            'org_type' => OrgType::HOSPITAL,
            'hpid_no' => fake()->numerify('########'),
        ]);
    }

    public function supplier(): static
    {
        return $this->type(OrgType::SUPPLIER);
    }

    public function warehouse(): static
    {
        return $this->type(OrgType::WAREHOUSE);
    }

    public function hq(): static
    {
        return $this->type(OrgType::HQ);
    }

    public function life(): static
    {
        return $this->type(OrgType::LIFE);
    }
}
