<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

enum StorageType: string implements HasLabel
{
    use EnumOptions;

    case ROOM = 'ROOM';
    case COLD = 'COLD';
    case FROZEN = 'FROZEN';

    public function label(): string
    {
        return match ($this) {
            self::ROOM => '실온',
            self::COLD => '냉장',
            self::FROZEN => '냉동',
        };
    }
}
