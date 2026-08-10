<?php

declare(strict_types=1);

namespace App\Http\Controllers\Usage;

use App\Enums\OrgType;
use App\Enums\SalesChannel;
use App\Enums\UsageStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UsageReport;
use App\Rules\NotClosedMonth;
use App\Services\DocumentNoService;
use App\Services\UsageApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 사용분 — 병원 등록/전송, 본사 승인/반려. 승인은 UsageApprovalService(재고 차감·정산 생성)에 위임.
 */
class UsageController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        // HospitalScope 가 병원 계정을 자동 필터. HQ 는 전체.
        $query = UsageReport::query()->with('hospital')->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->boolean('pending'), fn ($q) => $q->where('status', UsageStatus::SUBMITTED->value))
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('report_no', 'like', "%{$v}%"))
            ->orderByDesc('id');

        $size = min(max($request->integer('size', 10), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (UsageReport $r) => [
                'id' => $r->id,
                'report_no' => $r->report_no,
                'hospital_name' => $r->hospital->name,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'usage_date' => $r->usage_date->toDateString(),
                'items_count' => $r->items_count,
                'total_amount' => (float) $r->total_amount,
            ])->all(),
        ]);
    }

    /** 병원: 사용분 등록(DRAFT). */
    public function store(Request $request, DocumentNoService $docNo): JsonResponse
    {
        $user = $request->user();
        $isLife = $user->isLife();
        $validated = $request->validate([
            // 라이프사이언스(요청)는 병원 대신 등록하므로 대상 병원을 선택한다.
            'hospital_id' => [$isLife ? 'required' : 'nullable', 'integer', 'exists:organizations,id'],
            // 소급·사후 등록 허용 — 과거 사용일도 가능(마감월만 차단).
            'usage_date' => ['required', 'date', new NotClosedMonth],
            'sales_channel' => ['nullable', Rule::enum(SalesChannel::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.lot_id' => ['required', 'integer', 'exists:product_lots,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.dept' => ['nullable', 'string', 'max:50'],
        ], [], ['usage_date' => '사용일', 'hospital_id' => '병원']);

        // 병원 계정은 자기 병원, 라이프는 선택한 병원. 문서번호는 병원 코드 기준.
        $hospital = $isLife
            ? Organization::query()->where('org_type', OrgType::HOSPITAL)->findOrFail((int) $validated['hospital_id'])
            : $user->organization;

        $report = DB::transaction(function () use ($validated, $docNo, $user, $hospital) {
            $report = UsageReport::create([
                'report_no' => $docNo->next('UR', $hospital->code, 'Ym'),
                'hospital_id' => $hospital->id,
                'status' => UsageStatus::DRAFT,
                'usage_date' => $validated['usage_date'],
                'sales_channel' => $validated['sales_channel'] ?? SalesChannel::DIRECT->value,
                'total_amount' => 0,
                'created_by' => $user->id,
            ]);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $unit = (float) $product->sales_price;
                $amount = $unit * $item['qty'];
                $total += $amount;
                $report->items()->create([
                    'product_id' => $item['product_id'],
                    'lot_id' => $item['lot_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $unit,
                    'amount' => $amount,
                    'dept' => $item['dept'] ?? null,
                ]);
            }
            $report->update(['total_amount' => $total]);

            return $report;
        });

        return response()->json(['id' => $report->id, 'report_no' => $report->report_no]);
    }

    public function show(UsageReport $usage): JsonResponse
    {
        $usage->load(['hospital', 'items.product', 'items.lot']);

        return response()->json([
            'id' => $usage->id,
            'report_no' => $usage->report_no,
            'status' => $usage->status->value,
            'status_label' => $usage->status->label(),
            'hospital_name' => $usage->hospital->name,
            'usage_date' => $usage->usage_date->toDateString(),
            'reject_reason' => $usage->reject_reason,
            'total_amount' => (float) $usage->total_amount,
            'items' => $usage->items->map(fn ($it) => [
                'product_code' => $it->product->product_code,
                'product_name' => $it->product->product_name,
                'lot_no' => $it->lot->lot_no,
                'qty' => $it->qty,
                'amount' => (float) $it->amount,
                'dept' => $it->dept,
            ])->all(),
        ]);
    }

    /** 병원: 전송(DRAFT/REJECTED → SUBMITTED). */
    public function submit(UsageReport $usage): JsonResponse
    {
        if (! $usage->status->isEditable()) {
            return response()->json(['message' => '전송할 수 없는 상태입니다.'], 409);
        }
        $usage->update(['status' => UsageStatus::SUBMITTED, 'submitted_at' => now()]);

        return response()->json(['message' => "{$usage->report_no} 전송 완료 — 본사 승인 대기"]);
    }

    /** 본사: 승인. */
    public function approve(UsageReport $usage, UsageApprovalService $service, Request $request): JsonResponse
    {
        $service->approve($usage, $request->user()?->id);

        return response()->json(['message' => "{$usage->report_no} 승인 완료 — 재고 차감·정산 반영"]);
    }

    /** 본사: 반려. */
    public function reject(UsageReport $usage, UsageApprovalService $service, Request $request): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => '반려 사유']);
        $service->reject($usage, $validated['reason'], $request->user()?->id);

        return response()->json(['message' => "{$usage->report_no} 반려 완료"]);
    }

    public function create(): View
    {
        return view('usage.create');
    }

    public function approval(): View
    {
        return view('usage.approval');
    }

    public function index(): View
    {
        return view('usage.index');
    }
}
