<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * 재고 위치(org_id)를 가진 테이블(stock_balances, stock_transactions, stocktakes 등)에
 * 소속 조직 필터를 강제한다.
 *
 * HQ        : 전체
 * WAREHOUSE : 자기 창고 재고
 * HOSPITAL  : 자기 병원 재고
 * SUPPLIER  : 위치가 아니라 제품 기준이므로 SupplierProductScope 가 별도로 담당한다.
 */
class OrgLocationScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        if ($user->isHq() || $user->isSupplier()) {
            return;
        }

        /** @var string $column */
        $column = method_exists($model, 'orgScopeColumn')
            ? $model->orgScopeColumn()
            : 'org_id';

        $builder->where($model->qualifyColumn($column), $user->org_id);
    }
}
