<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/**
 * 거래처(조직) 마스터 권한. 마스터 관리는 본사 전용.
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isHq();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->isHq() || $user->org_id === $organization->id;
    }

    public function create(User $user): bool
    {
        return $user->isHq();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isHq();
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isHq();
    }
}
