<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotiType;
use App\Enums\OrgType;
use App\Enums\Severity;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\ProductLot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 현재 재고/유통기한 상황을 바탕으로 대표 알림을 생성한다.
 * (실서비스에서는 Phase 6 배치가 생성 — 여기서는 알림 센터 UI 확인용 시드)
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $hq = Organization::where('org_type', OrgType::HQ)->first();

        // 1) 유통기한 임박(D-30) Lot → CRITICAL
        $soon = ProductLot::query()->with('product')->expiringWithin(30)->limit(6)->get();
        foreach ($soon as $lot) {
            $days = Carbon::today()->diffInDays($lot->expiry_date, false);
            Notification::create([
                'noti_type' => NotiType::EXPIRY,
                'severity' => $days < 0 ? Severity::CRITICAL : Severity::WARNING,
                'target_role' => OrgType::HQ->value,
                'target_org_id' => $hq?->id,
                'title' => '유통기한 임박',
                'message' => "{$lot->product->product_name} · Lot {$lot->lot_no} 유통기한 D".($days < 0 ? '+'.abs((int) $days).' (경과)' : '-'.(int) $days),
                'link_url' => '/inventory/expiry',
                'is_read' => false,
            ]);
        }

        // 2) 안전재고 미달 → WARNING (병원 대상)
        $below = DB::table('safety_stocks as s')
            ->leftJoin('stock_balances as b', function ($j) {
                $j->on('b.org_id', '=', 's.hospital_id')->on('b.product_id', '=', 's.product_id');
            })
            ->join('organizations as h', 'h.id', '=', 's.hospital_id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->select('s.hospital_id', 'h.name as hospital', 'p.product_name', 's.safety_qty', DB::raw('COALESCE(SUM(b.qty),0) as onhand'))
            ->groupBy('s.hospital_id', 'h.name', 'p.product_name', 's.safety_qty')
            ->havingRaw('COALESCE(SUM(b.qty),0) < s.safety_qty')
            ->limit(8)->get();

        foreach ($below as $row) {
            Notification::create([
                'noti_type' => NotiType::SAFETY_STOCK,
                'severity' => Severity::WARNING,
                'target_role' => null,
                'target_org_id' => $row->hospital_id,
                'title' => '안전재고 미달',
                'message' => "{$row->product_name} · 현재고 {$row->onhand} / 안전 {$row->safety_qty}",
                'link_url' => '/inventory/status',
                'is_read' => false,
            ]);
            // 본사에도 동일 알림
            Notification::create([
                'noti_type' => NotiType::SAFETY_STOCK,
                'severity' => Severity::WARNING,
                'target_role' => OrgType::HQ->value,
                'target_org_id' => $hq?->id,
                'title' => '안전재고 미달',
                'message' => "{$row->hospital} · {$row->product_name} 보충 필요",
                'link_url' => '/inventory/status',
                'is_read' => false,
            ]);
        }
    }
}
