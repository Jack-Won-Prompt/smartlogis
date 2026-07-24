<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * GS1 바코드 파싱 결과 DTO. (01)GTIN (17)유통기한 (10)Lot (21)시리얼.
 */
final class Gs1Data
{
    public function __construct(
        public readonly ?string $gtin = null,
        public readonly ?Carbon $expiryDate = null,
        public readonly ?string $lotNo = null,
        public readonly ?string $serial = null,
        public readonly string $raw = '',
    ) {}

    public function hasGtin(): bool
    {
        return $this->gtin !== null && $this->gtin !== '';
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'gtin' => $this->gtin,
            'expiry_date' => $this->expiryDate?->toDateString(),
            'lot_no' => $this->lotNo,
            'serial' => $this->serial,
            'raw' => $this->raw,
        ];
    }
}
