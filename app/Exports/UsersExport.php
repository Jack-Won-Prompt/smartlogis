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
        return ['이메일', '이름', '역할', '소속', '상태', '계정ID'];
    }

    /**
     * @param  User  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->email,
            $row->name,
            $row->role->label(),
            $row->organization->name,
            $row->status->label(),
            $row->login_id,
        ];
    }
}
