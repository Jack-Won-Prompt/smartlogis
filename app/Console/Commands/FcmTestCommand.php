<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Services\FcmSender;
use Illuminate\Console\Command;

/**
 * FCM 설정·발송 점검용.
 *
 *   php artisan fcm:test                 설정만 확인(발송 안 함)
 *   php artisan fcm:test --user=3        해당 사용자의 기기로 실제 발송
 *   php artisan fcm:test --token=xxx     특정 토큰으로 발송
 *
 * 키를 넣고 나서 "왜 안 오지" 를 앱부터 뒤지지 않도록, 서버 단독으로
 * 어디까지 되는지 먼저 가를 수 있게 한다.
 */
class FcmTestCommand extends Command
{
    protected $signature = 'fcm:test
        {--user= : 이 사용자에게 등록된 모든 기기로 발송}
        {--token= : 특정 푸시 토큰으로 발송}
        {--title=SmartLogis 테스트}
        {--body=푸시 알림이 정상 동작합니다.}';

    protected $description = 'FCM 설정을 점검하고 테스트 알림을 보낸다';

    public function handle(FcmSender $fcm): int
    {
        $this->line('');
        $this->info('■ FCM 설정');
        $this->line('  enabled     : '.(config('fcm.enabled') ? 'true' : 'false'));
        $this->line('  project_id  : '.(config('fcm.project_id') ?: '(비어 있음)'));

        $path = (string) config('fcm.credentials');
        $this->line('  credentials : '.$path);
        $this->line('                '.(is_file($path) ? '✔ 파일 있음' : '✖ 파일 없음'));

        if (! $fcm->enabled()) {
            $this->line('');
            $this->warn('FCM 이 비활성 상태입니다. .env 를 확인하세요:');
            $this->line('  FCM_ENABLED=true');
            $this->line('  FCM_PROJECT_ID=<Firebase 프로젝트 ID>');
            $this->line('  FCM_CREDENTIALS=<서비스 계정 JSON 경로>');

            return self::FAILURE;
        }

        $tokens = $this->targetTokens();

        if ($tokens === []) {
            $this->line('');
            $this->info('설정은 정상입니다. 발송 대상을 지정하려면 --user 또는 --token 을 쓰세요.');
            $this->line('등록된 푸시 토큰 수: '.ApiToken::whereNotNull('push_token')->count());

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('■ 발송 — 대상 '.count($tokens).'개 기기');

        $result = $fcm->send(
            $tokens,
            (string) $this->option('title'),
            (string) $this->option('body'),
            ['link_url' => '/notifications', 'noti_type' => 'TEST', 'severity' => 'INFO'],
        );

        $this->line("  성공 {$result['sent']} / 실패 {$result['failed']}");

        if ($result['invalid'] !== []) {
            $this->warn('  무효 토큰 '.count($result['invalid']).'개 — 정리합니다.');
            ApiToken::whereIn('push_token', $result['invalid'])->update(['push_token' => null]);
        }

        return $result['sent'] > 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, string> */
    private function targetTokens(): array
    {
        if ($t = $this->option('token')) {
            return [(string) $t];
        }

        if ($u = $this->option('user')) {
            return ApiToken::query()
                ->where('user_id', (int) $u)
                ->whereNotNull('push_token')
                ->pluck('push_token')
                ->all();
        }

        return [];
    }
}
