<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Enums\UsageStatus;
use App\Exceptions\DomainException;
use App\Models\Product;
use App\Models\UsageReport;
use App\Rules\NotClosedMonth;
use App\Services\DocumentNoService;
use App\Services\UsageApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 사용분 — 병원이 스캔으로 등록·전송하고 본사가 승인/반려한다.
 * 승인은 UsageApprovalService(재고 차감 + 정산 생성 + 자동보충)에 위임한다.
 *
 * HospitalScope 가 병원 계정을 자기 병원 건으로 자동 제한한다.
 */
class UsageController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = UsageReport::query()
            ->with('hospital:id,name')
            ->withCount('items')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->boolean('pending'), fn ($q) => $q->where('status', UsageStatus::SUBMITTED->value))
            // 병원 필터 — 본사가 승인 대기를 병원별로 추릴 때 쓴다.
            ->when($request->integer('hospital_id'), fn ($q, $v) => $q->where('hospital_id', $v))
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('report_no', 'like', "%{$v}%"))
            ->when($request->date('date_from'), fn ($q, $v) => $q->whereDate('usage_date', '>=', $v))
            ->when($request->date('date_to'), fn ($q, $v) => $q->whereDate('usage_date', '<=', $v))
            ->orderByDesc('id');

        // 본사는 "승인할 게 몇 건이고 얼마인지" 를 먼저 본다.
        $pendingQ = (clone $query)->reorder()->where('status', UsageStatus::SUBMITTED->value);
        $pendingCnt = (clone $pendingQ)->count();
        $pendingAmt = (float) (clone $pendingQ)->sum('total_amount');

        $summary = $this->statusSummary($query, [
            UsageStatus::DRAFT->value => ['작성', 'hold'],
            UsageStatus::SUBMITTED->value => ['승인 대기', 'warn'],
            UsageStatus::APPROVED->value => ['승인', 'ok'],
            UsageStatus::REJECTED->value => ['반려', 'crit'],
        ], [
            $this->stat('전체', (clone $query)->reorder()->count(), '건'),
            $this->stat('승인 대기', $pendingCnt, '건', $pendingCnt > 0 ? 'warn' : 'ok'),
            $this->stat('대기 금액', $this->wonShort($pendingAmt), null, 'info'),
        ]);

        $query->reorder()->when(true, fn ($q) => match ($request->string('sort')->toString()) {
            'oldest' => $q->orderBy('id'),
            'amount_desc' => $q->orderByDesc('total_amount'),
            'usage_date' => $q->orderByDesc('usage_date')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        });

        $paginator = $query->paginate($this->pageSize($request), ['*'], 'page', max($request->integer('page', 1), 1));

        return $this->paged($paginator, fn (UsageReport $r) => [
            'id' => $r->id,
            'report_no' => $r->report_no,
            'hospital_name' => $r->hospital?->name,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'tone' => $r->status->tone()->value,
            'usage_date' => $r->usage_date->toDateString(),
            'items_count' => $r->items_count,
            'total_amount' => (float) $r->total_amount,
        ], $summary);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $report = $this->find($id);
        $report->load(['hospital:id,name', 'items.product:id,product_code,product_name,spec,unit', 'items.lot:id,lot_no,expiry_date', 'approver:id,name']);

        $user = $request->user();

        return response()->json(['data' => [
            'id' => $report->id,
            'report_no' => $report->report_no,
            'hospital_name' => $report->hospital?->name,
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'tone' => $report->status->tone()->value,
            'usage_date' => $report->usage_date->toDateString(),
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'approved_at' => $report->approved_at?->toIso8601String(),
            'approver_name' => $report->approver?->name,
            'reject_reason' => $report->reject_reason,
            'total_amount' => (float) $report->total_amount,
            'can_submit' => $user->role === OrgType::HOSPITAL && $report->status->isEditable(),
            'can_approve' => $user->role === OrgType::HQ && $report->status->isApprovable(),
            'items' => $report->items->map(fn ($it) => [
                'id' => $it->id,
                'product_id' => $it->product_id,
                'product_code' => $it->product?->product_code,
                'product_name' => $it->product?->product_name,
                'spec' => $it->product?->spec,
                'unit' => $it->product?->unit,
                'lot_id' => $it->lot_id,
                'lot_no' => $it->lot?->lot_no,
                'expiry_date' => $it->lot?->expiry_date?->toDateString(),
                'qty' => $it->qty,
                'unit_price' => (float) $it->unit_price,
                'amount' => (float) $it->amount,
                'dept' => $it->dept,
                'procedure_info' => $it->procedure_info,
            ])->all(),
        ]]);
    }

    /** 병원: 사용분 등록(DRAFT). 모바일은 스캔한 Lot 을 그대로 명세로 올린다. */
    public function store(Request $request, DocumentNoService $docNo): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'usage_date' => ['required', 'date', new NotClosedMonth],
            'submit' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.lot_id' => ['required', 'integer', 'exists:product_lots,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.dept' => ['nullable', 'string', 'max:50'],
            'items.*.procedure_info' => ['nullable', 'string', 'max:255'],
            'items.*.scanned_barcode' => ['nullable', 'string', 'max:200'],
        ], [], ['usage_date' => '사용일', 'items' => '사용 명세']);

        // 등록 시점에 병원 재고를 미리 확인해 준다(실제 차감은 본사 승인 시).
        $shortages = $this->checkOnHand($user->org_id, $validated['items']);
        if ($shortages !== []) {
            return response()->json([
                'message' => '재고보다 많은 수량이 있습니다: '.implode(', ', $shortages),
            ], 422);
        }

        $report = DB::transaction(function () use ($validated, $docNo, $user) {
            $report = UsageReport::create([
                'report_no' => $docNo->next('UR', $user->organization->code, 'Ym'),
                'hospital_id' => $user->org_id,
                'status' => UsageStatus::DRAFT,
                'usage_date' => $validated['usage_date'],
                'total_amount' => 0,
                'created_by' => $user->id,
            ]);

            $total = 0.0;

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $unit = (float) ($product?->sales_price ?? 0);
                $amount = $unit * $item['qty'];
                $total += $amount;

                $report->items()->create([
                    'product_id' => $item['product_id'],
                    'lot_id' => $item['lot_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $unit,
                    'amount' => $amount,
                    'dept' => $item['dept'] ?? null,
                    'procedure_info' => $item['procedure_info'] ?? null,
                    'scanned_barcode' => $item['scanned_barcode'] ?? null,
                ]);
            }

            $report->update(['total_amount' => $total]);

            if ($validated['submit'] ?? false) {
                $report->update(['status' => UsageStatus::SUBMITTED, 'submitted_at' => now()]);
            }

            return $report;
        });

        $submitted = ($validated['submit'] ?? false) === true;

        return response()->json([
            'message' => $submitted
                ? "{$report->report_no} 전송 완료 — 본사 승인 대기"
                : "{$report->report_no} 임시저장되었습니다.",
            'id' => $report->id,
            'report_no' => $report->report_no,
            'status' => $report->status->value,
        ], 201);
    }

    /** 병원: 전송(DRAFT/REJECTED → SUBMITTED). */
    public function submit(Request $request, int $id): JsonResponse
    {
        $report = $this->find($id);

        abort_unless($request->user()->role === OrgType::HOSPITAL, 403, '병원 담당자만 전송할 수 있습니다.');

        if (! $report->status->isEditable()) {
            throw DomainException::conflict("전송할 수 없는 상태입니다: {$report->status->label()}");
        }

        $report->update(['status' => UsageStatus::SUBMITTED, 'submitted_at' => now()]);

        return $this->ok("{$report->report_no} 전송 완료 — 본사 승인 대기");
    }

    /** 병원: 임시저장 건 삭제. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $report = $this->find($id);

        abort_unless($request->user()->role === OrgType::HOSPITAL, 403, '병원 담당자만 삭제할 수 있습니다.');

        if ($report->status !== UsageStatus::DRAFT) {
            throw DomainException::conflict('임시저장 상태만 삭제할 수 있습니다.');
        }

        DB::transaction(function () use ($report) {
            $report->items()->delete();
            $report->delete();
        });

        return $this->ok('삭제되었습니다.');
    }

    /** 본사: 승인 — 재고 차감 + 정산 생성 + 자동보충 점검. */
    public function approve(Request $request, int $id, UsageApprovalService $service): JsonResponse
    {
        $report = $this->find($id);
        $service->approve($report, $request->user()->id);

        return $this->ok("{$report->report_no} 승인 완료 — 재고 차감·정산 반영");
    }

    /** 본사: 일괄 승인. 실패 건은 사유와 함께 돌려준다. */
    public function approveBulk(Request $request, UsageApprovalService $service): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer'],
        ], [], ['ids' => '대상 사용분']);

        $succeeded = 0;
        $failed = [];

        foreach ($validated['ids'] as $id) {
            $report = UsageReport::find($id);

            if ($report === null) {
                $failed[] = ['id' => $id, 'reason' => '문서를 찾을 수 없습니다.'];

                continue;
            }

            try {
                $service->approve($report, $request->user()->id);
                $succeeded++;
            } catch (\Throwable $e) {
                $failed[] = ['id' => $id, 'report_no' => $report->report_no, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => "{$succeeded}건 승인 완료".($failed === [] ? '' : ', '.count($failed).'건 실패'),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ]);
    }

    public function reject(Request $request, int $id, UsageApprovalService $service): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['reason' => '반려 사유']);

        $report = $this->find($id);
        $service->reject($report, $validated['reason'], $request->user()->id);

        return $this->ok("{$report->report_no} 반려 완료");
    }

    // ---------------------------------------------------------------- 헬퍼

    private function find(int $id): UsageReport
    {
        $report = UsageReport::find($id); // HospitalScope 적용

        abort_if($report === null, 404, '사용분 문서를 찾을 수 없습니다.');

        return $report;
    }

    /**
     * 등록 시점 재고 사전 검증. 같은 Lot 이 여러 줄에 나뉘어 있어도 합산해서 본다.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function checkOnHand(int $hospitalId, array $items): array
    {
        $needed = [];
        foreach ($items as $item) {
            $key = $item['product_id'].':'.$item['lot_id'];
            $needed[$key] = ($needed[$key] ?? 0) + (int) $item['qty'];
        }

        $shortages = [];

        foreach ($needed as $key => $qty) {
            [$productId, $lotId] = array_map('intval', explode(':', (string) $key));

            $onHand = (int) DB::table('stock_balances')
                ->where('org_id', $hospitalId)
                ->where('product_id', $productId)
                ->where('lot_id', $lotId)
                ->value('qty');

            if ($qty > $onHand) {
                $row = DB::table('product_lots as l')
                    ->join('products as p', 'p.id', '=', 'l.product_id')
                    ->where('l.id', $lotId)
                    ->select('p.product_name', 'l.lot_no')
                    ->first();

                $name = $row?->product_name ?? "제품#{$productId}";
                $lotNo = $row?->lot_no ?? "Lot#{$lotId}";
                $shortages[] = "{$name}(Lot {$lotNo}) 현재고 {$onHand} < 요청 {$qty}";
            }
        }

        return $shortages;
    }
}
