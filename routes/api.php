<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InboundController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OutboundController;
use App\Http\Controllers\Api\V1\ReturnController;
use App\Http\Controllers\Api\V1\ScanController;
use App\Http\Controllers\Api\V1\SettlementController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 모바일 앱(Flutter) API — prefix: /api/v1
|--------------------------------------------------------------------------
|
| 인증은 Bearer 토큰(api_tokens). 인증 미들웨어가 기본 가드에도 사용자를 주입하므로
| 웹과 동일한 Global Scope(HospitalScope / SupplierProductScope / OrgLocationScope)가
| 그대로 적용된다. 역할 게이트는 웹과 같은 `role:` 미들웨어를 재사용한다.
|
*/

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

/*
 * 앱 버전 확인 — 인증 없이 접근 가능해야 한다.
 * 강제 업데이트 대상 구버전은 로그인 자체가 실패할 수 있는데(인증 응답 스키마 변경 등)
 * 그 상태에서도 "업데이트하세요" 는 띄울 수 있어야 하기 때문이다.
 */
Route::get('/app/version', AppVersionController::class)->middleware('throttle:60,1');

Route::middleware('api.token')->group(function () {

    // ---------------------------------------------------------------- 공통
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/push-token', [AuthController::class, 'pushToken']);

    Route::get('/dashboard', DashboardController::class);

    Route::post('/scan', ScanController::class);

    Route::get('/catalog/products', [CatalogController::class, 'products']);
    Route::get('/catalog/organizations', [CatalogController::class, 'organizations']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->whereNumber('id');

    // ---------------------------------------------------------------- 재고
    Route::middleware('role:HQ,WAREHOUSE,HOSPITAL,SUPPLIER')->prefix('inventory')->group(function () {
        Route::get('/stocks', [InventoryController::class, 'stocks']);
        Route::get('/lots', [InventoryController::class, 'lots']);
        Route::get('/expiry', [InventoryController::class, 'expiry']);
        Route::get('/shortages', [InventoryController::class, 'shortages']);
        Route::get('/lots/{lotId}/trace', [InventoryController::class, 'trace'])->whereNumber('lotId');
    });

    // ---------------------------------------------------------------- 입고
    Route::prefix('inbounds')->group(function () {
        Route::get('/', [InboundController::class, 'index']);
        Route::get('/{id}', [InboundController::class, 'show'])->whereNumber('id');

        // ASN 등록 — 공급사(자기 조직 고정) 또는 본사
        Route::post('/', [InboundController::class, 'store'])->middleware('role:SUPPLIER,HQ');

        // 검수 — 받는 쪽(창고/병원) 또는 본사
        Route::middleware('role:WAREHOUSE,HOSPITAL,HQ')->group(function () {
            Route::post('/{id}/scan', [InboundController::class, 'scanItem'])->whereNumber('id');
            Route::patch('/{id}/items/{itemId}', [InboundController::class, 'updateItem'])->whereNumber(['id', 'itemId']);
            Route::delete('/{id}/items/{itemId}', [InboundController::class, 'destroyItem'])->whereNumber(['id', 'itemId']);
            Route::post('/{id}/confirm', [InboundController::class, 'confirm'])->whereNumber('id');
        });
    });

    // ---------------------------------------------------------------- 출고
    Route::middleware('role:HQ,WAREHOUSE,HOSPITAL')->prefix('outbounds')->group(function () {
        Route::get('/', [OutboundController::class, 'index']);
        Route::get('/{id}', [OutboundController::class, 'show'])->whereNumber('id');

        Route::middleware('role:HQ,WAREHOUSE')->group(function () {
            Route::post('/', [OutboundController::class, 'store']);
            Route::post('/{id}/pick', [OutboundController::class, 'pick'])->whereNumber('id');
            Route::post('/{id}/ship', [OutboundController::class, 'ship'])->whereNumber('id');
        });

        Route::post('/{id}/deliver', [OutboundController::class, 'deliver'])->whereNumber('id');
    });

    // ---------------------------------------------------------------- 반납
    // 병원/라이프가 등록 → 배송 → 창고/본사가 수령확인(이때 재고가 이동한다).
    Route::middleware('role:HQ,WAREHOUSE,HOSPITAL,LIFE')->prefix('returns')->group(function () {
        Route::get('/', [ReturnController::class, 'index']);
        Route::get('/{id}', [ReturnController::class, 'show'])->whereNumber('id');

        // 등록은 물품을 보유한 쪽만 — 창고가 자기 앞으로 반납을 만들 수 없다.
        Route::post('/', [ReturnController::class, 'store'])->middleware('role:HOSPITAL,LIFE');

        Route::post('/{id}/ship', [ReturnController::class, 'ship'])->whereNumber('id');

        // 재고를 실제로 옮기는 단계라 받는 쪽으로 제한한다.
        Route::post('/{id}/receive', [ReturnController::class, 'receive'])
            ->whereNumber('id')->middleware('role:HQ,WAREHOUSE');

        Route::post('/{id}/cancel', [ReturnController::class, 'cancel'])
            ->whereNumber('id')->middleware('role:HQ,HOSPITAL,LIFE');
    });

    // ---------------------------------------------------------------- 사용분
    Route::middleware('role:HQ,HOSPITAL')->prefix('usages')->group(function () {
        Route::get('/', [UsageController::class, 'index']);
        Route::get('/{id}', [UsageController::class, 'show'])->whereNumber('id');

        Route::middleware('role:HOSPITAL')->group(function () {
            Route::post('/', [UsageController::class, 'store']);
            Route::post('/{id}/submit', [UsageController::class, 'submit'])->whereNumber('id');
            Route::delete('/{id}', [UsageController::class, 'destroy'])->whereNumber('id');
        });

        Route::middleware('role:HQ')->group(function () {
            Route::post('/approve-bulk', [UsageController::class, 'approveBulk']);
            Route::post('/{id}/approve', [UsageController::class, 'approve'])->whereNumber('id');
            Route::post('/{id}/reject', [UsageController::class, 'reject'])->whereNumber('id');
        });
    });

    // ---------------------------------------------------------------- 정산
    Route::middleware('role:HQ,HOSPITAL,SUPPLIER')->prefix('settlements')->group(function () {
        Route::get('/', [SettlementController::class, 'index']);
        Route::get('/{id}', [SettlementController::class, 'show'])->whereNumber('id');
    });

    // ---------------------------------------------------------------- 공급사
    Route::middleware('role:SUPPLIER,HQ')->prefix('supplier')->group(function () {
        Route::get('/stocks', [SupplierController::class, 'stocks']);
        Route::get('/stocks/{productId}/hospitals', [SupplierController::class, 'stockByHospital'])->whereNumber('productId');
        Route::get('/shortages', [SupplierController::class, 'shortages']);
    });
});
