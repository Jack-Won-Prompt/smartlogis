<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;
use App\Enums\Concerns\HasTone;

enum Severity: string implements HasLabel, HasTone
{
    use EnumOptions;

    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case CRITICAL = 'CRITICAL';

    public function label(): string
    {
        return match ($this) {
            self::INFO => '정보',
            self::WARNING => '주의',
            self::CRITICAL => '위험',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::INFO => Tone::INFO,
            self::WARNING => Tone::WARN,
            self::CRITICAL => Tone::CRIT,
        };
    }
}
