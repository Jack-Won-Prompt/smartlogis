<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UsageReport;

/**
 * Phase 7: 권한 스코프 전수 검증(HTTP 레벨).
 * EnsureRole 미들웨어(화면 접근 역할)와 Global Scope(데이터 범위)가
 * 라우트/컨트롤러를 통과할 때 실제로 강제되는지 확인한다.
 */

// ── 화면 접근 역할(EnsureRole) ───────────────────────────────────────────────

it('비로그인 사용자는 대시보드 접근 시 로그인으로 리다이렉트된다', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

dataset('금지된 역할·경로', [
    '병원은 마스터(HQ 전용) 진입 불가' => [OrgType::HOSPITAL, '/master/products'],
    '공급사는 출고 지시(HQ·창고) 진입 불가' => [OrgType::SUPPLIER, '/outbounds'],
    '병원은 사용분 승인(HQ 전용) 진입 불가' => [OrgType::HOSPITAL, '/usages/approval'],
    '창고는 공급사 화면 진입 불가' => [OrgType::WAREHOUSE, '/supplier/stocks'],
    '공급사는 감사 로그(HQ 전용) 진입 불가' => [OrgType::SUPPLIER, '/admin/audit-logs'],
]);

it('허용되지 않은 역할은 403 을 받는다', function (OrgType $role, string $path) {
    actingAsRole($role);
    $this->get($path)->assertForbidden();
})->with('금지된 역할·경로');

dataset('허용된 역할·경로', [
    'HQ 마스터' => [OrgType::HQ, '/master/products'],
    'HQ 사용분 승인' => [OrgType::HQ, '/usages/approval'],
    'HQ 감사 로그' => [OrgType::HQ, '/admin/audit-logs'],
    '공급사 자사 재고' => [OrgType::SUPPLIER, '/supplier/stocks'],
]);

it('허용된 역할은 화면에 정상 진입한다', function (OrgType $role, string $path) {
    actingAsRole($role);
    $this->get($path)->assertOk();
})->with('허용된 역할·경로');

// ── 데이터 스코프(Global Scope) — HTTP 데이터 엔드포인트 ───────────────────────

it('병원 A 는 사용분 목록에서 자기 병원 것만 받는다', function () {
    $hospitalA = Organization::factory()->hospital()->create();
    $hospitalB = Organization::factory()->hospital()->create();
    UsageReport::factory()->for($hospitalA, 'hospital')->create();
    UsageReport::factory()->for($hospitalB, 'hospital')->create();

    actingAsRole(OrgType::HOSPITAL, $hospitalA);

    $res = $this->getJson('/usages/data')->assertOk();
    expect($res->json('total'))->toBe(1);
});

it('병원 A 는 타 병원 사용분 단건 조회 시 404 를 받는다', function () {
    $hospitalA = Organization::factory()->hospital()->create();
    $hospitalB = Organization::factory()->hospital()->create();
    $reportB = UsageReport::factory()->for($hospitalB, 'hospital')->create();

    actingAsRole(OrgType::HOSPITAL, $hospitalA);

    // 라우트 모델 바인딩이 HospitalScope 안에서 해석 → 못 찾음(404).
    $this->getJson("/usages/{$reportB->id}")->assertNotFound();
});

it('공급사 A 는 자사 제품 재고만 데이터로 받는다', function () {
    $supplierA = Organization::factory()->supplier()->create();
    $supplierB = Organization::factory()->supplier()->create();
    $hospital = Organization::factory()->hospital()->create();

    $ownProduct = Product::factory()->create(['supplier_id' => $supplierA->id]);
    $otherProduct = Product::factory()->create(['supplier_id' => $supplierB->id]);

    // 두 제품 모두 병원 재고 보유
    seedHospitalStock($hospital, $ownProduct, 10);
    seedHospitalStock($hospital, $otherProduct, 20);

    actingAsRole(OrgType::SUPPLIER, $supplierA);

    $res = $this->getJson('/supplier/stocks/data')->assertOk();
    $codes = collect($res->json('data'))->pluck('product_code')->unique();
    expect($codes)->toHaveCount(1)
        ->and($codes->first())->toBe($ownProduct->product_code);
});
