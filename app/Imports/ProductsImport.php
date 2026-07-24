<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\StorageType;
use App\Models\Organization;
use App\Models\Product;
use App\Validation\ProductRules;

/**
 * 제품 마스터 엑셀 업로드 (CLAUDE.md §7.5).
 * 한국어 헤더 기준으로 검증하고, 공급사코드로 공급사를 매핑한다. 제품코드 중복은 갱신(upsert).
 */
class ProductsImport extends BaseRowImport
{
    /**
     * 공급사 코드 → id 캐시.
     *
     * @var array<string, int>
     */
    private array $supplierCache = [];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return ProductRules::importRules();
    }

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return ['공급사코드' => '공급사코드', '제품코드' => '제품코드', '제품명' => '제품명', '매입가' => '매입가', '매출가' => '매출가'];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function storeRow(array $row): void
    {
        $supplierId = $this->supplierId((string) $row['공급사코드']);

        $storage = match (trim((string) ($row['보관유형'] ?? '실온'))) {
            '냉장', 'COLD' => StorageType::COLD,
            '냉동', 'FROZEN' => StorageType::FROZEN,
            default => StorageType::ROOM,
        };

        Product::updateOrCreate(
            ['product_code' => (string) $row['제품코드']],
            [
                'product_name' => (string) $row['제품명'],
                'gtin' => $row['gtin'] ?? null,
                'edi_code' => $row['보험코드'] ?? null,
                'spec' => $row['규격'] ?? null,
                'manufacturer' => $row['제조사'] ?? null,
                'supplier_id' => $supplierId,
                'unit' => (string) ($row['단위'] ?? 'EA'),
                'box_qty' => (int) ($row['box당수량'] ?? 1),
                'purchase_price' => (float) $row['매입가'],
                'sales_price' => (float) $row['매출가'],
                'storage_type' => $storage,
                'is_active' => true,
            ]
        );
    }

    private function supplierId(string $code): int
    {
        return $this->supplierCache[$code] ??= (int) Organization::query()
            ->where('code', $code)->where('org_type', 'SUPPLIER')->value('id');
    }
}
