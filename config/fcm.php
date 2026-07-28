<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| FCM(Firebase Cloud Messaging) 설정
|--------------------------------------------------------------------------
|
| 모바일 앱은 알림 센터를 1분 폴링으로 갱신하는데, 앱이 꺼져 있으면 아무것도
| 받지 못한다. 승인 요청·재고 미달처럼 놓치면 안 되는 알림을 위해 푸시를 붙인다.
|
| 서비스 계정 키 얻는 법:
|   Firebase 콘솔 → 프로젝트 설정 → 서비스 계정 → "새 비공개 키 생성"
|   내려받은 JSON 을 서버에 두고 아래 경로를 가리키게 한다.
|
| **키 파일은 저장소에 커밋하지 않는다.** 유출되면 제3자가 우리 사용자 전원에게
| 푸시를 보낼 수 있다.
|
*/

return [

    /**
     * 켜기/끄기. 키가 준비되기 전에는 꺼 둔다 —
     * 꺼져 있으면 발송을 조용히 건너뛰고 앱의 폴링만으로 동작한다.
     */
    'enabled' => (bool) env('FCM_ENABLED', false),

    /** Firebase 프로젝트 ID (예: smartlogis-sams). */
    'project_id' => env('FCM_PROJECT_ID', ''),

    /**
     * 서비스 계정 JSON 경로. 절대경로 또는 storage_path() 기준 상대경로.
     * 예: storage/app/fcm/service-account.json
     */
    'credentials' => env('FCM_CREDENTIALS', storage_path('app/fcm/service-account.json')),

    /** 발송 실패를 어디까지 기록할지. 운영에서는 error 만 남기는 편이 조용하다. */
    'log_channel' => env('FCM_LOG_CHANNEL', null),

    /**
     * 한 번에 보낼 최대 토큰 수. FCM HTTP v1 은 배치 API 가 없어 토큰마다
     * 요청을 보내야 하므로, 대상이 많으면 큐로 넘긴다.
     */
    'sync_limit' => (int) env('FCM_SYNC_LIMIT', 20),

    /** 안드로이드 알림 채널 ID — 앱과 반드시 같아야 소리/중요도가 적용된다. */
    'android_channel_id' => env('FCM_ANDROID_CHANNEL', 'smartlogis_default'),

];
