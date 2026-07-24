<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * 본사 초대 수락 — 초대 링크(token)로 진입해 최초 비밀번호를 설정하면 계정이 활성화된다.
 */
class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->validInvitationOrFail($token);

        return view('auth.invitation', ['invitation' => $invitation]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->validInvitationOrFail($token);

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['password' => '비밀번호']);

        DB::transaction(function () use ($invitation, $request) {
            $user = User::create([
                'login_id' => $invitation->email, // 이메일이 로그인 계정
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role,
                'org_id' => $invitation->org_id,
                'status' => UserStatus::ACTIVE,
                'password' => Hash::make($request->string('password')->toString()),
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => $invitation->invited_by,
            ]);

            $invitation->forceFill([
                'accepted_at' => now(),
                'user_id' => $user->id,
            ])->save();
        });

        return redirect()->route('login')->with(
            'status',
            '계정이 활성화되었습니다. 설정한 비밀번호로 로그인하세요.'
        );
    }

    private function validInvitationOrFail(string $token): Invitation
    {
        $invitation = Invitation::with('organization')->where('token', $token)->first();

        abort_if($invitation === null, 404, '유효하지 않은 초대 링크입니다.');
        abort_if($invitation->isAccepted(), 410, '이미 사용된 초대 링크입니다.');
        abort_if($invitation->isExpired(), 410, '만료된 초대 링크입니다. 본사에 재발송을 요청하세요.');

        return $invitation;
    }
}
