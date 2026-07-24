<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\StorageType;
use Illuminate\Validation\Rule;

/**
 * 제품 검증 규칙 — Livewire 폼과 엑셀 Import 가 공유한다 (CLAUDE.md §7.5).
 */
class ProductRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?int $ignoreId = null): array
    {
        return [
            'product_code' => ['required', 'string', 'max:50', Rule::unique('products', 'product_code')->ignore($ignoreId)],
            'product_name' => ['required', 'string', 'max:255'],
            'gtin' => ['nullable', 'string', 'max:14', Rule::unique('products', 'gtin')->ignore($ignoreId)],
            'edi_code' => ['nullable', 'string', 'max:30'],
            'spec' => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'SUPPLIER')],
            'unit' => ['required', 'string', 'max:10'],
            'box_qty' => ['required', 'integer', 'min:1'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sales_price' => ['required', 'numeric', 'min:0'],
            'storage_type' => ['required', Rule::enum(StorageType::class)],
            'is_sterile' => ['boolean'],
            'use_lot_control' => ['boolean'],
            'use_expiry' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'product_code' => '제품코드',
            'product_name' => '제품명',
            'gtin' => 'GTIN',
            'edi_code' => '보험코드',
            'spec' => '규격',
            'manufacturer' => '제조사',
            'supplier_id' => '공급사',
            'unit' => '단위',
            'box_qty' => 'BOX당 수량',
            'purchase_price' => '매입가',
            'sales_price' => '매출가',
            'storage_type' => '보관유형',
        ];
    }

    /**
     * 엑셀 Import 용(한국어 헤더 → 컬럼) 규칙. 헤더는 headings() 와 일치.
     *
     * @return array<string, mixed>
     */
    public static function importRules(): array
    {
        return [
            '제품코드' => ['required', 'string', 'max:50'],
            '제품명' => ['required', 'string', 'max:255'],
            'gtin' => ['nullable', 'max:14'],
            '보험코드' => ['nullable', 'max:30'],
            '규격' => ['nullable', 'max:100'],
            '제조사' => ['nullable', 'max:100'],
            '공급사코드' => ['required', 'string', Rule::exists('organizations', 'code')->where('org_type', 'SUPPLIER')],
            '단위' => ['nullable', 'max:10'],
            'box당수량' => ['nullable', 'integer', 'min:1'],
            '매입가' => ['required', 'numeric', 'min:0'],
            '매출가' => ['required', 'numeric', 'min:0'],
            '보관유형' => ['nullable', Rule::in(['실온', '냉장', '냉동', 'ROOM', 'COLD', 'FROZEN'])],
        ];
    }
}
