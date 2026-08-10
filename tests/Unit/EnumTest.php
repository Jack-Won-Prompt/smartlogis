<?php

declare(strict_types=1);

use App\Enums\InboundDirection;
use App\Enums\OrgType;
use App\Enums\TxType;
use App\Enums\UsageStatus;

it('모든 상태 Enum이 한국어 라벨을 제공한다', function () {
    expect(OrgType::HQ->label())->toBe('본사');
    expect(UsageStatus::APPROVED->label())->toBe('승인');
});

it('options()는 value=>label 배열을 만든다', function () {
    expect(OrgType::options())->toBe([
        'HQ' => '본사',
        'WAREHOUSE' => '물류창고',
        'HOSPITAL' => '거점병원',
        'SUPPLIER' => '공급사',
        'LIFE' => '라이프사이언스',
    ]);
});

it('TxType 부호 방향이 정확하다', function () {
    expect(TxType::IN_SUPPLIER->signedDirection())->toBe(1);
    expect(TxType::USE->signedDirection())->toBe(-1);
    expect(TxType::ADJUST->signedDirection())->toBeNull();
});

it('입고 방향은 대응하는 재고 트랜잭션 유형을 안다', function () {
    expect(InboundDirection::SUPPLIER_TO_WH->txType())->toBe(TxType::IN_SUPPLIER);
    expect(InboundDirection::WH_TO_HOSPITAL->txType())->toBe(TxType::IN_HOSPITAL);
});

it('UsageStatus 승인 가능 여부는 SUBMITTED 에서만 참이다', function () {
    expect(UsageStatus::SUBMITTED->isApprovable())->toBeTrue();
    expect(UsageStatus::DRAFT->isApprovable())->toBeFalse();
    expect(UsageStatus::APPROVED->isApprovable())->toBeFalse();
});
