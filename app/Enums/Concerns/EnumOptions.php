<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * HasLabel::options() 기본 구현.
 *
 * @mixin \BackedEnum
 */
trait EnumOptions
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            /** @var string $value */
            $value = $case->value;
            $out[$value] = $case->label();
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
