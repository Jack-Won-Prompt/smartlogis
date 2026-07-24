<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\OrgType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 사용자 마스터 엑셀 업로드. 아이디 기준 upsert. 소속은 조직코드로 매핑, 역할은 라벨/코드 허용.
 * 신규 계정은 임시 비밀번호를 해시로 발급한다(엑셀에는 노출하지 않음).
 */
class UsersImport extends BaseRowImport
{
    /** @var array<string, int> */
    private array $orgCache = [];

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            '아이디' => ['required', 'string', 'alpha_dash', 'max:50'],
            '이름' => ['required', 'string', 'max:50'],
            '역할' => ['required', 'string'],
            '소속코드' => ['required', 'string'],
            '이메일' => ['nullable', 'email', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function attributes(): array
    {
        return ['아이디' => '아이디', '이름' => '이름', '역할' => '역할', '소속코드' => '소속코드'];
    }

    /** @param  array<string, mixed>  $row */
    protected function storeRow(array $row): void
    {
        $orgId = $this->orgId((string) $row['소속코드']);
        if ($orgId === 0) {
            throw new \RuntimeException("소속코드에 해당하는 거래처가 없습니다: {$row['소속코드']}");
        }
        $role = $this->role((string) $row['역할']);

        User::updateOrCreate(
            ['login_id' => (string) $row['아이디']],
            [
                'name' => (string) $row['이름'],
                'email' => $row['이메일'] ?? null,
                'role' => $role,
                'org_id' => $orgId,
                'status' => UserStatus::ACTIVE,
                'is_active' => true,
                'approved_at' => now(),
                'password' => Hash::make(Str::password(10, symbols: false)),
            ]
        );
    }

    private function orgId(string $code): int
    {
        return $this->orgCache[$code] ??= (int) Organization::query()->where('code', $code)->value('id');
    }

    private function role(string $v): OrgType
    {
        return match (trim($v)) {
            '본사', 'HQ' => OrgType::HQ,
            '창고', '물류창고', 'WAREHOUSE' => OrgType::WAREHOUSE,
            '공급사', 'SUPPLIER' => OrgType::SUPPLIER,
            default => OrgType::HOSPITAL,
        };
    }

    /** @return array<int, string> */
    public static function columns(): array
    {
        return ['아이디', '이름', '역할', '소속코드', '이메일'];
    }
}
