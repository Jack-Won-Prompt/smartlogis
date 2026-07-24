<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SUPPLIER 계정은 자사 제품과 관련된 행만 볼 수 있다.
 *
 * - products 테이블 자체:            supplier_id = 내 조직
 * - product_id 를 가진 종속 테이블:  product_id IN (내 제품)
 *   (대상 모델이 supplierScopeColumn() 으로 컬럼명을 지정, 기본 product_id)
 */
class SupplierProductScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isSupplier()) {
            return;
        }

        if ($model->getTable() === 'products') {
            $builder->where($model->qualifyColumn('supplier_id'), $user->org_id);

            return;
        }

        /** @var string $column */
        $column = method_exists($model, 'supplierScopeColumn')
            ? $model->supplierScopeColumn()
            : 'product_id';

        $builder->whereIn(
            $model->qualifyColumn($column),
            DB::table('products')->select('id')->where('supplier_id', $user->org_id)
        );
    }
}
