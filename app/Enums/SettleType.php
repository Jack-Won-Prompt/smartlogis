<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

enum SettleType: string implements HasLabel
{
    use EnumOptions;

    case SALES = 'SALES';       // 병원 대상 매출
    case PURCHASE = 'PURCHASE'; // 공급사 대상 매입

    public function label(): string
    {
        return match ($this) {
            self::SALES => '매출',
            self::PURCHASE => '매입',
        };
    }
}
