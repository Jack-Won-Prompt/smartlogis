<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;
use App\Enums\Concerns\HasTone;

enum InboundStatus: string implements HasLabel, HasTone
{
    use EnumOptions;

    case PLANNED = 'PLANNED';
    case RECEIVING = 'RECEIVING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELED = 'CANCELED';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => '입고예정',
            self::RECEIVING => '검수중',
            self::CONFIRMED => '입고확정',
            self::CANCELED => '취소',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::PLANNED => Tone::HOLD,
            self::RECEIVING => Tone::INFO,
            self::CONFIRMED => Tone::OK,
            self::CANCELED => Tone::HOLD,
        };
    }

    /** 확정/취소된 문서는 더 이상 수정할 수 없다. */
    public function isEditable(): bool
    {
        return in_array($this, [self::PLANNED, self::RECEIVING], true);
    }
}
