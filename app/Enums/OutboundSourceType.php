<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

enum OutboundSourceType: string implements HasLabel
{
    use EnumOptions;

    case AUTO_REPLENISH = 'AUTO_REPLENISH';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::AUTO_REPLENISH => '자동보충',
            self::MANUAL => '수동지시',
        };
    }
}
