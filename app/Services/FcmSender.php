<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM HTTP v1 발송기.
 *
 * 레거시 서버 키(`https://fcm.googleapis.com/fcm/send`)는 2024년에 폐지됐다.
 * v1 은 서비스 계정으로 OAuth2 액세스 토큰을 받아 Bearer 로 보내야 한다.
 *
 * composer 의존성을 늘리지 않으려고(google/apiclient 는 무겁다) JWT 서명을
 * openssl 로 직접 한다 — 필요한 건 RS256 서명 한 번뿐이다.
 */
class FcmSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** 액세스 토큰 캐시 키. 토큰은 1시간 유효하므로 매 발송마다 받지 않는다. */
    private const CACHE_KEY = 'fcm:access_token';

    public function enabled(): bool
    {
        return (bool) config('fcm.enabled') && $this->credentials() !== null;
    }

    /**
     * 여러 기기에 같은 알림을 보낸다.
     *
     * @param  array<int, string>  $tokens  기기 푸시 토큰
     * @param  array<string, string>  $data  앱이 탭 처리에 쓰는 부가 데이터
     * @return array{sent: int, failed: int, invalid: array<int, string>}
     *                                                                    invalid 는 더 이상 유효하지 않은 토큰 — 호출 측에서 지워야 한다.
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === [] || ! $this->enabled()) {
            return ['sent' => 0, 'failed' => 0, 'invalid' => []];
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return ['sent' => 0, 'failed' => count($tokens), 'invalid' => []];
        }

        $projectId = (string) config('fcm.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $sent = 0;
        $failed = 0;
        $invalid = [];

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    // data 값은 반드시 문자열이어야 한다(FCM 규격).
                    'data' => array_map(static fn ($v) => (string) $v, $data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => (string) config('fcm.android_channel_id'),
                            // 앱이 꺼져 있어도 트레이에 쌓이도록.
                            'default_sound' => true,
                        ],
                    ],
                    'apns' => [
                        'payload' => ['aps' => ['sound' => 'default', 'badge' => 1]],
                    ],
                ],
            ];

            try {
                $res = Http::withToken($accessToken)
                    ->timeout(10)
                    ->post($url, $payload);

                if ($res->successful()) {
                    $sent++;

                    continue;
                }

                $failed++;

                // 앱 삭제·재설치로 죽은 토큰. 계속 두면 매번 실패하므로 정리 대상.
                $status = $res->json('error.status');
                if (in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                    $invalid[] = $token;
                }

                $this->log('warning', 'FCM 발송 실패', [
                    'status' => $res->status(),
                    'error' => $res->json('error.message'),
                ]);
            } catch (\Throwable $e) {
                $failed++;
                $this->log('error', 'FCM 발송 예외: '.$e->getMessage());
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'invalid' => $invalid];
    }

    /**
     * 서비스 계정으로 OAuth2 액세스 토큰을 받는다(1시간 캐시).
     */
    private function accessToken(): ?string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(50), function (): ?string {
            $cred = $this->credentials();
            if ($cred === null) {
                return null;
            }

            $jwt = $this->signedJwt($cred);
            if ($jwt === null) {
                return null;
            }

            try {
                $res = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if (! $res->successful()) {
                    $this->log('error', 'FCM 액세스 토큰 발급 실패', ['body' => $res->body()]);

                    return null;
                }

                return $res->json('access_token');
            } catch (\Throwable $e) {
                $this->log('error', 'FCM 토큰 발급 예외: '.$e->getMessage());

                return null;
            }
        });
    }

    /**
     * 서비스 계정 개인키로 RS256 JWT 를 만든다.
     *
     * @param  array<string, mixed>  $cred
     */
    private function signedJwt(array $cred): ?string
    {
        $now = time();

        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64url(json_encode([
            'iss' => $cred['client_email'] ?? '',
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        $ok = openssl_sign(
            "{$header}.{$claims}",
            $signature,
            (string) ($cred['private_key'] ?? ''),
            OPENSSL_ALGO_SHA256,
        );

        if (! $ok) {
            $this->log('error', 'FCM JWT 서명 실패 — 서비스 계정 private_key 를 확인하세요.');

            return null;
        }

        return "{$header}.{$claims}.".$this->base64url($signature);
    }

    /**
     * 서비스 계정 JSON 을 읽는다. 파일이 없으면 null(= 발송 비활성).
     *
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        $path = (string) config('fcm.credentials');

        if ($path === '' || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) && isset($json['client_email'], $json['private_key'])
            ? $json
            : null;
    }

    private function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $channel = config('fcm.log_channel');

        $channel
            ? Log::channel($channel)->{$level}($message, $context)
            : Log::{$level}($message, $context);
    }
}
