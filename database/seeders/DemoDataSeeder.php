<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\InboundDirection;
use App\Enums\InboundStatus;
use App\Enums\NotiType;
use App\Enums\OrgType;
use App\Enums\OutboundSourceType;
use App\Enums\OutboundStatus;
use App\Enums\ReturnStatus;
use App\Enums\SettlementStatus;
use App\Enums\SettleType;
use App\Enums\Severity;
use App\Enums\StocktakeStatus;
use App\Enums\UsageStatus;
use App\Enums\UserStatus;
use App\Models\AccessLog;
use App\Models\AuditLog;
use App\Models\Inbound;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Outbound;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\SafetyStock;
use App\Models\Settlement;
use App\Models\StockReturn;
use App\Models\Stocktake;
use App\Models\UsageReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 데모(가상) 데이터 시더 — 각 화면 리스트를 50건 이상으로 채운다.
 * 모든 외래키는 실재하는 부모(조직/제품/Lot/사용자)만 참조하도록 강제해 정합성을 확보한다.
 * 문서번호는 'D' 마커를 붙여 실데이터와 구분/정리 가능하게 한다. 재고 원장은 건드리지 않는다.
 */
class DemoDataSeeder extends Seeder
{
    private const TARGET = 50;

    public function run(): void
    {
        // 재실행 안전 가드 — 데모 문서(IB-D 마커)가 이미 있으면 중복 생성하지 않는다(운영 보호).
        if (Inbound::where('inbound_no', 'like', 'IB-D%')->exists()) {
            $this->command->warn('DemoDataSeeder: 이미 데모 데이터가 있어 건너뜁니다. (정리하려면 IB-D/OB-D/UR-D/ST-D/RT-D 마커 문서를 삭제)');

            return;
        }

        DB::transaction(function () {
            $this->topUpOrganizations();
            $this->topUpProducts();
            $this->ensureLots();
            $this->topUpUsers();

            // 상태 스냅샷(자식 문서가 참조할 부모 풀) — 전부 정수 id 배열
            $suppliers = $this->ids(Organization::where('org_type', OrgType::SUPPLIER)->pluck('id')->all());
            $warehouses = $this->ids(Organization::where('org_type', OrgType::WAREHOUSE)->pluck('id')->all());
            $hospitals = $this->ids(Organization::where('org_type', OrgType::HOSPITAL)->pluck('id')->all());
            $userIds = $this->ids(User::pluck('id')->all());

            // product_id => [ProductLot ...] (lot 은 반드시 같은 product 의 것만 배정 → 정합성)
            /** @var array<int, array<int, ProductLot>> $lotsByProduct */
            $lotsByProduct = ProductLot::all()->groupBy('product_id')->map(fn ($g) => $g->values()->all())->all();
            $productIds = array_map('intval', array_keys($lotsByProduct));
            /** @var array<int, float> $priceOf */
            $priceOf = Product::pluck('sales_price', 'id')->map(fn ($v) => (float) $v)->all();
            /** @var array<int, int> $supplierOfProduct */
            $supplierOfProduct = Product::pluck('supplier_id', 'id')->map(fn ($v) => (int) $v)->all();

            $this->seedInbounds($suppliers, $warehouses, $hospitals, $userIds, $lotsByProduct, $productIds);
            $usageItems = $this->seedUsageReports($hospitals, $userIds, $lotsByProduct, $productIds, $priceOf);
            $this->seedOutbounds($warehouses, $hospitals, $lotsByProduct, $productIds, $priceOf);
            $this->seedReturns($hospitals, $warehouses, $userIds, $lotsByProduct, $productIds);
            $this->seedStocktakes(array_merge($warehouses, $hospitals), $userIds, $lotsByProduct, $productIds);
            $this->seedSettlements($hospitals, $suppliers, $usageItems, $supplierOfProduct);
            $this->topUpSafetyStocks($hospitals, $productIds);
            $this->topUpNotifications();
            $this->topUpAccessLogs($userIds);
            $this->topUpAuditLogs($userIds);
        });
    }

    private function topUpOrganizations(): void
    {
        $need = self::TARGET - Organization::count();
        for ($i = 1; $i <= max(0, $need); $i++) {
            $type = [OrgType::HOSPITAL, OrgType::HOSPITAL, OrgType::SUPPLIER, OrgType::WAREHOUSE][$i % 4];
            $prefix = ['HOSPITAL' => 'HOSP', 'SUPPLIER' => 'SUP', 'WAREHOUSE' => 'WH', 'HQ' => 'HQ'][$type->value];
            $labels = ['HOSPITAL' => '의료원', 'SUPPLIER' => '메디칼', 'WAREHOUSE' => '물류센터', 'HQ' => '본사'];
            Organization::create([
                'org_type' => $type,
                'code' => sprintf('%s-D%03d', $prefix, $i),
                'name' => sprintf('데모%s %02d', $labels[$type->value], $i),
                'biz_reg_no' => sprintf('%03d-%02d-%05d', random_int(100, 999), random_int(10, 99), random_int(10000, 99999)),
                'hpid_no' => $type === OrgType::HOSPITAL ? (string) random_int(30000000, 39999999) : null,
                'address' => '서울시 데모구 가상로 '.random_int(1, 300),
                'tel' => sprintf('02-%04d-%04d', random_int(100, 9999), random_int(1000, 9999)),
                'is_active' => true,
            ]);
        }
    }

    private function topUpProducts(): void
    {
        $suppliers = $this->ids(Organization::where('org_type', OrgType::SUPPLIER)->pluck('id')->all());
        $need = self::TARGET - Product::count();
        $start = Product::count() + 1;
        for ($i = 0; $i < max(0, $need); $i++) {
            $n = $start + $i;
            Product::factory()->create([
                'product_code' => sprintf('P-D%04d', $n),
                'gtin' => sprintf('880%011d', random_int(10000000000, 99999999999)),
                'supplier_id' => $suppliers[array_rand($suppliers)],
                'is_active' => true,
            ]);
        }
    }

    /** 모든 제품이 최소 1개 Lot 을 갖도록 보장(사용분/실사 아이템의 lot_id 정합성). */
    private function ensureLots(): void
    {
        foreach (Product::whereDoesntHave('lots')->pluck('id') as $pid) {
            ProductLot::factory()->count(random_int(1, 2))->create(['product_id' => $pid]);
        }
    }

    private function topUpUsers(): void
    {
        $orgs = Organization::all(['id', 'org_type']);
        $need = self::TARGET - User::count();
        $start = User::count() + 1;
        for ($i = 0; $i < max(0, $need); $i++) {
            $org = $orgs->random();
            User::create([
                'login_id' => 'demo_user'.($start + $i),
                'password' => Hash::make('password'),
                'name' => '데모사용자'.($start + $i),
                'email' => 'demo'.($start + $i).'@smartlogis.test',
                'role' => $org->org_type,              // 역할 = 소속 조직 유형 (정합성)
                'org_id' => $org->id,
                'status' => UserStatus::ACTIVE,
                'is_active' => true,
                'approved_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $suppliers
     * @param  array<int, int>  $warehouses
     * @param  array<int, int>  $hospitals
     * @param  array<int, int>  $userIds
     * @param  array<int, array<int, ProductLot>>  $lotsByProduct
     * @param  array<int, int>  $productIds
     */
    private function seedInbounds(array $suppliers, array $warehouses, array $hospitals, array $userIds, array $lotsByProduct, array $productIds): void
    {
        $statuses = [InboundStatus::PLANNED, InboundStatus::RECEIVING, InboundStatus::CONFIRMED, InboundStatus::PLANNED, InboundStatus::CANCELED];
        for ($i = 1; $i <= self::TARGET; $i++) {
            $toWh = $i % 2 === 0;
            $from = $toWh ? $suppliers[array_rand($suppliers)] : $warehouses[array_rand($warehouses)];
            $to = $toWh ? $warehouses[array_rand($warehouses)] : $hospitals[array_rand($hospitals)];
            $status = $statuses[$i % count($statuses)];

            $ib = Inbound::create([
                'inbound_no' => sprintf('IB-D%08d', $i),
                'direction' => $toWh ? InboundDirection::SUPPLIER_TO_WH : InboundDirection::WH_TO_HOSPITAL,
                'from_org_id' => $from,
                'to_org_id' => $to,
                'status' => $status,
                'planned_date' => now()->subDays(random_int(0, 60))->toDateString(),
                'confirmed_at' => $status === InboundStatus::CONFIRMED ? now()->subDays(random_int(0, 30)) : null,
                'created_by' => $userIds[array_rand($userIds)],
            ]);
            foreach ($this->pickProducts($productIds, random_int(1, 3)) as $pid) {
                $lot = $this->randomLot($lotsByProduct, $pid);
                $ib->items()->create([
                    'product_id' => $pid,
                    'lot_no' => $lot->lot_no,
                    'expiry_date' => $lot->expiry_date,
                    'qty' => random_int(5, 200),
                    'unit_price' => random_int(1000, 500000),
                ]);
            }
        }
    }

    /**
     * @param  array<int, int>  $hospitals
     * @param  array<int, int>  $userIds
     * @param  array<int, array<int, ProductLot>>  $lotsByProduct
     * @param  array<int, int>  $productIds
     * @param  array<int, float>  $priceOf
     * @return array<int, array{id: int, hospital_id: int, product_id: int, qty: int, unit_price: float, amount: float}>
     */
    private function seedUsageReports(array $hospitals, array $userIds, array $lotsByProduct, array $productIds, array $priceOf): array
    {
        $statuses = [UsageStatus::DRAFT, UsageStatus::SUBMITTED, UsageStatus::SUBMITTED, UsageStatus::REJECTED, UsageStatus::APPROVED];
        $depts = ['정형외과', '신경외과', '순환기내과', '외과', '마취통증의학과'];
        $items = [];
        for ($i = 1; $i <= self::TARGET; $i++) {
            $hospital = $hospitals[array_rand($hospitals)];
            $status = $statuses[$i % count($statuses)];
            $report = UsageReport::create([
                'report_no' => sprintf('UR-D%08d', $i),
                'hospital_id' => $hospital,
                'status' => $status,
                'usage_date' => now()->subDays(random_int(0, 90))->toDateString(),
                'submitted_at' => $status === UsageStatus::DRAFT ? null : now()->subDays(random_int(0, 30)),
                'approved_at' => $status === UsageStatus::APPROVED ? now()->subDays(random_int(0, 20)) : null,
                'approved_by' => $status === UsageStatus::APPROVED ? $userIds[array_rand($userIds)] : null,
                'reject_reason' => $status === UsageStatus::REJECTED ? '수량 상이로 반려(데모)' : null,
                'total_amount' => 0,
                'created_by' => $userIds[array_rand($userIds)],
            ]);
            $total = 0.0;
            foreach ($this->pickProducts($productIds, random_int(1, 3)) as $pid) {
                $lot = $this->randomLot($lotsByProduct, $pid);
                $qty = random_int(1, 20);
                $unit = $priceOf[$pid] ?? 10000.0;
                $amount = $unit * $qty;
                $total += $amount;
                $item = $report->items()->create([
                    'product_id' => $pid,
                    'lot_id' => $lot->id,     // 같은 product 의 lot → 정합성
                    'qty' => $qty,
                    'unit_price' => $unit,
                    'amount' => $amount,
                    'dept' => $depts[array_rand($depts)],
                ]);
                $items[] = ['id' => (int) $item->id, 'hospital_id' => $hospital, 'product_id' => $pid, 'qty' => $qty, 'unit_price' => $unit, 'amount' => $amount];
            }
            $report->update(['total_amount' => $total]);
        }

        return $items;
    }

    /**
     * 출고 지시 — 창고→병원. 상태별로 shipped/delivered 시점을 채운다. Lot 은 같은 product 것만.
     *
     * @param  array<int, int>  $warehouses
     * @param  array<int, int>  $hospitals
     * @param  array<int, array<int, ProductLot>>  $lotsByProduct
     * @param  array<int, int>  $productIds
     * @param  array<int, float>  $priceOf
     */
    private function seedOutbounds(array $warehouses, array $hospitals, array $lotsByProduct, array $productIds, array $priceOf): void
    {
        $statuses = [OutboundStatus::DRAFT, OutboundStatus::APPROVED, OutboundStatus::PICKING, OutboundStatus::SHIPPED, OutboundStatus::DELIVERED, OutboundStatus::CANCELED];
        for ($i = 1; $i <= self::TARGET; $i++) {
            $status = $statuses[$i % count($statuses)];
            $shipped = in_array($status, [OutboundStatus::SHIPPED, OutboundStatus::DELIVERED], true);
            $delivered = $status === OutboundStatus::DELIVERED;
            $ob = Outbound::create([
                'outbound_no' => sprintf('OB-D%08d', $i),
                'warehouse_id' => $warehouses[array_rand($warehouses)],
                'hospital_id' => $hospitals[array_rand($hospitals)],
                'status' => $status,
                'source_type' => $i % 3 === 0 ? OutboundSourceType::AUTO_REPLENISH : OutboundSourceType::MANUAL,
                'planned_date' => now()->subDays(random_int(0, 40))->toDateString(),
                'shipped_at' => $shipped ? now()->subDays(random_int(0, 20)) : null,
                'delivered_at' => $delivered ? now()->subDays(random_int(0, 10)) : null,
            ]);
            foreach ($this->pickProducts($productIds, random_int(1, 3)) as $pid) {
                $lot = $this->randomLot($lotsByProduct, $pid);
                $ob->items()->create([
                    'product_id' => $pid,
                    'lot_id' => $lot->id,
                    'qty' => random_int(1, 50),
                    'unit_price' => $priceOf[$pid] ?? 10000.0,
                ]);
            }
        }
    }

    /**
     * 반납 — 병원→창고(라이프/병원 등록). 상태별 shipped/received 시점 채움.
     *
     * @param  array<int, int>  $hospitals
     * @param  array<int, int>  $warehouses
     * @param  array<int, int>  $userIds
     * @param  array<int, array<int, ProductLot>>  $lotsByProduct
     * @param  array<int, int>  $productIds
     */
    private function seedReturns(array $hospitals, array $warehouses, array $userIds, array $lotsByProduct, array $productIds): void
    {
        $statuses = [ReturnStatus::REQUESTED, ReturnStatus::SHIPPING, ReturnStatus::RECEIVED, ReturnStatus::CANCELED];
        for ($i = 1; $i <= self::TARGET; $i++) {
            $status = $statuses[$i % count($statuses)];
            $rt = StockReturn::create([
                'return_no' => sprintf('RT-D%08d', $i),
                'hospital_id' => $hospitals[array_rand($hospitals)],
                'warehouse_id' => $warehouses[array_rand($warehouses)],
                'status' => $status,
                'reason' => '유통기한 임박 반납(데모)',
                'shipped_at' => in_array($status, [ReturnStatus::SHIPPING, ReturnStatus::RECEIVED], true) ? now()->subDays(random_int(0, 15)) : null,
                'received_at' => $status === ReturnStatus::RECEIVED ? now()->subDays(random_int(0, 7)) : null,
                'created_by' => $userIds[array_rand($userIds)],
            ]);
            foreach ($this->pickProducts($productIds, random_int(1, 3)) as $pid) {
                $lot = $this->randomLot($lotsByProduct, $pid);
                $rt->items()->create([
                    'product_id' => $pid,
                    'lot_id' => $lot->id,
                    'qty' => random_int(1, 30),
                ]);
            }
        }
    }

    /**
     * @param  array<int, int>  $orgIds
     * @param  array<int, int>  $userIds
     * @param  array<int, array<int, ProductLot>>  $lotsByProduct
     * @param  array<int, int>  $productIds
     */
    private function seedStocktakes(array $orgIds, array $userIds, array $lotsByProduct, array $productIds): void
    {
        $statuses = [StocktakeStatus::DRAFT, StocktakeStatus::COUNTING, StocktakeStatus::CONFIRMED];
        for ($i = 1; $i <= self::TARGET; $i++) {
            $status = $statuses[$i % count($statuses)];
            $st = Stocktake::create([
                'stocktake_no' => sprintf('ST-D%08d', $i),
                'org_id' => $orgIds[array_rand($orgIds)],
                'status' => $status,
                'count_date' => now()->subDays(random_int(0, 45))->toDateString(),
                'confirmed_at' => $status === StocktakeStatus::CONFIRMED ? now()->subDays(random_int(0, 20)) : null,
                'created_by' => $userIds[array_rand($userIds)],
            ]);
            foreach ($this->pickProducts($productIds, random_int(1, 4)) as $pid) {
                $lot = $this->randomLot($lotsByProduct, $pid);
                $sys = random_int(0, 300);
                $counted = max(0, $sys + random_int(-5, 5));
                $st->items()->create([
                    'product_id' => $pid,
                    'lot_id' => $lot->id,
                    'system_qty' => $sys,
                    'counted_qty' => $counted,
                    'diff_qty' => $counted - $sys,
                    'reason' => $counted !== $sys ? '실사 차이(데모)' : null,
                ]);
            }
        }
    }

    /**
     * @param  array<int, int>  $hospitals
     * @param  array<int, int>  $suppliers
     * @param  array<int, array{id: int, hospital_id: int, product_id: int, qty: int, unit_price: float, amount: float}>  $usageItems
     * @param  array<int, int>  $supplierOfProduct
     */
    private function seedSettlements(array $hospitals, array $suppliers, array $usageItems, array $supplierOfProduct): void
    {
        // 정산 아이템 후보를 조직별로 분류(정합성: SALES=병원 사용분, PURCHASE=자사 제품)
        /** @var array<int, array<int, array{id: int, hospital_id: int, product_id: int, qty: int, unit_price: float, amount: float}>> $byHospital */
        $byHospital = [];
        /** @var array<int, array<int, array{id: int, hospital_id: int, product_id: int, qty: int, unit_price: float, amount: float}>> $bySupplier */
        $bySupplier = [];
        foreach ($usageItems as $it) {
            $byHospital[$it['hospital_id']][] = $it;
            $sup = $supplierOfProduct[$it['product_id']] ?? null;
            if ($sup !== null) {
                $bySupplier[$sup][] = $it;
            }
        }

        $months = [];
        for ($y = 2025; $y <= 2026; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                $months[] = sprintf('%04d-%02d', $y, $m);
            }
        }
        $statuses = [SettlementStatus::OPEN, SettlementStatus::CONFIRMED, SettlementStatus::OPEN];

        $made = 0;
        // year_month × org × settle_type 고유 조합으로 50건 채운다.
        foreach ($months as $ym) {
            if ($made >= self::TARGET) {
                break;
            }
            /** @var array<string, array{0: array<int, int>, 1: array<int, array<int, array<string, mixed>>>, 2: SettleType}> $tracks */
            $tracks = [
                'SALES' => [$hospitals, $byHospital, SettleType::SALES],
                'PURCHASE' => [$suppliers, $bySupplier, SettleType::PURCHASE],
            ];
            foreach ($tracks as [$orgPool, $itemPool, $type]) {
                foreach ($orgPool as $orgId) {
                    if ($made >= self::TARGET) {
                        break 2;
                    }
                    if (Settlement::where('year_month', $ym)->where('org_id', $orgId)->where('settle_type', $type)->exists()) {
                        continue;
                    }
                    $pool = $itemPool[$orgId] ?? [];
                    if (count($pool) === 0) {
                        continue; // 연결할 사용분 아이템이 없으면 건너뜀(빈 정산 방지)
                    }
                    $settlement = Settlement::create([
                        'year_month' => $ym,
                        'org_id' => $orgId,
                        'settle_type' => $type,
                        'status' => $statuses[$made % 3],
                        'total_qty' => 0,
                        'total_amount' => 0,
                    ]);
                    $totalQty = 0;
                    $totalAmt = 0.0;
                    foreach (array_slice($pool, 0, random_int(1, min(3, count($pool)))) as $it) {
                        $settlement->items()->create([
                            'usage_report_item_id' => $it['id'],
                            'product_id' => $it['product_id'],
                            'qty' => $it['qty'],
                            'unit_price' => $it['unit_price'],
                            'amount' => $it['amount'],
                        ]);
                        $totalQty += (int) $it['qty'];
                        $totalAmt += (float) $it['amount'];
                    }
                    $settlement->update(['total_qty' => $totalQty, 'total_amount' => $totalAmt]);
                    $made++;
                }
            }
        }
    }

    /**
     * 안전재고 — 병원×제품 고유 조합으로 50건까지 채운다(PK 중복 회피).
     *
     * @param  array<int, int>  $hospitals
     * @param  array<int, int>  $productIds
     */
    private function topUpSafetyStocks(array $hospitals, array $productIds): void
    {
        $need = self::TARGET - SafetyStock::count();
        if ($need <= 0) {
            return;
        }
        $made = 0;
        foreach ($hospitals as $hid) {
            foreach ($productIds as $pid) {
                if ($made >= $need) {
                    return;
                }
                if (SafetyStock::where('hospital_id', $hid)->where('product_id', $pid)->exists()) {
                    continue;
                }
                $safety = random_int(5, 50);
                SafetyStock::create([
                    'hospital_id' => $hid,
                    'product_id' => $pid,
                    'safety_qty' => $safety,
                    'max_qty' => $safety * 3,
                    'reorder_qty' => $safety * 2,
                ]);
                $made++;
            }
        }
    }

    /** 알림 센터 — 유형/심각도/대상역할을 섞어 50건까지 채운다. */
    private function topUpNotifications(): void
    {
        $need = self::TARGET - Notification::count();
        if ($need <= 0) {
            return;
        }
        $types = NotiType::cases();
        $severities = [Severity::INFO, Severity::WARNING, Severity::CRITICAL];
        $roles = [OrgType::HQ, OrgType::WAREHOUSE, OrgType::HOSPITAL, OrgType::SUPPLIER];
        // 중요: 이벤트 억제 — 데모 알림이 실제 FCM 푸시/이메일/브로드캐스트로 발송되지 않게 한다(운영 보호).
        Notification::withoutEvents(function () use ($need, $types, $severities, $roles) {
            for ($i = 0; $i < $need; $i++) {
                $type = $types[array_rand($types)];
                Notification::create([
                    'noti_type' => $type,
                    'severity' => $severities[array_rand($severities)],
                    'target_role' => $roles[array_rand($roles)],
                    'target_org_id' => null,
                    'title' => $type->label().' 알림(데모)',
                    'message' => '데모 알림 메시지 '.($i + 1),
                    'link_url' => null,
                    'is_read' => (bool) random_int(0, 1),
                    'created_at' => now()->subDays(random_int(0, 30)),
                ]);
            }
        });
    }

    /**
     * 접속 로그 — 대표 라우트를 섞어 50건까지 채운다.
     *
     * @param  array<int, int>  $userIds
     */
    private function topUpAccessLogs(array $userIds): void
    {
        $need = self::TARGET - AccessLog::count();
        if ($need <= 0) {
            return;
        }
        $routes = [
            ['GET', '/dashboard', 'dashboard'],
            ['GET', '/inventory/status', 'inventory.status'],
            ['GET', '/usages', 'usages.index'],
            ['POST', '/usages', 'usages.store'],
            ['GET', '/settlements', 'settlements.index'],
            ['GET', '/outbounds', 'outbounds.index'],
            ['GET', '/notifications', 'notifications.index'],
        ];
        for ($i = 0; $i < $need; $i++) {
            $r = $routes[array_rand($routes)];
            AccessLog::create([
                'user_id' => $userIds[array_rand($userIds)],
                'method' => $r[0],
                'path' => $r[1],
                'route' => $r[2],
                'ip' => '127.0.0.1',
                'user_agent' => 'DemoSeeder/1.0',
                'created_at' => now()->subDays(random_int(0, 30)),
            ]);
        }
    }

    /** @param  array<int, int>  $userIds */
    private function topUpAuditLogs(array $userIds): void
    {
        $actions = AuditAction::cases();
        $entities = ['Product', 'Organization', 'User', 'UsageReport', 'Inbound', 'Outbound', 'Stocktake', 'SafetyStock'];
        $need = self::TARGET - AuditLog::count();
        for ($i = 0; $i < max(0, $need); $i++) {
            $action = $actions[array_rand($actions)];
            AuditLog::create([
                'user_id' => $userIds[array_rand($userIds)],
                'action' => $action,
                'entity' => $entities[array_rand($entities)],
                'entity_id' => random_int(1, 50),
                'before' => in_array($action, [AuditAction::UPDATE, AuditAction::DELETE], true) ? ['status' => 'OLD'] : null,
                'after' => in_array($action, [AuditAction::CREATE, AuditAction::UPDATE], true) ? ['status' => 'NEW'] : null,
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDays(random_int(0, 60)),
            ]);
        }
    }

    /**
     * 같은 product 에 속한 Lot 하나를 무작위 반환(lot-product 정합성 보장).
     *
     * @param  array<int, array<int, ProductLot>>  $lotsByProduct
     */
    private function randomLot(array $lotsByProduct, int $productId): ProductLot
    {
        $lots = $lotsByProduct[$productId];

        return $lots[array_rand($lots)];
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, int>
     */
    private function pickProducts(array $productIds, int $count): array
    {
        $keys = (array) array_rand($productIds, min($count, count($productIds)));

        return array_map(fn ($k) => $productIds[$k], $keys);
    }

    /**
     * mixed 배열을 정수 id 배열로 정규화.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, int>
     */
    private function ids(array $values): array
    {
        return array_values(array_map('intval', $values));
    }
}
