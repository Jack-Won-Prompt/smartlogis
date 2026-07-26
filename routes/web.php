<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inbound\InboundController;
use App\Http\Controllers\Inventory\ExpiryController;
use App\Http\Controllers\Inventory\LotTraceController;
use App\Http\Controllers\Inventory\StockStatusController;
use App\Http\Controllers\Inventory\StocktakeController;
use App\Http\Controllers\Master\OrganizationMasterController;
use App\Http\Controllers\Master\ProductMasterController;
use App\Http\Controllers\Master\SafetyStockMasterController;
use App\Http\Controllers\Master\UserMasterController;
use App\Http\Controllers\Outbound\OutboundController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settlement\ClosingController;
use App\Http\Controllers\Settlement\SettlementController;
use App\Http\Controllers\Supplier\SupplierController;
use App\Http\Controllers\Usage\UsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SmartLogis 라우트
|--------------------------------------------------------------------------
| 화면(메뉴)별 라우트는 Phase 3 이후 역할 미들웨어 그룹으로 추가된다.
| 예) Route::middleware(['auth','role:HQ,WAREHOUSE'])->group(...)
| Phase 1 은 인증 + 대시보드 뼈대까지만 연결한다.
*/

Route::get('/', function () {
    // 로그인 사용자는 MDI 워크스페이스로, 방문자는 랜딩 페이지로.
    return auth()->check()
        ? redirect()->route('workspace')
        : view('welcome');
})->name('welcome');

Route::middleware('auth')->group(function () {
    // MDI 탭 워크스페이스(셸) — 메뉴 클릭 시 화면이 탭(iframe)으로 열린다.
    Route::view('/workspace', 'workspace')->name('workspace');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // 바코드 스캔 파싱 (GS1) — 세션 인증 하에 제품 조회
    Route::post('/api/barcode/parse', [BarcodeController::class, 'parse'])->name('barcode.parse');

    // 알림 센터
    Route::view('/notifications', 'notifications.index')->name('notifications.index');

    // 채팅(전 사용자) — 어떤 사용자든 검색해 대화 시작. 실시간은 Pusher 브로드캐스트.
    Route::controller(ChatController::class)->group(function () {
        Route::get('/chat', 'index')->name('chat.index');
        Route::get('/chat/users', 'userSearch')->name('chat.users');   // {conversation} 보다 먼저
        Route::post('/chat', 'store')->name('chat.store');
        Route::post('/chat/group', 'storeGroup')->name('chat.group');
        Route::get('/chat/{conversation}', 'show')->name('chat.show');
        Route::post('/chat/{conversation}/reply', 'reply')->name('chat.reply');
        Route::post('/chat/{conversation}/invite', 'invite')->name('chat.invite');
        Route::delete('/chat/{conversation}/leave', 'leave')->name('chat.leave');
        Route::patch('/chat/messages/{message}', 'update')->name('chat.messages.update');
        Route::delete('/chat/messages/{message}', 'destroy')->name('chat.messages.destroy');
    });

    // 재고 — 본사/창고/병원
    Route::middleware('role:HQ,WAREHOUSE,HOSPITAL')->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/status', [StockStatusController::class, 'index'])->name('status');
        Route::get('/status/data', [StockStatusController::class, 'data'])->name('status.data');

        Route::get('/expiry', [ExpiryController::class, 'index'])->name('expiry');
        Route::get('/expiry/data', [ExpiryController::class, 'data'])->name('expiry.data');

        Route::get('/lot-trace', [LotTraceController::class, 'index'])->name('lot-trace');
        Route::get('/lot-trace/lots', [LotTraceController::class, 'lots'])->name('lot-trace.lots');
        Route::get('/lot-trace/{lot}/trace', [LotTraceController::class, 'trace'])->name('lot-trace.trace');
    });

    // 입고 — 공급사/본사/창고
    Route::middleware('role:HQ,WAREHOUSE,SUPPLIER')->prefix('inbounds')->name('inbounds.')->group(function () {
        Route::controller(InboundController::class)->group(function () {
            Route::get('/asn', 'asn')->name('asn');
            Route::get('/receiving', 'receiving')->name('receiving');
            Route::get('/data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::get('/{inbound}', 'show')->name('show');
            Route::post('/{inbound}/confirm', 'confirm')->name('confirm');
        });
    });

    // 출고 — 본사/창고
    Route::middleware('role:HQ,WAREHOUSE')->prefix('outbounds')->name('outbounds.')->group(function () {
        Route::controller(OutboundController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/picking', 'index')->name('picking');
            Route::get('/delivery', 'index')->name('delivery');
            Route::get('/data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::get('/{outbound}', 'show')->name('show');
            Route::post('/{outbound}/pick', 'pick')->name('pick');
            Route::post('/{outbound}/ship', 'ship')->name('ship');
            Route::post('/{outbound}/deliver', 'deliver')->name('deliver');
        });
    });

    // 재고 실사 — 본사/창고/병원
    Route::middleware('role:HQ,WAREHOUSE,HOSPITAL')->prefix('stocktakes')->name('stocktakes.')->group(function () {
        Route::controller(StocktakeController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::get('/{stocktake}', 'show')->name('show');
            Route::patch('/{stocktake}/items/{item}', 'updateItem')->name('updateItem');
            Route::post('/{stocktake}/confirm', 'confirm')->name('confirm');
        });
    });

    // 사용분 — 등록(병원)/승인(본사)/이력
    Route::prefix('usages')->name('usages.')->group(function () {
        Route::controller(UsageController::class)->group(function () {
            Route::get('/create', 'create')->middleware('role:HOSPITAL')->name('create');
            Route::get('/approval', 'approval')->middleware('role:HQ')->name('approval');
            Route::get('/', 'index')->middleware('role:HQ,HOSPITAL')->name('index');
            Route::get('/data', 'data')->middleware('role:HQ,HOSPITAL')->name('data');
            Route::get('/{usage}', 'show')->middleware('role:HQ,HOSPITAL')->name('show');
            Route::post('/', 'store')->middleware('role:HOSPITAL')->name('store');
            Route::post('/{usage}/submit', 'submit')->middleware('role:HOSPITAL')->name('submit');
            Route::post('/{usage}/approve', 'approve')->middleware('role:HQ')->name('approve');
            Route::post('/{usage}/reject', 'reject')->middleware('role:HQ')->name('reject');
        });
    });

    // 정산 — 조회(HQ/병원/공급사) / 마감(HQ)
    Route::prefix('settlements')->name('settlements.')->group(function () {
        Route::controller(SettlementController::class)->group(function () {
            Route::get('/', 'index')->middleware('role:HQ,HOSPITAL,SUPPLIER')->name('index');
            Route::get('/data', 'data')->middleware('role:HQ,HOSPITAL,SUPPLIER')->name('data');
        });
        Route::controller(ClosingController::class)->middleware('role:HQ')->group(function () {
            Route::get('/closing', 'index')->name('closing');
            Route::get('/closing/data', 'data')->name('closing.data');
            Route::post('/closing/close', 'close')->name('closing.close');
            Route::post('/closing/reopen', 'reopen')->name('closing.reopen');
        });
    });

    // 공급사 — 공급사/본사
    Route::middleware('role:SUPPLIER,HQ')->prefix('supplier')->name('supplier.')->group(function () {
        Route::controller(SupplierController::class)->group(function () {
            Route::get('/stocks', 'stocks')->name('stocks');
            Route::get('/stocks/data', 'stocksData')->name('stocks.data');
            Route::get('/shortages', 'shortages')->name('shortages');
            Route::get('/shortages/data', 'shortagesData')->name('shortages.data');
        });
    });

    // 감사 로그 / 시스템 관리 — 본사 전용
    Route::middleware('role:HQ')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs');
        Route::get('/audit-logs/data', [AuditLogController::class, 'data'])->name('audit-logs.data');

        // 접속/페이지 접근 로그
        Route::get('/access-logs', [AccessLogController::class, 'index'])->name('access-logs');
        Route::get('/access-logs/data', [AccessLogController::class, 'data'])->name('access-logs.data');

        // 업무 데이터 초기화(위험) — 대시보드에서 호출
        Route::post('/reset-data', [SystemController::class, 'resetData'])->name('reset-data');
    });

    // 기준정보(마스터) — 본사 전용
    Route::middleware('role:HQ')->prefix('master')->name('master.')->group(function () {
        // 제품 마스터 (Tabulator 그리드)
        Route::get('/products', fn () => view('master.products'))->name('products');
        Route::controller(ProductMasterController::class)->prefix('products')->name('products.')->group(function () {
            Route::get('/data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::post('/batch', 'batch')->name('batch');
            Route::patch('/{product}', 'update')->name('update');
            Route::delete('/', 'bulkDestroy')->name('bulkDestroy');
            Route::get('/export', 'export')->name('export');
            Route::get('/template', 'template')->name('template');
            Route::post('/import', 'import')->name('import');
            Route::get('/failures/{key}', 'failures')->name('failures');
        });

        // 거래처 마스터
        Route::get('/organizations', fn () => view('master.organizations'))->name('organizations');
        Route::controller(OrganizationMasterController::class)->prefix('organizations')->name('organizations.')->group(function () {
            Route::get('/data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::post('/batch', 'batch')->name('batch');
            Route::patch('/{organization}', 'update')->name('update');
            Route::delete('/', 'bulkDestroy')->name('bulkDestroy');
            Route::get('/export', 'export')->name('export');
            Route::get('/template', 'template')->name('template');
            Route::post('/import', 'import')->name('import');
            Route::get('/failures/{key}', 'failures')->name('failures');
        });

        // 사용자 마스터
        Route::get('/users', fn () => view('master.users'))->name('users');
        Route::controller(UserMasterController::class)->prefix('users')->name('users.')->group(function () {
            Route::get('/data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::post('/batch', 'batch')->name('batch'); // wwGrid 배치 저장(updated/added/deleted)
            Route::patch('/{user}', 'update')->name('update');
            Route::post('/{user}/reset-password', 'resetPassword')->name('resetPassword');
            Route::delete('/', 'bulkDestroy')->name('bulkDestroy');
            Route::get('/export', 'export')->name('export');
            Route::get('/template', 'template')->name('template');
            Route::post('/import', 'import')->name('import');
            Route::get('/failures/{key}', 'failures')->name('failures');
        });

        // 안전재고 마스터
        Route::get('/safety-stocks', fn () => view('master.safety-stocks'))->name('safety-stocks');
        Route::controller(SafetyStockMasterController::class)->prefix('safety-stocks')->name('safety-stocks.')->group(function () {
            Route::get('/data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::post('/batch', 'batch')->name('batch');
            Route::patch('/{key}', 'update')->name('update');
            Route::delete('/', 'bulkDestroy')->name('bulkDestroy');
            Route::post('/auto-suggest', 'autoSuggest')->name('autoSuggest');
            Route::get('/export', 'export')->name('export');
            Route::get('/template', 'template')->name('template');
            Route::post('/import', 'import')->name('import');
            Route::get('/failures/{key}', 'failures')->name('failures');
        });
    });
});

require __DIR__.'/auth.php';
