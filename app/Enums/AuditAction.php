<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

enum AuditAction: string implements HasLabel
{
    use EnumOptions;

    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case APPROVE = 'APPROVE';
    case REJECT = 'REJECT';
    case CLOSE = 'CLOSE';
    case REOPEN = 'REOPEN';
    case LOGIN = 'LOGIN';
    case LOGOUT = 'LOGOUT';

    public function label(): string
    {
        return match ($this) {
            self::CREATE => '생성',
            self::UPDATE => '수정',
            self::DELETE => '삭제',
            self::APPROVE => '승인',
            self::REJECT => '반려',
            self::CLOSE => '마감',
            self::REOPEN => '마감취소',
            self::LOGIN => '로그인',
            self::LOGOUT => '로그아웃',
        };
    }
}
