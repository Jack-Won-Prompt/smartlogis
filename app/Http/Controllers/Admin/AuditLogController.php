<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 감사 로그 조회 — 본사(HQ) 전용, 읽기 전용. audit_logs 는 수정/삭제하지 않는다.
 */
class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('admin.audit-logs');
    }

    public function data(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->filter([
                'action' => $request->string('action')->toString() ?: null,
                'entity' => $request->string('entity')->toString() ?: null,
                'date_from' => $request->string('date_from')->toString() ?: null,
                'date_to' => $request->string('date_to')->toString() ?: null,
            ])
            ->when($request->string('keyword')->toString(), fn ($q, $v) => $q->where('entity', 'like', "%{$v}%"))
            ->orderByDesc('id');

        $size = min(max($request->integer('size', 30), 1), 100);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        // 사용자명은 별도 조회(관계 접근자 대신 맵) — user_id 가 null(시스템 동작)일 수 있음.
        $names = User::query()
            ->whereIn('id', $p->getCollection()->pluck('user_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (AuditLog $r) => [
                'id' => $r->id,
                'created_at' => $r->created_at?->timezone('Asia/Seoul')->format('Y-m-d H:i:s'),
                'user_name' => $r->user_id ? ($names[$r->user_id] ?? '시스템') : '시스템',
                'action' => $r->action->value,
                'action_label' => $r->action->label(),
                'entity' => $r->entity,
                'entity_id' => $r->entity_id,
                'before' => $r->before ? json_encode($r->before, JSON_UNESCAPED_UNICODE) : null,
                'after' => $r->after ? json_encode($r->after, JSON_UNESCAPED_UNICODE) : null,
            ])->all(),
        ]);
    }
}
