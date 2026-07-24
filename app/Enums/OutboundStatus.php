<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;
use App\Enums\Concerns\HasTone;

enum OutboundStatus: string implements HasLabel, HasTone
{
    use EnumOptions;

    case DRAFT = 'DRAFT';
    case APPROVED = 'APPROVED';
    case PICKING = 'PICKING';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
    case CANCELED = 'CANCELED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => '임시저장',
            self::APPROVED => '승인',
            self::PICKING => '피킹중',
            self::SHIPPED => '배송중',
            self::DELIVERED => '배송완료',
            self::CANCELED => '취소',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::DRAFT => Tone::HOLD,
            self::APPROVED => Tone::OK,
            self::PICKING, self::SHIPPED => Tone::INFO,
            self::DELIVERED => Tone::OK,
            self::CANCELED => Tone::HOLD,
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::APPROVED], true);
    }
}
