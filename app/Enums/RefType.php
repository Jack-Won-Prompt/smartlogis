<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

/**
 * 재고 트랜잭션의 근거 문서 유형(ref_type/ref_id).
 */
enum RefType: string implements HasLabel
{
    use EnumOptions;

    case INBOUND = 'INBOUND';
    case OUTBOUND = 'OUTBOUND';
    case USAGE = 'USAGE';
    case STOCKTAKE = 'STOCKTAKE';
    case RETURN = 'RETURN';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::INBOUND => '입고',
            self::OUTBOUND => '출고',
            self::USAGE => '사용분',
            self::STOCKTAKE => '재고 실사',
            self::RETURN => '반납',
            self::MANUAL => '수기 조정',
        };
    }
}
