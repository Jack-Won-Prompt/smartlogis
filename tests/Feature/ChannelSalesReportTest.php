<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\SalesChannel;
use App\Enums\UsageStatus;
use App\Models\Organization;
use App\Models\UsageReport;

/**
 * B-5a 채널별 매출 — 승인 사용분을 채널별로 집계, 비중 계산.
 */
it('채널별 매출을 승인 사용분 기준으로 집계하고 비중을 계산한다', function () {
    $hospital = Organization::factory()->hospital()->create();
    $mk = fn (SalesChannel $ch, float $amt) => UsageReport::factory()->create([
        'hospital_id' => $hospital->id,
        'status' => UsageStatus::APPROVED,
        'sales_channel' => $ch,
        'usage_date' => now()->toDateString(),
        'total_amount' => $amt,
    ]);
    $mk(SalesChannel::DIRECT, 300);
    $mk(SalesChannel::GPO, 100);
    // 미승인은 제외
    UsageReport::factory()->create([
        'hospital_id' => $hospital->id, 'status' => UsageStatus::SUBMITTED,
        'sales_channel' => SalesChannel::GPO, 'usage_date' => now()->toDateString(), 'total_amount' => 999,
    ]);

    actingAsRole(OrgType::HQ);
    $res = $this->getJson('/reports/channel-sales/data');
    $res->assertOk();

    expect((float) $res->json('total'))->toEqual(400.0);
    $rows = collect($res->json('data'));
    expect((float) $rows->firstWhere('channel', 'DIRECT')['amount'])->toEqual(300.0);
    expect((float) $rows->firstWhere('channel', 'DIRECT')['share'])->toEqual(75.0);
    expect((float) $rows->firstWhere('channel', 'GPO')['share'])->toEqual(25.0);
    // 사용 안 된 채널도 0 으로 노출
    expect((float) $rows->firstWhere('channel', 'ONLINE')['amount'])->toEqual(0.0);
});

it('본사 외 역할은 채널별 매출 리포트에 접근할 수 없다', function () {
    actingAsRole(OrgType::HOSPITAL);
    $this->get('/reports/channel-sales')->assertForbidden();
});
