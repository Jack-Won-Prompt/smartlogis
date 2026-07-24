<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\OrgType;
use App\Models\Organization;

/**
 * 거래처 마스터 엑셀 업로드. 코드 기준 upsert. 유형은 라벨/코드 모두 허용.
 */
class OrganizationsImport extends BaseRowImport
{
    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            '유형' => ['required', 'string'],
            '코드' => ['required', 'string', 'max:30'],
            '거래처명' => ['required', 'string', 'max:255'],
            '사업자번호' => ['nullable', 'string', 'max:20'],
            '요양기관번호' => ['nullable', 'string', 'max:20'],
            '연락처' => ['nullable', 'string', 'max:30'],
            '주소' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function attributes(): array
    {
        return ['유형' => '유형', '코드' => '코드', '거래처명' => '거래처명'];
    }

    /** @param  array<string, mixed>  $row */
    protected function storeRow(array $row): void
    {
        $type = $this->orgType((string) $row['유형']);

        Organization::updateOrCreate(
            ['code' => (string) $row['코드']],
            [
                'org_type' => $type,
                'name' => (string) $row['거래처명'],
                'biz_reg_no' => $row['사업자번호'] ?? null,
                'hpid_no' => $row['요양기관번호'] ?? null,
                'tel' => $row['연락처'] ?? null,
                'address' => $row['주소'] ?? null,
                'is_active' => true,
            ]
        );
    }

    private function orgType(string $v): OrgType
    {
        return match (trim($v)) {
            '본사', 'HQ' => OrgType::HQ,
            '창고', '물류창고', 'WAREHOUSE' => OrgType::WAREHOUSE,
            '공급사', 'SUPPLIER' => OrgType::SUPPLIER,
            default => OrgType::HOSPITAL,
        };
    }

    /** @return array<int, string> 검증 실패용 컬럼 순서 */
    public static function columns(): array
    {
        return ['유형', '코드', '거래처명', '사업자번호', '요양기관번호', '연락처', '주소'];
    }
}
