<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * 모든 Backed Enum이 한국어 라벨을 제공하도록 강제한다.
 */
interface HasLabel
{
    public function label(): string;

    /**
     * 셀렉트 박스/필터 세그먼트용 [value => label] 배열.
     *
     * @return array<string, string>
     */
    public static function options(): array;
}
