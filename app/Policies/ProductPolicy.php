<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * 제품 마스터 권한. 조회는 SUPPLIER 도 가능하나(자사 제품은 Global Scope 로 필터),
 * 생성/수정/삭제는 본사만.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isHq();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isHq();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isHq();
    }
}
