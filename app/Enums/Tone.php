<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * DESIGN.md §1.3 시맨틱 색 토큰과 1:1 대응하는 상태 톤.
 * 화면(StatusBadge 등)에서 색을 하드코딩하지 않고 이 값만 참조한다.
 */
enum Tone: string
{
    case OK = 'ok';        // 정상, 승인, 재고 충분
    case WARN = 'warn';    // 주의: 안전재고 근접, D-60
    case CRIT = 'crit';    // 위험: 재고 미달, D-30, 반려, 유통기한 경과
    case INFO = 'info';    // 진행중, 전송됨, 배송중
    case HOLD = 'hold';    // 임시저장, 취소, 비활성
}
