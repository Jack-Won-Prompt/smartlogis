<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;
use App\Enums\Concerns\HasTone;

enum StocktakeStatus: string implements HasLabel, HasTone
{
    use EnumOptions;

    case DRAFT = 'DRAFT';
    case COUNTING = 'COUNTING';
    case CONFIRMED = 'CONFIRMED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => '임시저장',
            self::COUNTING => '실사중',
            self::CONFIRMED => '확정',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::DRAFT => Tone::HOLD,
            self::COUNTING => Tone::INFO,
            self::CONFIRMED => Tone::OK,
        };
    }
}
