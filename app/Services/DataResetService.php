<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * 업무 데이터 초기화 — 사용자(로그인 계정)와 그 소속 조직만 남기고 전부 삭제한다.
 * 쿼리빌더로 삭제하여 Observer(감사로그) 를 발생시키지 않는다. 자식→부모 순서로 지운다.
 */
class DataResetService
{
    /**
     * @return array<string, int> 테이블별 삭제 건수
     */
    public function reset(): array
    {
        return DB::transaction(function () {
            // 삭제 순서 = FK 자식 → 부모. organizations/users 는 별도 처리.
            $tables = [
                'settlement_items', 'settlements',
                'usage_report_items', 'usage_reports',
                'outbound_items', 'outbounds',
                'inbound_items', 'inbounds',
                'stocktake_items', 'stocktakes',
                'stock_transactions', 'stock_balances',
                'safety_stocks',
                'notifications', 'audit_logs', 'monthly_closings',
                'invitations',
                'product_lots', 'products',
                'document_sequences',
            ];

            $deleted = [];
            foreach ($tables as $t) {
                $deleted[$t] = DB::table($t)->delete();
            }

            // 조직은 "현재 사용자가 소속된 조직"만 남긴다(사용자 FK 보존).
            $keepOrgIds = DB::table('users')->whereNotNull('org_id')->distinct()->pluck('org_id')->all();
            $deleted['organizations'] = DB::table('organizations')
                ->when($keepOrgIds !== [], fn ($q) => $q->whereNotIn('id', $keepOrgIds))
                ->delete();

            return $deleted;
        });
    }
}
