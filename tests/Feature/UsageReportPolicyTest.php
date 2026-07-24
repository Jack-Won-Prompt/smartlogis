<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\UsageStatus;
use App\Models\Organization;
use App\Models\UsageReport;
use App\Models\User;

/**
 * 상태 변경 이중 검증(Policy). Global Scope 와 별개로 "승인할 자격"을 확인한다.
 */
it('본사만 SUBMITTED 사용분을 승인할 수 있다', function () {
    $hospital = Organization::factory()->hospital()->create();
    $report = UsageReport::factory()->submitted()->for($hospital, 'hospital')->create();

    $hqUser = User::factory()->role(OrgType::HQ)->create();
    $hospitalUser = User::factory()->create(['role' => OrgType::HOSPITAL, 'org_id' => $hospital->id]);

    expect($hqUser->can('approve', $report))->toBeTrue();
    expect($hospitalUser->can('approve', $report))->toBeFalse();
});

it('이미 승인된 사용분은 다시 승인할 수 없다(멱등성)', function () {
    $hospital = Organization::factory()->hospital()->create();
    $report = UsageReport::factory()->status(UsageStatus::APPROVED)->for($hospital, 'hospital')->create();

    $hqUser = User::factory()->role(OrgType::HQ)->create();

    expect($hqUser->can('approve', $report))->toBeFalse();
});

it('사용분 등록은 병원만 가능하다', function () {
    $hqUser = User::factory()->role(OrgType::HQ)->create();
    $hospitalUser = User::factory()->role(OrgType::HOSPITAL)->create();

    expect($hospitalUser->can('create', UsageReport::class))->toBeTrue();
    expect($hqUser->can('create', UsageReport::class))->toBeFalse();
});
