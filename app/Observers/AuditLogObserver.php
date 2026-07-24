<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * 도메인 모델의 생성/수정/삭제를 audit_logs 에 자동 기록한다.
 * 상태 전이(승인/반려/마감 등)는 서비스에서 AuditLog::record() 로 별도 기록하므로
 * 여기서는 순수 CRUD(CREATE/UPDATE/DELETE)만 담당한다.
 */
class AuditLogObserver
{
    public function created(Model $model): void
    {
        AuditLog::record(
            AuditAction::CREATE,
            $this->entity($model),
            $this->entityId($model),
            null,
            $model->getAttributes(),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        // updated_at 만 바뀐 no-op 저장은 기록하지 않는다.
        unset($changes['updated_at']);
        if ($changes === []) {
            return;
        }

        $before = [];
        foreach (array_keys($changes) as $key) {
            $before[$key] = $model->getOriginal($key);
        }

        AuditLog::record(
            AuditAction::UPDATE,
            $this->entity($model),
            $this->entityId($model),
            $before,
            $changes,
        );
    }

    public function deleted(Model $model): void
    {
        AuditLog::record(
            AuditAction::DELETE,
            $this->entity($model),
            $this->entityId($model),
            $model->getOriginal(),
            null,
        );
    }

    private function entity(Model $model): string
    {
        return class_basename($model);
    }

    private function entityId(Model $model): ?int
    {
        $key = $model->getKey();

        return is_numeric($key) ? (int) $key : null;
    }
}
