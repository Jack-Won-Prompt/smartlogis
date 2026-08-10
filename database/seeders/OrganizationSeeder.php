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
 * 멱등(idempotent): 조직은 code, 사용자는 email 기준 upsert 라 여러 번 실행해도 중복이 없고,
 * 재실행 시 테스트 계정 비밀번호를 "password" 로 재설정한다(운영 테스트 시드용).
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

        // 라이프사이언스(요청) — 병원 대신 물품 요청·사용확정·반납을 수행
        $life = $this->org(OrgType::LIFE, 'LIFE-SEOUL', '라이프사이언스 서울');
        $this->user($life, 'life1', '라이프사이언스 담당자');

        // 거점병원 5곳
        $hospitals = [
            ['SEOUL', '서울대학교병원', 'seoul'],
            ['SEVERANCE', '세브란스병원', 'severance'],
            ['ASAN', '서울아산병원', 'asan'],
            ['SAMSUNG', '삼성서울병원', 'samsungmc'],
            ['BUSAN', '부산대학교병원', 'busan'],
        ];
        foreach ($hospitals as [$code, $name, $loginId]) {
            // 요양기관번호: code 기반 결정적 8자리(faker 미사용 — 운영 --no-dev 실행 가능)
            $hpid = (string) (11000000 + abs(crc32($code)) % 8999999);
            $org = $this->org(OrgType::HOSPITAL, "HOSP-{$code}", $name, hpid: $hpid);
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
        // 결정적 값(faker 미사용 — 운영 --no-dev 환경에서도 실행 가능)
        $d = abs(crc32($code));

        return Organization::firstOrCreate(
            ['code' => $code],
            [
                'org_type' => $type,
                'name' => $name,
                'biz_reg_no' => sprintf('%03d-%02d-%05d', $d % 1000, $d % 100, $d % 100000),
                'hpid_no' => $hpid,
                'address' => '서울특별시 강남구 테헤란로 123',
                'tel' => '02-0000-0000',
                'is_active' => true,
            ]
        );
    }

    private function user(Organization $org, string $loginId, string $name): User
    {
        return User::updateOrCreate(
            ['email' => $loginId.'@smartlogis.test'],
            [
                'login_id' => $loginId,
                'name' => $name,
                'role' => $org->org_type,
                'org_id' => $org->id,
                'status' => UserStatus::ACTIVE,
                'tel' => '010-0000-0000',
                'password' => Hash::make('password'),
                'is_active' => true,
                'approved_at' => now(),
            ]
        );
    }
}
