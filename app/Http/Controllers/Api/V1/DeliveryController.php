<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgType;
use App\Enums\OutboundStatus;
use App\Exceptions\DomainException;
use App\Models\DeliveryPhoto;
use App\Models\DeliveryProof;
use App\Models\Outbound;
use App\Services\InboundService;
use App\Services\OutboundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 배송 처리 — 기사가 병원에서 물건을 넘기며 남기는 현장 증빙.
 *
 * 흐름: 병원 선택 → 출고지시서 QR 스캔 → 현장 사진(여러 장) → 인수자 서명 → 전송.
 *
 * 사진은 **찍는 즉시 한 장씩** 올린다. 마지막에 몰아서 보내면 현장 회선에서 오래 걸리고,
 * 실패하면 전부 날아간다. 서명은 전송 때 한 번만 올리고 그 시점에 배송이 완료된다.
 */
class DeliveryController extends ApiController
{
    /**
     * 스캔한 출고지시서 번호로 대상 출고를 찾는다.
     *
     * 병원을 함께 받아 **다른 병원 지시서를 찍었을 때 막는다.** 기사가 여러 병원을 도는데
     * 엉뚱한 문서에 서명을 받으면 실물과 장부가 어긋난다.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'hospital_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ], [], ['code' => '출고지시서 번호']);

        // QR 은 문서번호 평문이다(LabelService::outboundOrder).
        $code = trim($validated['code']);

        $outbound = $this->scoped($request)
            ->with(['warehouse:id,name', 'hospital:id,name', 'deliveryProof.photos'])
            ->withCount('items')
            ->where('outbound_no', $code)
            ->first();

        if ($outbound === null) {
            return response()->json([
                'message' => "출고지시서를 찾을 수 없습니다 ({$code}). 지시서의 QR 을 다시 찍어 주세요.",
            ], 404);
        }

        $hospitalId = $validated['hospital_id'] ?? null;
        if ($hospitalId !== null && $outbound->hospital_id !== (int) $hospitalId) {
            return response()->json([
                'message' => "선택한 병원의 지시서가 아닙니다. 이 지시서는 «{$outbound->hospital?->name}» 건입니다.",
            ], 422);
        }

        return response()->json(['data' => $this->detail($outbound)]);
    }

    /** 현장 사진 한 장 업로드. 증빙이 없으면 이때 만든다. */
    public function storePhoto(Request $request, int $id): JsonResponse
    {
        $outbound = $this->deliverable($request, $id);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'file.mimes' => '사진 파일만 올릴 수 있습니다 (jpg · png · webp).',
            'file.max' => '사진이 너무 큽니다 (최대 20MB).',
            'file.required' => '사진이 없습니다.',
        ], ['file' => '사진']);

        $proof = $this->proofFor($outbound, $request);
        $file = $request->file('file');
        $path = $file->store('deliveries', 'public');

        if (! is_string($path) || $path === '') {
            return response()->json([
                'message' => '사진을 저장하지 못했습니다. 관리자에게 문의하세요.',
            ], 500);
        }

        $photo = $proof->photos()->create([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'data' => $this->photo($photo),
            'photo_count' => $proof->photos()->count(),
        ], 201);
    }

    /** 잘못 찍은 사진 삭제. */
    public function destroyPhoto(Request $request, int $photoId): JsonResponse
    {
        $photo = DeliveryPhoto::with('proof.outbound')->find($photoId);

        abort_if($photo === null, 404, '사진을 찾을 수 없습니다.');

        $outbound = $photo->proof?->outbound;
        abort_if($outbound === null, 404, '사진을 찾을 수 없습니다.');
        $this->deliverable($request, $outbound->id);

        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        return $this->ok('사진을 삭제했습니다.');
    }

    /**
     * 전송 — 인수 서명을 붙이고 배송을 완료한다.
     *
     * 서명을 받았다는 것은 병원이 실제로 물건을 받았다는 뜻이므로, 여기서 배송 완료까지
     * 처리한다(병원 입고 문서 생성 + 재고 반영). 이미 완료된 건이면 증빙만 붙인다.
     */
    public function complete(
        Request $request,
        int $id,
        OutboundService $service,
        InboundService $inboundService,
    ): JsonResponse {
        $outbound = $this->deliverable($request, $id);

        $validated = $request->validate([
            'signature' => ['required', 'file', 'max:5120', 'mimes:png,jpg,jpeg'],
            'signer_name' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:500'],
        ], [
            'signature.required' => '인수자 서명이 필요합니다.',
            'signature.mimes' => '서명 이미지 형식이 올바르지 않습니다.',
        ], ['signature' => '서명', 'signer_name' => '인수자']);

        $proof = $this->proofFor($outbound, $request);

        if ($proof->photos()->count() === 0) {
            return response()->json([
                'message' => '배송 사진을 최소 한 장 이상 촬영해 주세요.',
            ], 422);
        }

        $path = $request->file('signature')->store('signatures', 'public');

        if (! is_string($path) || $path === '') {
            return response()->json([
                'message' => '서명을 저장하지 못했습니다. 관리자에게 문의하세요.',
            ], 500);
        }

        // 이전 서명이 있으면(재전송) 파일을 정리한다.
        if ($proof->hasSignature()) {
            Storage::disk('public')->delete($proof->signature_path);
        }

        DB::transaction(function () use ($proof, $path, $validated, $request) {
            $proof->update([
                'signature_path' => $path,
                'signer_name' => $validated['signer_name'] ?? null,
                'memo' => $validated['memo'] ?? null,
                'delivered_by' => $request->user()->id,
                'delivered_at' => now(),
            ]);
        });

        // 배송중이면 여기서 실제 배송 완료까지 끝낸다.
        $completed = false;
        if ($outbound->status === OutboundStatus::SHIPPED) {
            try {
                $service->deliver($outbound, $inboundService, $request->user()->id);
                $completed = true;
            } catch (DomainException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json([
            'message' => $completed
                ? "{$outbound->outbound_no} 배송 완료 — 증빙이 저장되고 병원 재고에 반영되었습니다."
                : "{$outbound->outbound_no} 배송 증빙을 저장했습니다.",
            'completed' => $completed,
            'data' => $this->detail($outbound->fresh(['warehouse', 'hospital', 'deliveryProof.photos'])),
        ]);
    }

    // ────────────────────────────────────────────────────────────── 내부

    /**
     * 조회 범위 — 창고는 자기 창고 건, 병원은 자기 병원 건(HospitalScope 가 이미 좁힌다).
     *
     * @return Builder<Outbound>
     */
    private function scoped(Request $request)
    {
        $user = $request->user();

        return Outbound::query()->when(
            $user->role === OrgType::WAREHOUSE,
            fn ($q) => $q->where('warehouse_id', $user->org_id),
        );
    }

    /** 배송 증빙을 남길 수 있는 출고인지. */
    private function deliverable(Request $request, int $id): Outbound
    {
        $outbound = $this->scoped($request)->find($id);

        abort_if($outbound === null, 404, '출고지시서를 찾을 수 없습니다.');

        // 아직 창고를 떠나지 않은 건에 인수 서명을 받을 수는 없다.
        abort_if(
            in_array($outbound->status, [
                OutboundStatus::DRAFT,
                OutboundStatus::APPROVED,
                OutboundStatus::PICKING,
                OutboundStatus::CANCELED,
            ], true),
            422,
            "아직 배송을 시작하지 않은 지시서입니다 (현재: {$outbound->status->label()}).",
        );

        return $outbound;
    }

    private function proofFor(Outbound $outbound, Request $request): DeliveryProof
    {
        return DeliveryProof::firstOrCreate(
            ['outbound_id' => $outbound->id],
            ['delivered_by' => $request->user()->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Outbound $outbound): array
    {
        $proof = $outbound->deliveryProof;

        return [
            'id' => $outbound->id,
            'outbound_no' => $outbound->outbound_no,
            'warehouse_name' => $outbound->warehouse?->name,
            'hospital_id' => $outbound->hospital_id,
            'hospital_name' => $outbound->hospital?->name,
            'status' => $outbound->status->value,
            'status_label' => $outbound->status->label(),
            'tone' => $outbound->status->tone()->value,
            'planned_date' => $outbound->planned_date?->toDateString(),
            'items_count' => $outbound->items_count ?? $outbound->items()->count(),
            // 이미 서명까지 받은 건이면 앱이 "다시 보내시겠습니까" 를 물을 수 있어야 한다.
            'is_delivered' => $outbound->status === OutboundStatus::DELIVERED,
            'signed_at' => $proof?->delivered_at?->toIso8601String(),
            'signer_name' => $proof?->signer_name,
            'signature_url' => $proof?->signatureUrl(),
            'photos' => $proof !== null
                ? $proof->photos->map(fn (DeliveryPhoto $p) => $this->photo($p))->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photo(DeliveryPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'url' => $photo->url(),
            'file_name' => $photo->file_name,
            'created_at' => $photo->created_at?->toIso8601String(),
        ];
    }
}
