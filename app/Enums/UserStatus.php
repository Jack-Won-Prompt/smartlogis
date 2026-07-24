<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;
use App\Enums\Concerns\HasTone;

/**
 * 사용자 계정 생명주기.
 *  PENDING  : 자체 회원가입 후 본사 승인 대기
 *  INVITED  : 본사가 초대(초대 링크 발송), 최초 비밀번호 미설정
 *  ACTIVE   : 정상 사용
 *  SUSPENDED: 정지/비활성
 */
enum UserStatus: string implements HasLabel, HasTone
{
    use EnumOptions;

    case PENDING = 'PENDING';
    case INVITED = 'INVITED';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '승인대기',
            self::INVITED => '초대됨',
            self::ACTIVE => '정상',
            self::SUSPENDED => '정지',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::PENDING => Tone::WARN,
            self::INVITED => Tone::INFO,
            self::ACTIVE => Tone::OK,
            self::SUSPENDED => Tone::HOLD,
        };
    }

    /** 로그인 가능한 상태인가. */
    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }
}
