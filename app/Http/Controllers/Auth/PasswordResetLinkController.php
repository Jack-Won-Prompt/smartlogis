<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * 비밀번호 찾기 — 등록 이메일로 재설정 링크 발송.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email:filter'], // filter: FILTER_VALIDATE_EMAIL 로 CRLF 거부(CVE-2026-48019 완화)
        ], [], ['email' => '이메일']);

        // ACTIVE 계정만 재설정 대상(가입 대기/초대 상태는 별도 흐름).
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', '재설정 링크를 이메일로 보냈습니다. 메일함을 확인해 주세요.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
