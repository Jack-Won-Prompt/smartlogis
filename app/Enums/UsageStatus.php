<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;
use App\Enums\Concerns\HasTone;

enum UsageStatus: string implements HasLabel, HasTone
{
    use EnumOptions;

    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => '임시저장',
            self::SUBMITTED => '전송됨',
            self::APPROVED => '승인',
            self::REJECTED => '반려',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::DRAFT => Tone::HOLD,
            self::SUBMITTED => Tone::INFO,
            self::APPROVED => Tone::OK,
            self::REJECTED => Tone::CRIT,
        };
    }

    /** 병원이 수정할 수 있는 상태(반려분은 수정 후 재전송 가능). */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED], true);
    }

    /** 승인 처리 가능한 상태는 SUBMITTED 뿐이다(멱등성 검증에 사용). */
    public function isApprovable(): bool
    {
        return $this === self::SUBMITTED;
    }
}
