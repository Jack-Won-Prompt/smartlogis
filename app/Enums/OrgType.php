<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

/**
 * 조직 유형 = 사용자 역할(users.role)과 동일한 값 집합을 쓴다.
 */
enum OrgType: string implements HasLabel
{
    use EnumOptions;

    case HQ = 'HQ';
    case WAREHOUSE = 'WAREHOUSE';
    case HOSPITAL = 'HOSPITAL';
    case SUPPLIER = 'SUPPLIER';
    case LIFE = 'LIFE';   // 라이프사이언스(요청) — 병원 대신 물품 요청·사용확정·반납

    public function label(): string
    {
        return match ($this) {
            self::HQ => '본사',
            self::WAREHOUSE => '물류창고',
            self::HOSPITAL => '거점병원',
            self::SUPPLIER => '공급사',
            self::LIFE => '라이프사이언스',
        };
    }

    /** 전체 데이터를 볼 수 있는 역할인가. */
    public function seesAllData(): bool
    {
        return $this === self::HQ;
    }
}
