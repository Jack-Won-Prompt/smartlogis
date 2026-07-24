<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Inbound;
use App\Models\Organization;
use App\Models\Outbound;
use App\Models\Product;
use App\Models\SafetyStock;
use App\Models\Stocktake;
use App\Models\UsageReport;
use App\Models\User;
use App\Observers\AuditLogObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // N+1 방지: 로컬에서 지연 로딩을 즉시 에러로 드러낸다(CLAUDE.md §8).
        Model::preventLazyLoading(! $this->app->isProduction());

        // 대량 할당 오탐을 조기에 잡는다.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // 날짜는 서버 UTC 저장, 화면 표시는 별도 헬퍼에서 KST 로 변환한다.
        Date::use(Carbon::class);

        // 감사 로그: 마스터·문서 헤더의 CRUD 를 Observer 로 자동 기록(§7.6).
        // 재고 원장(stock_transactions/balances)은 그 자체가 이력이므로 제외한다.
        foreach ([Product::class, Organization::class, User::class, SafetyStock::class,
            UsageReport::class, Inbound::class, Outbound::class, Stocktake::class] as $model) {
            $model::observe(AuditLogObserver::class);
        }

        // 로그인/로그아웃 감사 기록.
        Event::listen(Login::class, fn (Login $e) => AuditLog::record(
            AuditAction::LOGIN, class_basename($e->user), (int) $e->user->getAuthIdentifier(),
        ));
        Event::listen(Logout::class, fn (Logout $e) => AuditLog::record(
            AuditAction::LOGOUT, class_basename($e->user), (int) $e->user->getAuthIdentifier(),
        ));

        // XAMPP 하위 경로(/smartlogis) 배포 대응: Livewire update 엔드포인트를
        // APP_URL 의 base path 를 포함하도록 재등록한다(기본 /livewire/update → /smartlogis/livewire/update).
        $basePath = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        if ($basePath !== '') {
            Livewire::setUpdateRoute(function ($handle) use ($basePath) {
                return Route::post($basePath.'/livewire/update', $handle);
            });
            Livewire::setScriptRoute(function ($handle) use ($basePath) {
                return Route::get($basePath.'/livewire/livewire.js', $handle);
            });
        }
    }
}
