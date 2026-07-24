<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumOptions;
use App\Enums\Concerns\HasLabel;

/**
 * 재고 트랜잭션 유형. stock_transactions.qty 의 부호 규칙을 이 Enum이 소유한다.
 */
enum TxType: string implements HasLabel
{
    use EnumOptions;

    case IN_SUPPLIER = 'IN_SUPPLIER';           // 공급사 → 창고 입고 (+)
    case OUT_TO_HOSPITAL = 'OUT_TO_HOSPITAL';   // 창고 → 병원 출고 (-)
    case IN_HOSPITAL = 'IN_HOSPITAL';           // 병원 입고 확정 (+)
    case USE = 'USE';                           // 병원 사용분 승인 (-)
    case ADJUST = 'ADJUST';                     // 실사 차이 조정 (±)
    case RETURN_HOSPITAL = 'RETURN_HOSPITAL';   // 병원 → 창고 반품 (병원 -)
    case RETURN_SUPPLIER = 'RETURN_SUPPLIER';   // 창고 → 공급사 반품 (-)
    case TRANSFER = 'TRANSFER';                 // 창고 간 이동 (±)

    public function label(): string
    {
        return match ($this) {
            self::IN_SUPPLIER => '공급사 입고',
            self::OUT_TO_HOSPITAL => '병원 출고',
            self::IN_HOSPITAL => '병원 입고',
            self::USE => '사용',
            self::ADJUST => '재고 조정',
            self::RETURN_HOSPITAL => '병원 반품',
            self::RETURN_SUPPLIER => '공급사 반품',
            self::TRANSFER => '창고 이동',
        };
    }

    /**
     * 수량 부호가 고정된 유형인지. ADJUST/TRANSFER 는 ± 모두 허용.
     */
    public function signedDirection(): ?int
    {
        return match ($this) {
            self::IN_SUPPLIER, self::IN_HOSPITAL => 1,
            self::OUT_TO_HOSPITAL, self::USE, self::RETURN_HOSPITAL, self::RETURN_SUPPLIER => -1,
            self::ADJUST, self::TRANSFER => null,
        };
    }
}
