<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

/**
 * 매출 채널 — 사용분(매출) 분류. 채널별 매출 리포트에 사용한다.
 */
enum SalesChannel: string implements HasLabel
{
    use EnumOptions;

    case GPO = 'GPO';
    case DIRECT = 'DIRECT';
    case TPL = 'TPL';         // 3PL
    case SURGERY = 'SURGERY'; // 수술
    case ONLINE = 'ONLINE';

    public function label(): string
    {
        return match ($this) {
            self::GPO => 'GPO',
            self::DIRECT => 'Direct(직거래)',
            self::TPL => '3PL',
            self::SURGERY => '수술',
            self::ONLINE => '온라인',
        };
    }
}
