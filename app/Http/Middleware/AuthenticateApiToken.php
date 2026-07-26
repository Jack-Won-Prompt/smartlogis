<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 모바일 API Bearer 토큰 인증.
 *
 * 인증에 성공하면 기본(web) 가드에도 사용자를 주입한다. Global Scope
 * (HospitalScope / SupplierProductScope / OrgLocationScope)가 Auth::user() 를
 * 기준으로 데이터 스코프를 강제하기 때문에, 이 주입이 없으면 모바일 요청만
 * 스코프를 우회하게 된다. 웹과 동일한 차단 지점을 쓰는 것이 핵심이다.
 */
class AuthenticateApiToken
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if ($plain === null || $plain === '') {
            return $this->unauthorized('인증 토큰이 없습니다. 다시 로그인해 주세요.');
        }

        $token = ApiToken::query()
            ->valid()
            ->with('user.organization')
            ->where('token_hash', ApiToken::hash($plain))
            ->first();

        if ($token === null) {
            return $this->unauthorized('토큰이 만료되었거나 유효하지 않습니다. 다시 로그인해 주세요.');
        }

        $user = $token->user;

        if ($user === null || ! $user->is_active || $user->status !== UserStatus::ACTIVE) {
            return $this->unauthorized('사용할 수 없는 계정입니다. 본사 관리자에게 문의하세요.');
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('api_token', $token);

        // 마지막 사용 시각은 하루 1회만 기록해 매 요청 쓰기를 피한다.
        if ($token->last_used_at === null || $token->last_used_at->isBefore(now()->subHour())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json(['message' => $message], 401);
    }
}
