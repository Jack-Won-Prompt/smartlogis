<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrgType;
use App\Models\UsageReport;
use App\Models\User;

/**
 * 사용분 문서 권한. Global Scope 가 "볼 수 있는 범위"를 자르고,
 * 이 Policy 는 "상태를 바꿀 수 있는가"를 이중 검증한다(CLAUDE.md §2, §7.1).
 */
class UsageReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(OrgType::HQ, OrgType::HOSPITAL);
    }

    public function view(User $user, UsageReport $report): bool
    {
        return $user->isHq() || $this->ownsReport($user, $report);
    }

    /** 사용분 등록은 병원만. */
    public function create(User $user): bool
    {
        return $user->isHospital();
    }

    /** 임시저장/반려 상태를 소유 병원이 수정. */
    public function update(User $user, UsageReport $report): bool
    {
        return $this->ownsReport($user, $report) && $report->status->isEditable();
    }

    /** 전송(제출)도 소유 병원, 수정 가능 상태에서만. */
    public function submit(User $user, UsageReport $report): bool
    {
        return $this->ownsReport($user, $report) && $report->status->isEditable();
    }

    /** 승인/반려는 본사만, SUBMITTED 상태에서만. */
    public function approve(User $user, UsageReport $report): bool
    {
        return $user->isHq() && $report->status->isApprovable();
    }

    public function reject(User $user, UsageReport $report): bool
    {
        return $this->approve($user, $report);
    }

    private function ownsReport(User $user, UsageReport $report): bool
    {
        return $user->isHospital() && $user->org_id === $report->hospital_id;
    }
}
