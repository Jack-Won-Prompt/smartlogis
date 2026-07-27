<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 모바일 앱 배포 설정
|--------------------------------------------------------------------------
|
| 플레이스토어를 거치지 않는 사이드로딩 배포라 스토어의 자동 업데이트가 없다.
| 앱이 시작·복귀할 때마다 GET /api/v1/app/version 으로 이 값을 읽어
| 자기 versionCode 와 비교한 뒤 업데이트 안내를 띄운다.
|
| 새 버전을 올릴 때 하는 일:
|   1) APK 를 public/app/ 에 업로드
|   2) .env 의 APP_ANDROID_* 값을 갱신 (또는 아래 기본값 수정)
|   3) php artisan config:cache
|
| min_version 은 "이 버전 미만은 강제 업데이트" 기준이다.
| API 계약이 깨지는 변경(재고·정산 로직 등)을 배포할 때만 올린다.
| 평상시에는 latest_version 만 올리고 min_version 은 그대로 둔다.
|
*/

return [

    'android' => [

        /** 최신 버전의 versionCode (pubspec.yaml 의 version 뒤 +숫자). */
        'latest_version' => (int) env('APP_ANDROID_LATEST_VERSION', 1),

        /** 사용자에게 보여줄 버전명. */
        'latest_version_name' => env('APP_ANDROID_LATEST_VERSION_NAME', '1.0.0'),

        /**
         * 이 값 **미만**의 versionCode 는 강제 업데이트(앱 사용 차단).
         * 평상시 latest_version 과 같게 두지 말 것 — 매 배포가 강제가 된다.
         */
        'min_version' => (int) env('APP_ANDROID_MIN_VERSION', 1),

        /** 설치 파일 주소. 기기 ABI 를 몰라도 되는 universal 을 기본으로 준다. */
        'download_url' => env(
            'APP_ANDROID_DOWNLOAD_URL',
            'https://smartlogis.co.kr/app/smartlogis-1.0.0-universal.apk'
        ),

        /** ABI 별 주소(앱이 자기 기기에 맞는 것을 골라 받는다 — 용량 절반). */
        'download_urls' => [
            'arm64-v8a' => env('APP_ANDROID_DOWNLOAD_URL_ARM64', 'https://smartlogis.co.kr/app/smartlogis-1.0.0-arm64.apk'),
            'armeabi-v7a' => env('APP_ANDROID_DOWNLOAD_URL_ARM32', 'https://smartlogis.co.kr/app/smartlogis-1.0.0-arm32.apk'),
        ],

        /** 안내 페이지(앱이 설치에 실패했을 때 열어 줄 곳). */
        'install_page_url' => env('APP_ANDROID_INSTALL_PAGE', 'https://smartlogis.co.kr/app/'),

        /** 변경사항 — 다이얼로그에 줄 단위로 표시된다. */
        'release_notes' => array_values(array_filter(
            explode('|', (string) env('APP_ANDROID_RELEASE_NOTES', ''))
        )),
    ],

];
