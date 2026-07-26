<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 접속/페이지 접근 로그 조회 — 본사(HQ) 전용, 읽기 전용.
 */
class AccessLogController extends Controller
{
    public function index(): View
    {
        return view('admin.access-logs');
    }

    public function data(Request $request): JsonResponse
    {
        $kw = $request->string('keyword')->toString();
        $userId = $request->integer('user_id');
        $onlyGuest = $request->boolean('guest');

        $query = AccessLog::query()
            ->when($kw !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('path', 'like', "%{$kw}%")
                ->orWhere('ip', 'like', "%{$kw}%")))
            ->when($userId > 0, fn ($q) => $q->where('user_id', $userId))
            ->when($onlyGuest, fn ($q) => $q->whereNull('user_id'))
            ->when($request->string('date_from')->toString(), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->string('date_to')->toString(), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id');

        $size = min(max($request->integer('size', 50), 1), 500);
        $p = $query->paginate($size, ['*'], 'page', $request->integer('page', 1));

        $names = User::query()
            ->whereIn('id', $p->getCollection()->pluck('user_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return response()->json([
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'data' => $p->getCollection()->map(fn (AccessLog $r) => [
                'id' => $r->id,
                'created_at' => $r->created_at?->timezone('Asia/Seoul')->format('Y-m-d H:i:s'),
                'user_name' => $r->user_id ? ($names[$r->user_id] ?? '(삭제된 사용자)') : '게스트',
                'path' => $r->path,
                'route' => $r->route,
                'ip' => $r->ip,
                'user_agent' => $r->user_agent,
            ])->all(),
        ]);
    }
}
