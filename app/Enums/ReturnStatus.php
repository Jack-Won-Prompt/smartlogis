<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;
use App\Enums\Concerns\HasTone;

/**
 * 반납 상태 — 등록 → 배송 → 수령확인(재고 복귀) → (취소).
 */
enum ReturnStatus: string implements HasLabel, HasTone
{
    use EnumOptions;

    case REQUESTED = 'REQUESTED';   // 라이프/병원 반납 등록
    case SHIPPING = 'SHIPPING';     // 지역담당 배송 중
    case RECEIVED = 'RECEIVED';     // 창고 수령확인 → 병원 재고 차감 + 창고 재고 복귀
    case CANCELED = 'CANCELED';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => '반납 등록',
            self::SHIPPING => '배송 중',
            self::RECEIVED => '수령 완료',
            self::CANCELED => '취소',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::REQUESTED => Tone::HOLD,
            self::SHIPPING => Tone::INFO,
            self::RECEIVED => Tone::OK,
            self::CANCELED => Tone::CRIT,
        };
    }
}
