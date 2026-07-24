<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

enum InboundDirection: string implements HasLabel
{
    use EnumOptions;

    case SUPPLIER_TO_WH = 'SUPPLIER_TO_WH';
    case WH_TO_HOSPITAL = 'WH_TO_HOSPITAL';

    public function label(): string
    {
        return match ($this) {
            self::SUPPLIER_TO_WH => '공급사 → 물류창고',
            self::WH_TO_HOSPITAL => '물류창고 → 거점병원',
        };
    }

    /** 입고 확정 시 생성할 재고 트랜잭션 유형. */
    public function txType(): TxType
    {
        return match ($this) {
            self::SUPPLIER_TO_WH => TxType::IN_SUPPLIER,
            self::WH_TO_HOSPITAL => TxType::IN_HOSPITAL,
        };
    }
}
