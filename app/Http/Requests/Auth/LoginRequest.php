<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 이메일 + 비밀번호 인증. 비활성(status != ACTIVE) 계정은 거부한다.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:filter'], // filter: CRLF 거부(CVE-2026-48019 완화)
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'status' => UserStatus::ACTIVE->value,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $this->failureMessage(),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }

    /**
     * 로그인 실패 사유를 상태에 맞게 한국어로 안내한다(DESIGN.md §5.8).
     */
    private function failureMessage(): string
    {
        $user = User::query()
            ->where('email', $this->string('email')->toString())
            ->first();

        return match ($user?->status) {
            UserStatus::PENDING => '가입 승인 대기 중인 계정입니다. 본사 승인 후 이용할 수 있습니다.',
            UserStatus::INVITED => '초대받은 계정입니다. 이메일의 초대 링크에서 비밀번호를 먼저 설정하세요.',
            UserStatus::SUSPENDED => '정지된 계정입니다. 본사 관리자에게 문의하세요.',
            default => trans('auth.failed'),
        };
    }
}
