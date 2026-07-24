<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\User;

/** @extends BaseQueryExport<User> */
class UsersExport extends BaseQueryExport
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['아이디', '이름', '역할', '소속', '이메일', '상태'];
    }

    /**
     * @param  User  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->login_id,
            $row->name,
            $row->role->label(),
            $row->organization->name,
            $row->email,
            $row->status->label(),
        ];
    }
}
