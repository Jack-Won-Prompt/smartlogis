<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataResetService;
use Illuminate\Http\JsonResponse;

/**
 * 시스템 관리 — 본사(HQ) 전용. 업무 데이터 초기화 등 위험 작업.
 */
class SystemController extends Controller
{
    /** 모든 업무 데이터 삭제(사용자·소속 조직만 유지). */
    public function resetData(DataResetService $service): JsonResponse
    {
        $deleted = $service->reset();

        return response()->json([
            'message' => '모든 업무 데이터가 초기화되었습니다. (사용자·소속 조직은 유지)',
            'deleted' => array_sum($deleted),
            'detail' => $deleted,
        ]);
    }
}
