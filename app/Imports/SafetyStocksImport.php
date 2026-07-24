<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * 안전재고 엑셀 업로드. 병원코드·제품코드로 매핑하고 (병원×제품) 복합키로 upsert 한다.
 */
class SafetyStocksImport extends BaseRowImport
{
    /** @var array<string, int> */
    private array $hospitalCache = [];

    /** @var array<string, int> */
    private array $productCache = [];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            '병원코드' => ['required', 'string', Rule::exists('organizations', 'code')->where('org_type', 'HOSPITAL')],
            '제품코드' => ['required', 'string', Rule::exists('products', 'product_code')],
            '안전재고' => ['required', 'integer', 'min:0'],
            '최대재고' => ['nullable', 'integer', 'min:0'],
            '보충수량' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return ['병원코드' => '병원코드', '제품코드' => '제품코드', '안전재고' => '안전재고'];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function storeRow(array $row): void
    {
        $hospitalId = $this->hospitalCache[$row['병원코드']] ??= (int) Organization::query()
            ->where('code', $row['병원코드'])->where('org_type', 'HOSPITAL')->value('id');
        $productId = $this->productCache[$row['제품코드']] ??= (int) Product::query()
            ->where('product_code', $row['제품코드'])->value('id');

        $safety = (int) $row['안전재고'];

        DB::table('safety_stocks')->updateOrInsert(
            ['hospital_id' => $hospitalId, 'product_id' => $productId],
            [
                'safety_qty' => $safety,
                'max_qty' => (int) ($row['최대재고'] ?? $safety * 3),
                'reorder_qty' => (int) ($row['보충수량'] ?? $safety * 2),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
