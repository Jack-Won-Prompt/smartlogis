<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 모바일 API 공통 베이스. 모든 리스트 응답은 동일한 페이징 봉투를 쓴다.
 * (Flutter 쪽 `PagedResponse<T>` 와 1:1 대응)
 */
abstract class ApiController extends Controller
{
    /** 모바일은 무한 스크롤이므로 기본 20건, 최대 100건. */
    protected function pageSize(Request $request, int $default = 20): int
    {
        return min(max($request->integer('size', $default), 1), 100);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  callable(mixed): array<string, mixed>  $mapper
     */
    protected function paged(LengthAwarePaginator $paginator, callable $mapper): JsonResponse
    {
        return response()->json([
            'data' => array_values($paginator->getCollection()->map($mapper)->all()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'size' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->currentPage() < $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * 쿼리빌더(비 Eloquent) 결과를 같은 봉투로 감싼다.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function pagedRaw(array $rows, int $total, int $page, int $size): JsonResponse
    {
        $lastPage = max(1, (int) ceil($total / $size));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'size' => $size,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
            ],
        ]);
    }

    protected function ok(string $message, array $extra = []): JsonResponse
    {
        return response()->json(['message' => $message] + $extra);
    }
}
