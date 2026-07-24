<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Exports\ArrayHeadingExport;
use App\Exports\FailedRowsExport;
use App\Exports\SafetyStocksExport;
use App\Http\Controllers\Controller;
use App\Imports\SafetyStocksImport;
use App\Models\SafetyStock;
use App\Support\ExcelFailReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 안전재고 마스터 그리드 백엔드. 복합키(hospital_id, product_id)라 행 식별자는 "h:p".
 */
class SafetyStockMasterController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $kw = $request->string('keyword')->toString();
        $query = SafetyStock::query()->with(['hospital', 'product'])
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('hospital_id', $v))
            ->when($kw, fn ($q) => $q->whereHas('product', fn ($s) => $s
                ->where('product_name', 'like', "%{$kw}%")->orWhere('product_code', 'like', "%{$kw}%")))
            ->orderBy('hospital_id')->orderBy('product_id');

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (SafetyStock $s) => $this->row($s))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'hospital_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('org_type', 'HOSPITAL')],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'safety_qty' => ['required', 'integer', 'min:0'],
            'max_qty' => ['nullable', 'integer', 'min:0'],
            'reorder_qty' => ['nullable', 'integer', 'min:0'],
        ], [], ['hospital_id' => '병원', 'product_id' => '제품', 'safety_qty' => '안전재고'])->validate();

        $safety = (int) $validated['safety_qty'];
        // 그리드 신규행은 max/reorder 를 0 으로 보내므로(null 아님) 0·빈값은 자동 산출로 처리한다.
        DB::table('safety_stocks')->updateOrInsert(
            ['hospital_id' => $validated['hospital_id'], 'product_id' => $validated['product_id']],
            [
                'safety_qty' => $safety,
                'max_qty' => (int) (($validated['max_qty'] ?? 0) ?: $safety * 3),
                'reorder_qty' => (int) (($validated['reorder_qty'] ?? 0) ?: $safety * 2),
                'updated_at' => now(), 'created_at' => now(),
            ]
        );

        $row = SafetyStock::with(['hospital', 'product'])
            ->where('hospital_id', $validated['hospital_id'])->where('product_id', $validated['product_id'])->first();

        return response()->json($this->row($row));
    }

    public function update(Request $request, string $key): JsonResponse
    {
        [$hospitalId, $productId] = array_map('intval', explode(':', $key) + [1 => 0]);
        $field = $request->string('field')->toString();

        if (! in_array($field, ['safety_qty', 'max_qty', 'reorder_qty'], true)) {
            return response()->json(['message' => '수정할 수 없는 항목입니다.'], 422);
        }
        $validated = Validator::make([$field => $request->input('value')], [$field => ['required', 'integer', 'min:0']])->validate();

        DB::table('safety_stocks')->where('hospital_id', $hospitalId)->where('product_id', $productId)
            ->update([$field => (int) $validated[$field], 'updated_at' => now()]);

        $row = SafetyStock::with(['hospital', 'product'])->where('hospital_id', $hospitalId)->where('product_id', $productId)->first();
        abort_if($row === null, 404);

        return response()->json($this->row($row));
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $deleted = 0;
        foreach ($request->input('ids', []) as $key) {
            [$h, $p] = array_map('intval', explode(':', (string) $key) + [1 => 0]);
            $deleted += DB::table('safety_stocks')->where('hospital_id', $h)->where('product_id', $p)->delete();
        }

        return response()->json(['deleted' => $deleted]);
    }

    /** 현재고 기반 자동 산출 추천. */
    public function autoSuggest(Request $request): JsonResponse
    {
        $hospitalId = $request->integer('hospital_id');
        if (! $hospitalId) {
            return response()->json(['message' => '병원을 선택하세요.'], 422);
        }

        $rows = DB::table('safety_stocks as s')
            ->leftJoin('stock_balances as b', function ($j) {
                $j->on('b.org_id', '=', 's.hospital_id')->on('b.product_id', '=', 's.product_id');
            })
            ->where('s.hospital_id', $hospitalId)
            ->groupBy('s.hospital_id', 's.product_id')
            ->select('s.product_id', DB::raw('COALESCE(SUM(b.qty),0) as onhand'))->get();

        $count = 0;
        foreach ($rows as $r) {
            $safety = max(10, (int) round($r->onhand * 0.6));
            DB::table('safety_stocks')->where('hospital_id', $hospitalId)->where('product_id', $r->product_id)
                ->update(['safety_qty' => $safety, 'max_qty' => $safety * 3, 'reorder_qty' => $safety * 2, 'updated_at' => now()]);
            $count++;
        }

        return response()->json(['count' => $count, 'message' => "{$count}개 품목을 현재고 기준으로 추천 적용했습니다."]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = SafetyStock::query()->with(['hospital', 'product'])
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('hospital_id', $v))
            ->orderBy('hospital_id')->orderBy('product_id');

        return Excel::download(new SafetyStocksExport($query), 'safety_stocks_'.now()->format('Ymd_His').'.xlsx');
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ArrayHeadingExport(['병원코드', '제품코드', '안전재고', '최대재고', '보충수량']), 'safety_stocks_template.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']], [], ['file' => '엑셀 파일']);
        $import = new SafetyStocksImport;
        Excel::import($import, $request->file('file')->getRealPath());
        $report = $import->report();

        $failKey = null;
        if ($report->hasFailures()) {
            $failKey = 'ssfail_'.uniqid();
            Session::put($failKey, $report);
        }

        return response()->json(['message' => $report->summaryMessage(), 'failed' => $report->failureCount(), 'failKey' => $failKey]);
    }

    public function failures(string $key): BinaryFileResponse
    {
        abort_unless(Session::has($key), 404);
        /** @var ExcelFailReport $report */
        $report = Session::get($key);

        return Excel::download(new FailedRowsExport($report, ['병원코드', '제품코드', '안전재고', '최대재고', '보충수량']), 'safety_stocks_failures.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(SafetyStock $s): array
    {
        return [
            'id' => $s->hospital_id.':'.$s->product_id,
            'hospital_id' => $s->hospital_id,
            'hospital_name' => $s->hospital?->name,
            'product_id' => $s->product_id,
            'product_code' => $s->product?->product_code,
            'product_name' => $s->product?->product_name,
            'safety_qty' => $s->safety_qty,
            'max_qty' => $s->max_qty,
            'reorder_qty' => $s->reorder_qty,
        ];
    }
}
