<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AccessLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 접속/페이지 접근 로그 — 메인(게스트) 포함 모든 웹 페이지 GET 접근을 기록한다.
 * 자산·AJAX·데이터/브로드캐스트 엔드포인트는 제외한다. 기록 실패는 페이지에 영향을 주지 않는다.
 */
class LogAccess
{
    /** @var list<string> 경로 접두사 제외 목록 */
    private const SKIP_PREFIX = [
        'livewire', 'broadcasting', 'up', 'storage', 'build', 'vendor',
        'js/', 'css/', 'images/', 'fonts/', 'favicon',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->shouldLog($request, $response)) {
                AccessLog::create([
                    'user_id' => $request->user()?->id,
                    'method' => $request->method(),
                    'path' => '/'.ltrim($request->path(), '/'),
                    'route' => $request->route()?->getName(),
                    'ip' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // 로깅 실패는 무시(페이지 정상 응답 우선)
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false; // 페이지 접근만(액션은 감사로그가 담당)
        }
        if ($request->ajax() || $request->wantsJson() || $request->hasHeader('X-Requested-With')) {
            return false;
        }

        $path = ltrim($request->path(), '/');
        foreach (self::SKIP_PREFIX as $prefix) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/') || str_starts_with($path, $prefix)) {
                return false;
            }
        }
        if (str_contains($path, '/data') || str_ends_with($path, '/export')) {
            return false; // 그리드 데이터/엑셀 엔드포인트
        }

        // HTML 응답만 기록(리다이렉트·파일 다운로드 등 제외)
        $ct = (string) $response->headers->get('Content-Type', '');

        return $ct === '' || str_contains($ct, 'text/html');
    }
}
