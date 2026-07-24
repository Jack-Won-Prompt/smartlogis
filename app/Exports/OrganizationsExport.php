<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Organization;

/** @extends BaseQueryExport<Organization> */
class OrganizationsExport extends BaseQueryExport
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['유형', '코드', '거래처명', '사업자번호', '요양기관번호', '연락처', '주소', '사용자수', '사용여부'];
    }

    /**
     * @param  Organization  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->org_type->label(),
            $row->code,
            $row->name,
            $row->biz_reg_no,
            $row->hpid_no,
            $row->tel,
            $row->address,
            $row->users_count ?? 0,
            $row->is_active ? '사용' : '중지',
        ];
    }
}
