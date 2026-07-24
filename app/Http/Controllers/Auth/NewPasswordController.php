<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * 재설정 링크로 진입 → 새 비밀번호 저장.
 */
class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email:filter'], // filter: FILTER_VALIDATE_EMAIL 로 CRLF 거부(CVE-2026-48019 완화)
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [], ['email' => '이메일', 'password' => '비밀번호']);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')->toString()),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', '비밀번호가 변경되었습니다. 새 비밀번호로 로그인하세요.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
