<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Env;

/*
|--------------------------------------------------------------------------
| putenv 어댑터 비활성화 (XAMPP 다중 앱 환경 필수)
|--------------------------------------------------------------------------
| 같은 Apache/PHP 프로세스에서 htdocs 의 다른 Laravel 앱이 먼저 처리되면 그 앱의
| .env 값이 putenv() 로 프로세스 환경에 남는다. Dotenv 는 immutable 로드라
| 이미 존재하는 환경변수를 덮어쓰지 않기 때문에, 그 다음 요청에서 SmartLogis 가
| 다른 앱의 DB 접속 정보를 그대로 쓰는 간헐적 오류가 발생한다.
| putenv 어댑터를 끄면 $_ENV/$_SERVER 만 보므로 항상 자기 .env 가 적용된다.
*/
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // 모바일 앱(Flutter) 전용 API. 웹의 /api/barcode/parse 와 겹치지 않도록 v1 프리픽스를 쓴다.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 역할 기반 접근 제어: routes/web.php 에서 `role:HQ,WAREHOUSE` 형태로 사용
        $middleware->alias([
            'role' => EnsureRole::class,
            'api.token' => AuthenticateApiToken::class,
        ]);

        // 접속/페이지 접근 로그(메인·게스트 포함 모든 웹 GET 페이지)
        $middleware->web(append: [\App\Http\Middleware\LogAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
