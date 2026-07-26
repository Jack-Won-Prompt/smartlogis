<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 모바일 로그인/로그아웃. 웹과 달리 로그인 ID 또는 이메일 둘 다 허용한다
 * (창고·수술실에서 이메일 전체를 타이핑하기 어렵다는 현장 요건).
 */
class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login_id' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
        ], [], [
            'login_id' => '로그인 ID',
            'password' => '비밀번호',
        ]);

        $key = 'api-login:'.Str::lower($validated['login_id']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'login_id' => "로그인 시도가 많습니다. {$seconds}초 후 다시 시도해 주세요.",
            ]);
        }

        $identifier = $validated['login_id'];

        $user = User::query()
            ->with('organization')
            ->where('login_id', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'login_id' => '로그인 ID 또는 비밀번호가 올바르지 않습니다.',
            ]);
        }

        if ($user->status !== UserStatus::ACTIVE || ! $user->is_active) {
            throw ValidationException::withMessages([
                'login_id' => match ($user->status) {
                    UserStatus::PENDING => '가입 승인 대기 중인 계정입니다. 본사 승인 후 이용할 수 있습니다.',
                    UserStatus::INVITED => '초대받은 계정입니다. 웹에서 비밀번호를 먼저 설정하세요.',
                    UserStatus::SUSPENDED => '정지된 계정입니다. 본사 관리자에게 문의하세요.',
                    default => '사용할 수 없는 계정입니다.',
                },
            ]);
        }

        RateLimiter::clear($key);

        $issued = ApiToken::issue(
            $user,
            $validated['device_name'] ?? '모바일 기기',
            $validated['platform'] ?? null,
        );

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'token' => $issued['plain'],
            'expires_at' => $issued['token']->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('organization');

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        if ($token instanceof ApiToken) {
            $token->delete();
        }

        return $this->ok('로그아웃되었습니다.');
    }

    /** 푸시 토큰 등록/갱신 (알림 발송 준비용). */
    public function pushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->attributes->get('api_token');

        if ($token instanceof ApiToken) {
            $token->update(['push_token' => $validated['push_token']]);
        }

        return $this->ok('푸시 토큰이 등록되었습니다.');
    }

    /**
     * 역할별 메뉴 구성의 근거가 되는 사용자 정보.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'login_id' => $user->login_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'org_id' => $user->org_id,
            'org_code' => $user->organization?->code,
            'org_name' => $user->organization?->name,
            'tel' => $user->tel,
        ];
    }
}
