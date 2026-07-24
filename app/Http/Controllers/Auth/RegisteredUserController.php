<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\OrgType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * 거래처 자체 가입 신청. 조직(비활성)과 담당자 계정(PENDING)을 함께 생성하고,
 * 본사 승인 후 활성화된다(본사 승인 UI 는 Phase 3 기준정보/사용자).
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in([OrgType::HOSPITAL->value, OrgType::SUPPLIER->value, OrgType::WAREHOUSE->value])],
            'org_name' => ['required', 'string', 'max:255'],
            'biz_reg_no' => ['nullable', 'string', 'max:20'],
            'tel' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:50'],
            'login_id' => ['required', 'string', 'alpha_dash', 'min:4', 'max:50', 'unique:users,login_id'],
            'email' => ['required', 'email:filter', 'max:255', 'unique:users,email'], // filter: CRLF 거부(CVE-2026-48019 완화)
            'password' => ['required', 'confirmed', Password::min(8)],
            'agree' => ['accepted'],
        ], [], [
            'role' => '거래처 유형',
            'org_name' => '거래처명',
            'name' => '담당자명',
            'login_id' => '아이디',
            'email' => '이메일',
            'password' => '비밀번호',
            'agree' => '약관 동의',
        ]);

        DB::transaction(function () use ($validated) {
            $role = OrgType::from($validated['role']);

            $org = Organization::create([
                'org_type' => $role,
                'code' => $this->uniqueOrgCode($role),
                'name' => $validated['org_name'],
                'biz_reg_no' => $validated['biz_reg_no'] ?? null,
                'tel' => $validated['tel'] ?? null,
                'is_active' => false, // 승인 시 활성화
            ]);

            User::create([
                'login_id' => $validated['login_id'],
                'email' => $validated['email'],
                'name' => $validated['name'],
                'role' => $role,
                'org_id' => $org->id,
                'status' => UserStatus::PENDING,
                'tel' => $validated['tel'] ?? null,
                'password' => Hash::make($validated['password']),
                'is_active' => false,
            ]);
        });

        return redirect()->route('login')->with(
            'status',
            '가입 신청이 접수되었습니다. 본사 승인이 완료되면 이메일로 안내드립니다.'
        );
    }

    private function uniqueOrgCode(OrgType $role): string
    {
        $prefix = match ($role) {
            OrgType::HOSPITAL => 'HOSP',
            OrgType::SUPPLIER => 'SUP',
            OrgType::WAREHOUSE => 'WH',
            OrgType::HQ => 'HQ',
        };

        do {
            $code = $prefix.'-'.strtoupper(Str::random(6));
        } while (Organization::where('code', $code)->exists());

        return $code;
    }
}
