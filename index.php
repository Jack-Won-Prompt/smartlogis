<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 루트 진입점 (XAMPP 서브폴더 http://localhost/smartlogis 용)
|--------------------------------------------------------------------------
| 실제 공개 디렉터리는 public/ 이지만, http://localhost/smartlogis 로 접속했을 때
| Laravel 이 /smartlogis 를 base 경로로 올바르게 인식하도록 루트에서 부트스트랩한다.
| 정적 파일(빌드 자산·이미지 등)은 .htaccess 가 public/ 에서 직접 서빙한다.
*/

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 점검 모드 확인
if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer 오토로더
require __DIR__.'/vendor/autoload.php';

// Laravel 부트스트랩 및 요청 처리
(require_once __DIR__.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
