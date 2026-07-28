<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

enum NotiType: string implements HasLabel
{
    use EnumOptions;

    case SAFETY_STOCK = 'SAFETY_STOCK';
    case EXPIRY = 'EXPIRY';
    case USAGE_SUBMITTED = 'USAGE_SUBMITTED';
    case USAGE_REJECTED = 'USAGE_REJECTED';
    case INBOUND_DELAY = 'INBOUND_DELAY';
    case RECALL = 'RECALL';
    case NOTICE = 'NOTICE';

    public function label(): string
    {
        return match ($this) {
            self::SAFETY_STOCK => '안전재고 미달',
            self::EXPIRY => '유통기한 임박',
            self::USAGE_SUBMITTED => '사용분 전송',
            self::USAGE_REJECTED => '사용분 반려',
            self::INBOUND_DELAY => '입고 지연',
            self::RECALL => '리콜',
            self::NOTICE => '공지사항',
        };
    }
}
