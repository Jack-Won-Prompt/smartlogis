<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use App\Enums\Tone;

/**
 * 상태값 Enum이 시맨틱 톤(DESIGN.md §1.3)을 제공하도록 강제한다.
 */
interface HasTone
{
    public function tone(): Tone;
}
