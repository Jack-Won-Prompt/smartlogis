<?php

declare(strict_types=1);

use App\Enums\OrgType;

/*
 * 사이드바 메뉴 정의 — 앱 레이아웃(x-app-layout)과 MDI 워크스페이스 셸이 공유한다.
 * groups: [그룹라벨, 아이콘키, [ [라벨, 라우트명, 허용역할[]], ... ]]
 */

return [
    'groups' => [
        ['대시보드', 'grid', [
            ['대시보드', 'dashboard', []],
        ]],
        ['메세지', 'chat', [
            ['채팅', 'chat.index', []],
        ]],
        ['기준정보', 'layers', [
            ['제품 마스터', 'master.products', [OrgType::HQ]],
            ['거래처', 'master.organizations', [OrgType::HQ]],
            ['사용자', 'master.users', [OrgType::HQ]],
            ['안전재고', 'master.safety-stocks', [OrgType::HQ]],
        ]],
        ['재고', 'box', [
            ['재고 현황', 'inventory.status', [OrgType::HQ, OrgType::WAREHOUSE, OrgType::HOSPITAL, OrgType::LIFE]],
            ['유통기한 임박', 'inventory.expiry', [OrgType::HQ, OrgType::WAREHOUSE, OrgType::HOSPITAL, OrgType::LIFE]],
            ['재고 실사', 'stocktakes.index', [OrgType::HQ, OrgType::WAREHOUSE, OrgType::HOSPITAL]],
            ['Lot 추적', 'inventory.lot-trace', [OrgType::HQ, OrgType::WAREHOUSE, OrgType::HOSPITAL, OrgType::LIFE]],
        ]],
        ['입출고', 'truck', [
            ['입고 예정(ASN)', 'inbounds.asn', [OrgType::HQ, OrgType::WAREHOUSE, OrgType::SUPPLIER]],
            ['입고 검수', 'inbounds.receiving', [OrgType::HQ, OrgType::WAREHOUSE]],
            ['출고 지시', 'outbounds.index', [OrgType::HQ, OrgType::WAREHOUSE]],
            ['피킹/출고', 'outbounds.picking', [OrgType::HQ, OrgType::WAREHOUSE]],
            ['배송 현황', 'outbounds.delivery', [OrgType::HQ, OrgType::WAREHOUSE, OrgType::HOSPITAL]],
            ['반납 처리', 'returns.index', [OrgType::HQ, OrgType::WAREHOUSE, OrgType::HOSPITAL, OrgType::LIFE]],
        ]],
        ['사용분', 'clipboard', [
            ['사용분 등록', 'usages.create', [OrgType::HOSPITAL, OrgType::LIFE]],
            ['사용분 승인', 'usages.approval', [OrgType::HQ]],
            ['사용분 이력', 'usages.index', [OrgType::HQ, OrgType::HOSPITAL, OrgType::LIFE]],
        ]],
        ['정산', 'won', [
            ['월 정산', 'settlements.index', [OrgType::HQ, OrgType::HOSPITAL, OrgType::SUPPLIER]],
            ['월 마감', 'settlements.closing', [OrgType::HQ]],
        ]],
        ['리포트', 'won', [
            ['채널별 매출', 'reports.channel-sales', [OrgType::HQ]],
        ]],
        ['공급사', 'factory', [
            ['자사 재고', 'supplier.stocks', [OrgType::SUPPLIER, OrgType::HQ]],
            ['부족/납품', 'supplier.shortages', [OrgType::SUPPLIER, OrgType::HQ]],
        ]],
        ['관리', 'shield', [
            ['알림 센터', 'notifications.index', []],
            ['공지 발송', 'admin.announcements', [OrgType::HQ]],
            ['감사 로그', 'admin.audit-logs', [OrgType::HQ]],
            ['접속 로그', 'admin.access-logs', [OrgType::HQ]],
        ]],
    ],

    'icons' => [
        'grid' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
        'chat' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
        'layers' => 'm12 2 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 17l9 5 9-5',
        'box' => 'm12 3 8 4.5v9L12 21l-8-4.5v-9L12 3ZM12 12 4 7.5M12 12l8-4.5M12 12v9',
        'truck' => 'M1 3h15v13H1zM16 8h4l3 3v5h-7M5.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM18.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
        'clipboard' => 'M9 4h6v3H9zM7 4H5v17h14V4h-2M9 12h6M9 16h4',
        'won' => 'M4 6l3 12 3-9 2 6 2-6 3 9 3-12M3 10h18',
        'factory' => 'M3 21V9l5 3V9l5 3V9l5 3v9H3ZM7 21v-3M12 21v-3M17 21v-3',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3Z',
    ],
];
