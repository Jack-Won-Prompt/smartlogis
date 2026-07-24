<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\RefType;
use App\Enums\TxType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature/Livewire 테스트는 Laravel TestCase + RefreshDatabase(sqlite :memory:)를 쓴다.
| Unit 테스트(Gs1Parser 등)는 프레임워크 부팅 없이 순수 PHP로 동작한다.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Livewire');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * 지정한 역할/조직의 사용자로 로그인한다.
 */
function actingAsRole(OrgType $role, ?Organization $org = null): User
{
    $org ??= Organization::factory()->create(['org_type' => $role]);

    $user = User::factory()->create([
        'role' => $role,
        'org_id' => $org->id,
    ]);

    test()->actingAs($user);

    return $user;
}

/**
 * 병원 선납창고에 재고를 입고시킨다(원장 경유). 테스트용 스텁.
 */
function seedHospitalStock(Organization $hospital, Product $product, int $qty): ProductLot
{
    $lot = ProductLot::factory()->create(['product_id' => $product->id]);

    app(StockService::class)->apply(
        type: TxType::IN_HOSPITAL,
        orgId: $hospital->id,
        productId: $product->id,
        lotId: $lot->id,
        qty: $qty,
        refType: RefType::INBOUND,
    );

    return $lot;
}
