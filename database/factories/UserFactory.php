<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'login_id' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => OrgType::HOSPITAL,
            'org_id' => Organization::factory(),
            'status' => UserStatus::ACTIVE,
            'tel' => fake()->numerify('010-####-####'),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'approved_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(OrgType $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
            'org_id' => Organization::factory()->state(['org_type' => $role]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => UserStatus::SUSPENDED,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => UserStatus::PENDING,
            'approved_at' => null,
        ]);
    }
}
