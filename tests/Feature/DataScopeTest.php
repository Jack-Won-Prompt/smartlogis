<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SafetyStock;
use App\Models\UsageReport;

/**
 * Phase 1 핵심 보안 요건: 데이터 스코프가 프론트가 아니라 서버(Global Scope)에서 강제되는지.
 * CLAUDE.md §2, 테스트 시나리오 8·9.
 */
it('HOSPITAL 계정은 타 병원 사용분을 조회할 수 없다', function () {
    $hospitalA = Organization::factory()->hospital()->create();
    $hospitalB = Organization::factory()->hospital()->create();

    UsageReport::factory()->for($hospitalA, 'hospital')->create();
    UsageReport::factory()->for($hospitalB, 'hospital')->create();

    actingAsRole(OrgType::HOSPITAL, $hospitalA);

    // 병원 A 로 로그인하면 자기 병원 사용분(1건)만 보인다.
    expect(UsageReport::count())->toBe(1);
    expect(UsageReport::query()->where('hospital_id', $hospitalB->id)->exists())->toBeFalse();
});

it('HOSPITAL 계정은 타 병원 안전재고를 조회할 수 없다', function () {
    $hospitalA = Organization::factory()->hospital()->create();
    $hospitalB = Organization::factory()->hospital()->create();

    SafetyStock::factory()->create(['hospital_id' => $hospitalA->id]);
    SafetyStock::factory()->create(['hospital_id' => $hospitalB->id]);

    actingAsRole(OrgType::HOSPITAL, $hospitalA);

    expect(SafetyStock::count())->toBe(1);
});

it('SUPPLIER 계정은 자사 제품만 조회한다', function () {
    $supplierA = Organization::factory()->supplier()->create();
    $supplierB = Organization::factory()->supplier()->create();

    Product::factory()->count(3)->create(['supplier_id' => $supplierA->id]);
    Product::factory()->count(5)->create(['supplier_id' => $supplierB->id]);

    actingAsRole(OrgType::SUPPLIER, $supplierA);

    expect(Product::count())->toBe(3);
});

it('HQ 계정은 전체 데이터를 조회한다', function () {
    $hospitalA = Organization::factory()->hospital()->create();
    $hospitalB = Organization::factory()->hospital()->create();

    UsageReport::factory()->for($hospitalA, 'hospital')->create();
    UsageReport::factory()->for($hospitalB, 'hospital')->create();

    actingAsRole(OrgType::HQ);

    expect(UsageReport::count())->toBe(2);
});

it('SUPPLIER 계정은 타사 제품을 단건 조회해도 못 찾는다', function () {
    $supplierA = Organization::factory()->supplier()->create();
    $supplierB = Organization::factory()->supplier()->create();

    $otherProduct = Product::factory()->create(['supplier_id' => $supplierB->id]);

    actingAsRole(OrgType::SUPPLIER, $supplierA);

    expect(Product::find($otherProduct->id))->toBeNull();
});
