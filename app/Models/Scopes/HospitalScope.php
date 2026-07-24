<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * 병원 소유 문서(사용분/안전재고/출고지시 등)를 HOSPITAL 계정에게 자기 병원 것만 노출한다.
 *
 * 대상 모델은 hospitalScopeColumn() 으로 병원 FK 컬럼명을 알려준다(기본 hospital_id).
 * 프론트 필터가 아니라 이 스코프가 유일한 차단 지점이다.
 */
class HospitalScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // 콘솔/시더/미인증 컨텍스트는 필터하지 않는다(배치가 전 병원을 처리해야 함).
        if (! $user instanceof User || ! $user->isHospital()) {
            return;
        }

        /** @var string $column */
        $column = method_exists($model, 'hospitalScopeColumn')
            ? $model->hospitalScopeColumn()
            : 'hospital_id';

        $builder->where($model->qualifyColumn($column), $user->org_id);
    }
}
