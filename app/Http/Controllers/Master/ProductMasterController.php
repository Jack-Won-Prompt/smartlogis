<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Exports\ArrayHeadingExport;
use App\Exports\FailedRowsExport;
use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use App\Models\Product;
use App\Support\ExcelFailReport;
use App\Support\ExcelFile;
use App\Validation\ProductRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 제품 마스터 그리드(Tabulator) 백엔드 — 데이터/인라인수정/생성/일괄삭제 + 엑셀.
 */
class ProductMasterController extends Controller
{
    /** Tabulator 원격 페이지네이션 데이터. */
    public function data(Request $request): JsonResponse
    {
        $query = Product::query()->with('supplier')->filter([
            'keyword' => $request->string('keyword')->toString() ?: null,
            'supplier_id' => $request->integer('supplier_id') ?: null,
            'storage_type' => $request->string('storage_type')->toString() ?: null,
            'is_active' => $request->has('is_active') ? $request->string('is_active')->toString() : '',
        ]);

        // 정렬(Tabulator: sort[0][field], sort[0][dir])
        $sort = $request->input('sort.0');
        $sortable = ['product_code', 'product_name', 'sales_price', 'storage_type'];
        if (is_array($sort) && in_array($sort['field'] ?? '', $sortable, true)) {
            $query->orderBy($sort['field'], ($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('product_code');
        }

        $size = min(max($request->integer('size', 10), 1), 100);
        $paginator = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'data' => $paginator->getCollection()->map(fn (Product $p) => $this->row($p))->all(),
        ]);
    }

    /** 새 행 저장. */
    public function store(Request $request): JsonResponse
    {
        $data = array_merge([
            'unit' => 'EA', 'box_qty' => 1, 'purchase_price' => 0,
            'is_sterile' => false, 'use_lot_control' => true, 'use_expiry' => true, 'is_active' => true,
        ], $request->all());

        $validated = Validator::make($data, ProductRules::rules(), [], ProductRules::attributes())->validate();

        $product = Product::create($validated);

        return response()->json($this->row($product->load('supplier')));
    }

    /**
     * wwGrid 배치 저장: {updated,added,deleted} 를 한 트랜잭션에서 처리.
     */
    public function batch(Request $request): JsonResponse
    {
        $updated = $request->array('updated');
        $added = $request->array('added');
        $deleted = $request->array('deleted');
        $fields = array_keys(ProductRules::rules());
        $counts = ['added' => 0, 'updated' => 0, 'deleted' => 0];

        DB::transaction(function () use ($updated, $added, $deleted, $fields, &$counts) {
            $ids = collect($deleted)->pluck('id')->filter()->map(fn ($v) => (int) $v)->values()->all();
            if ($ids !== []) {
                $counts['deleted'] = Product::whereIn('id', $ids)->delete();
            }
            foreach ($added as $row) {
                $data = array_merge([
                    'unit' => 'EA', 'box_qty' => 1, 'purchase_price' => 0,
                    'is_sterile' => false, 'use_lot_control' => true, 'use_expiry' => true, 'is_active' => true,
                ], Arr::only($row, $fields));
                $validated = Validator::make($data, ProductRules::rules(), [], ProductRules::attributes())->validate();
                Product::create($validated);
                $counts['added']++;
            }
            foreach ($updated as $u) {
                $id = (int) ($u['current']['id'] ?? 0);
                $changed = Arr::only($u['changed'] ?? [], $fields);
                if ($id === 0 || $changed === []) {
                    continue;
                }
                $product = Product::find($id);
                if ($product === null) {
                    continue;
                }
                $validated = Validator::make($changed, array_intersect_key(ProductRules::rules($id), $changed), [], ProductRules::attributes())->validate();
                $product->update($validated);
                $counts['updated']++;
            }
        });

        return response()->json(array_merge(['message' => '저장되었습니다.'], $counts));
    }

    /** 인라인 셀 수정 {field, value}. */
    public function update(Request $request, Product $product): JsonResponse
    {
        $field = $request->string('field')->toString();
        $value = $request->input('value');

        $rules = ProductRules::rules($product->id);
        if (! array_key_exists($field, $rules)) {
            return response()->json(['message' => '수정할 수 없는 항목입니다.'], 422);
        }

        $validated = Validator::make([$field => $value], [$field => $rules[$field]], [], ProductRules::attributes())->validate();

        $product->update($validated);

        return response()->json($this->row($product->fresh('supplier')));
    }

    /** 일괄 삭제 {ids:[]}. */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = array_map('intval', $request->input('ids', []));
        $deleted = Product::whereIn('id', $ids)->delete();

        return response()->json(['deleted' => $deleted]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = Product::query()->with('supplier')->filter([
            'keyword' => $request->string('keyword')->toString() ?: null,
            'supplier_id' => $request->integer('supplier_id') ?: null,
            'storage_type' => $request->string('storage_type')->toString() ?: null,
            'is_active' => $request->has('is_active') ? $request->string('is_active')->toString() : '',
        ])->orderBy('product_code');

        return Excel::download(new ProductsExport($query), ExcelFile::name('제품마스터'));
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ArrayHeadingExport(
            ['제품코드', '제품명', 'gtin', '보험코드', '규격', '제조사', '공급사코드', '단위', 'box당수량', '매입가', '매출가', '보관유형']
        ), ExcelFile::template('제품마스터'));
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']], [], ['file' => '엑셀 파일']);

        $import = new ProductsImport;
        Excel::import($import, $request->file('file'));
        $report = $import->report();

        $failKey = null;
        if ($report->hasFailures()) {
            $failKey = 'pfail_'.uniqid();
            Session::put($failKey, $report);
        }

        return response()->json([
            'message' => $report->summaryMessage(),
            'failed' => $report->failureCount(),
            'failKey' => $failKey,
        ]);
    }

    public function failures(string $key): BinaryFileResponse
    {
        abort_unless(Session::has($key), 404);
        /** @var ExcelFailReport $report */
        $report = Session::get($key);

        return Excel::download(new FailedRowsExport($report,
            ['제품코드', '제품명', 'gtin', '보험코드', '규격', '제조사', '공급사코드', '단위', 'box당수량', '매입가', '매출가', '보관유형']
        ), ExcelFile::failures('제품마스터'));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Product $p): array
    {
        return [
            'id' => $p->id,
            'product_code' => $p->product_code,
            'product_name' => $p->product_name,
            'spec' => $p->spec,
            'gtin' => $p->gtin,
            'supplier_id' => $p->supplier_id,
            'supplier_name' => $p->supplier->name,
            'storage_type' => $p->storage_type->value,
            'storage_label' => $p->storage_type->label(),
            'sales_price' => (float) $p->sales_price,
            'is_active' => (bool) $p->is_active,
        ];
    }
}
