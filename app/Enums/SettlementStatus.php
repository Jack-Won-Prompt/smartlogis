<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;
use App\Enums\Concerns\HasTone;

enum SettlementStatus: string implements HasLabel, HasTone
{
    use EnumOptions;

    case OPEN = 'OPEN';
    case CONFIRMED = 'CONFIRMED';
    case CLOSED = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => '집계중',
            self::CONFIRMED => '확정',
            self::CLOSED => '마감',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::OPEN => Tone::INFO,
            self::CONFIRMED => Tone::OK,
            self::CLOSED => Tone::HOLD,
        };
    }
}
