<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrgType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 조직 4종과 대표 로그인 계정을 생성한다. 모든 계정 비밀번호는 "password".
 *
 * 로그인 ID 규칙: hq / wh1 / seoul(병원 코드) / sup-samsung(공급사)
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // 본사
        $hq = $this->org(OrgType::HQ, 'HQ', '삼에스 본사');
        $this->user($hq, 'hq', '본사 관리자');

        // 물류창고
        $wh = $this->org(OrgType::WAREHOUSE, 'WH-CENTRAL', '중앙 물류창고');
        $this->user($wh, 'wh1', '창고 담당자');

        // 거점병원 5곳
        $hospitals = [
            ['SEOUL', '서울대학교병원', 'seoul'],
            ['SEVERANCE', '세브란스병원', 'severance'],
            ['ASAN', '서울아산병원', 'asan'],
            ['SAMSUNG', '삼성서울병원', 'samsungmc'],
            ['BUSAN', '부산대학교병원', 'busan'],
        ];
        foreach ($hospitals as [$code, $name, $loginId]) {
            $org = $this->org(OrgType::HOSPITAL, "HOSP-{$code}", $name, hpid: fake()->numerify('########'));
            $this->user($org, $loginId, "{$name} 담당자");
        }

        // 공급사 4곳
        $suppliers = [
            ['SAMSUNG-MED', '삼성메디슨', 'sup-samsung'],
            ['MEDTRONIC', '메드트로닉코리아', 'sup-medtronic'],
            ['JNJ', '존슨앤드존슨메디칼', 'sup-jnj'],
            ['STRYKER', '스트라이커코리아', 'sup-stryker'],
        ];
        foreach ($suppliers as [$code, $name, $loginId]) {
            $org = $this->org(OrgType::SUPPLIER, "SUP-{$code}", $name);
            $this->user($org, $loginId, "{$name} 담당자");
        }
    }

    private function org(OrgType $type, string $code, string $name, ?string $hpid = null): Organization
    {
        return Organization::create([
            'org_type' => $type,
            'code' => $code,
            'name' => $name,
            'biz_reg_no' => fake()->numerify('###-##-#####'),
            'hpid_no' => $hpid,
            'address' => fake()->address(),
            'tel' => fake()->numerify('02-###-####'),
            'is_active' => true,
        ]);
    }

    private function user(Organization $org, string $loginId, string $name): User
    {
        return User::create([
            'login_id' => $loginId,
            'email' => $loginId.'@smartlogis.test',
            'name' => $name,
            'role' => $org->org_type,
            'org_id' => $org->id,
            'status' => UserStatus::ACTIVE,
            'tel' => fake()->numerify('010-####-####'),
            'password' => Hash::make('password'),
            'is_active' => true,
            'approved_at' => now(),
        ]);
    }
}
