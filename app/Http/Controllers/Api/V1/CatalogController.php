<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 모바일 폼에서 쓰는 참조 목록 — 제품 검색, 조직(병원/창고/공급사) 선택.
 * SupplierProductScope 가 공급사 계정을 자사 제품으로 자동 제한한다.
 */
class CatalogController extends ApiController
{
    public function products(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with('supplier:id,name')
            ->where('is_active', true)
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->where(fn ($s) => $s
                ->where('product_name', 'like', "%{$kw}%")
                ->orWhere('product_code', 'like', "%{$kw}%")
                ->orWhere('gtin', 'like', "%{$kw}%")
                ->orWhere('edi_code', 'like', "%{$kw}%")))
            ->when($request->integer('supplier_id'), fn ($q, $v) => $q->where('supplier_id', $v))
            ->orderBy('product_name');

        $paginator = $query->paginate($this->pageSize($request), ['*'], 'page', max($request->integer('page', 1), 1));

        return $this->paged($paginator, fn (Product $p) => [
            'id' => $p->id,
            'product_code' => $p->product_code,
            'product_name' => $p->product_name,
            'spec' => $p->spec,
            'gtin' => $p->gtin,
            'unit' => $p->unit,
            'box_qty' => $p->box_qty,
            'manufacturer' => $p->manufacturer,
            'supplier_name' => $p->supplier?->name,
            'storage_type' => $p->storage_type->value,
            'storage_label' => $p->storage_type->label(),
            'sales_price' => (float) $p->sales_price,
            'purchase_price' => (float) $p->purchase_price,
            'use_lot_control' => $p->use_lot_control,
            'use_expiry' => $p->use_expiry,
        ]);
    }

    public function organizations(Request $request): JsonResponse
    {
        $type = $request->string('org_type')->toString();

        $rows = Organization::query()
            ->where('is_active', true)
            ->when($type !== '' && OrgType::tryFrom($type) !== null, fn ($q) => $q->where('org_type', $type))
            ->when($request->string('keyword')->toString(), fn ($q, $kw) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$kw}%")->orWhere('code', 'like', "%{$kw}%")))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'org_type', 'code', 'name']);

        return response()->json([
            'data' => $rows->map(fn (Organization $o) => [
                'id' => $o->id,
                'org_type' => $o->org_type->value,
                'org_type_label' => $o->org_type->label(),
                'code' => $o->code,
                'name' => $o->name,
            ])->all(),
        ]);
    }
}
