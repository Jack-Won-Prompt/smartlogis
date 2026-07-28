<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 계정/데이터 삭제 요청 — 공개(로그인 불필요). 플레이스토어 요건.
 * 제출 시 요청을 기록하고 관리자가 확인·처리한다.
 */
class AccountDeletionController extends Controller
{
    public function create(): View
    {
        return view('account.delete');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|string|max:40',
            'request_type' => 'required|in:ACCOUNT,DATA',
            'reason' => 'nullable|string|max:2000',
            'agree' => 'accepted',
        ], [
            'name.required' => '이름을 입력해 주세요.',
            'email.required' => '가입하신 이메일을 입력해 주세요.',
            'email.email' => '올바른 이메일 형식이 아닙니다.',
            'request_type.required' => '요청 유형을 선택해 주세요.',
            'agree.accepted' => '안내 사항 확인에 동의해 주세요.',
        ]);

        $req = AccountDeletionRequest::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'request_type' => $data['request_type'],
            'reason' => $data['reason'] ?? null,
            'status' => 'RECEIVED',
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        // 관리자 알림(메일) — 실패해도 접수는 유지(운영 메일 미설정 대비).
        try {
            Log::channel('single')->info('[account-deletion] 접수 #'.$req->id.' '.$req->email.' ('.$req->request_type.')');
        } catch (\Throwable $e) {
            // 무시 — 접수 자체는 DB 에 기록됨
        }

        return redirect()
            ->route('account.delete')
            ->with('deletion_done', $req->id);
    }
}
