<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * 목록 상단 요약에 쓰는 지표를 만든다.
     *
     * 앱은 현재 페이지(20건)만 갖고 있어 스스로 합계를 낼 수 없다. 그래서 요약은
     * **전체 조건 기준**으로 서버가 계산해 내려준다. 라벨·단위·톤까지 서버가 정하는
     * 이유는 화면마다 의미가 다르기 때문이다(같은 "건수"라도 미달이면 crit).
     *
     * @param  string  $tone  ok|warn|crit|info|hold — 앱은 이 값으로 색만 고른다.
     */
    protected function stat(
        string $label,
        int|float|string $value,
        ?string $unit = null,
        string $tone = 'hold',
    ): array {
        return [
            'label' => $label,
            'value' => is_string($value) ? $value : (string) $value,
            'unit' => $unit,
            'tone' => $tone,
        ];
    }

    /**
     * 분포 막대 한 조각. 요약 바 아래에 상태 비중을 한 줄로 보여 준다.
     */
    protected function segment(string $label, int $count, string $tone): array
    {
        return ['label' => $label, 'count' => $count, 'tone' => $tone];
    }

    /**
     * 요약 바에 넣을 금액 표기.
     *
     * 정산·사용분 금액은 억 단위가 예사라 원 단위로 다 쓰면 요약 칸을 넘친다.
     * 앱의 Fmt.money 축약과 같은 규칙(억/만)을 쓴다.
     */
    protected function wonShort(float $amount): string
    {
        $abs = abs($amount);

        if ($abs >= 100_000_000) {
            return number_format($amount / 100_000_000, 1).'억';
        }
        if ($abs >= 10_000) {
            return number_format($amount / 10_000).'만';
        }

        return number_format($amount);
    }

    /**
     * 상태별 건수를 세어 요약을 만든다 — 문서 목록(입고/출고/사용분/정산) 공통.
     *
     * 목록을 스크롤하지 않고도 "검수 대기가 몇 건인지" 를 알 수 있어야 한다.
     * 페이징 전 쿼리로 세므로 **전체 조건 기준**이다.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, array{0: string, 1: string}>  $map  status => [라벨, 톤]
     * @param  array<int, array<string, mixed>>  $leadStats  세그먼트 앞에 붙일 지표
     */
    protected function statusSummary(
        $query,
        array $map,
        array $leadStats = [],
        string $column = 'status',
    ): array {
        // 정렬은 집계에 불필요하고 일부 DB 에서 GROUP BY 와 충돌한다.
        $counts = (clone $query)
            ->reorder()
            ->select($column, DB::raw('COUNT(*) as c'))
            ->groupBy($column)
            ->pluck('c', $column);

        $segments = [];
        foreach ($map as $status => [$label, $tone]) {
            $segments[] = $this->segment($label, (int) ($counts[$status] ?? 0), $tone);
        }

        return ['stats' => $leadStats, 'segments' => $segments];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  callable(mixed): array<string, mixed>  $mapper
     * @param  array<string, mixed>|null  $summary  ['stats' => [...], 'segments' => [...]]
     */
    protected function paged(
        LengthAwarePaginator $paginator,
        callable $mapper,
        ?array $summary = null,
    ): JsonResponse {
        return response()->json(array_filter([
            'data' => array_values($paginator->getCollection()->map($mapper)->all()),
            'summary' => $summary,
            'meta' => [
                'page' => $paginator->currentPage(),
                'size' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->currentPage() < $paginator->lastPage(),
            ],
        ], fn ($v) => $v !== null));
    }

    /**
     * 쿼리빌더(비 Eloquent) 결과를 같은 봉투로 감싼다.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $summary
     */
    protected function pagedRaw(
        array $rows,
        int $total,
        int $page,
        int $size,
        ?array $summary = null,
    ): JsonResponse {
        $lastPage = max(1, (int) ceil($total / $size));

        return response()->json(array_filter([
            'data' => $rows,
            'summary' => $summary,
            'meta' => [
                'page' => $page,
                'size' => $size,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
            ],
        ], fn ($v) => $v !== null));
    }

    protected function ok(string $message, array $extra = []): JsonResponse
    {
        return response()->json(['message' => $message] + $extra);
    }
}
