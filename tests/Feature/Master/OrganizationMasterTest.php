<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\User;

it('본사만 거래처 마스터에 접근할 수 있다', function () {
    actingAsRole(OrgType::HQ);
    $this->get('/master/organizations')->assertOk();
});

it('비본사 역할은 거래처 마스터 접근이 차단된다(403)', function () {
    actingAsRole(OrgType::SUPPLIER);
    $this->get('/master/organizations')->assertForbidden();
});

it('그리드 데이터는 페이지네이션 구조로 반환된다', function () {
    actingAsRole(OrgType::HQ);
    Organization::factory()->hospital()->count(3)->create();

    $this->getJson('/master/organizations/data?page=1&size=10')
        ->assertOk()
        ->assertJsonStructure(['last_page', 'total', 'data' => [['id', 'org_type', 'code', 'name', 'users_count', 'is_active']]]);
});

it('새 거래처를 등록한다', function () {
    actingAsRole(OrgType::HQ);

    $this->postJson('/master/organizations', [
        'org_type' => OrgType::HOSPITAL->value,
        'code' => 'HOSP-NEW',
        'name' => '신규병원',
        'biz_reg_no' => '123-45-67890',
    ])->assertOk()->assertJsonPath('code', 'HOSP-NEW');

    expect(Organization::where('code', 'HOSP-NEW')->exists())->toBeTrue();
});

it('중복 거래처 코드는 422를 반환한다', function () {
    actingAsRole(OrgType::HQ);
    Organization::factory()->create(['code' => 'DUP-ORG']);

    $this->postJson('/master/organizations', [
        'org_type' => OrgType::SUPPLIER->value, 'code' => 'DUP-ORG', 'name' => '중복',
    ])->assertStatus(422)->assertJsonValidationErrorFor('code');
});

it('인라인 셀 수정이 저장된다', function () {
    actingAsRole(OrgType::HQ);
    $org = Organization::factory()->create(['name' => '옛이름']);

    $this->patchJson("/master/organizations/{$org->id}", ['field' => 'name', 'value' => '새이름'])
        ->assertOk()->assertJsonPath('name', '새이름');

    expect($org->fresh()->name)->toBe('새이름');
});

it('허용되지 않은 필드 수정은 422', function () {
    actingAsRole(OrgType::HQ);
    $org = Organization::factory()->create();

    $this->patchJson("/master/organizations/{$org->id}", ['field' => 'id', 'value' => 999])
        ->assertStatus(422);
});

it('사용자 없는 거래처는 일괄 삭제된다', function () {
    actingAsRole(OrgType::HQ);
    $orgs = Organization::factory()->count(3)->create();

    $this->deleteJson('/master/organizations', ['ids' => $orgs->pluck('id')->all()])
        ->assertOk()->assertJsonPath('deleted', 3);

    expect(Organization::whereIn('id', $orgs->pluck('id'))->count())->toBe(0);
});

it('사용자 보유 거래처는 삭제 대신 비활성화된다(무결성 보호)', function () {
    actingAsRole(OrgType::HQ);
    $org = Organization::factory()->create(['is_active' => true]);
    User::factory()->create(['org_id' => $org->id]);

    $this->deleteJson('/master/organizations', ['ids' => [$org->id]])
        ->assertOk()->assertJsonPath('deleted', 0);

    expect($org->fresh())->not->toBeNull()
        ->and((bool) $org->fresh()->is_active)->toBeFalse();
});
