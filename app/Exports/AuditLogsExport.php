<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;

/** @extends BaseQueryExport<AuditLog> */
class AuditLogsExport extends BaseQueryExport
{
    /** @return array<int, string> */
    public function headings(): array
    {
        return ['일시', '사용자', '동작', '대상', '대상ID', '변경전', '변경후'];
    }

    /**
     * @param  AuditLog  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->created_at ? Carbon::parse($row->created_at)->timezone('Asia/Seoul')->format('Y-m-d H:i:s') : null,
            $row->user_id === null ? '시스템' : $row->user->name,
            $row->action->label(),
            $row->entity,
            $row->entity_id,
            $row->before ? json_encode($row->before, JSON_UNESCAPED_UNICODE) : null,
            $row->after ? json_encode($row->after, JSON_UNESCAPED_UNICODE) : null,
        ];
    }
}
