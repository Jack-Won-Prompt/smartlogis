<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrgType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 역할 기반 접근 제어. 라우트에서 `role:HQ`, `role:HQ,WAREHOUSE` 형태로 지정한다.
 *
 * 데이터 스코프(어느 조직 것을 보느냐)는 Global Scope 가 담당하고,
 * 이 미들웨어는 "이 화면 자체에 들어올 수 있는 역할인가"만 판정한다.
 */
class EnsureRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $allowed = array_map(
            static fn (string $role): OrgType => OrgType::from($role),
            $roles
        );

        if (! $user->hasRole(...$allowed)) {
            abort(403, '이 화면에 접근할 권한이 없습니다.');
        }

        return $next($request);
    }
}
