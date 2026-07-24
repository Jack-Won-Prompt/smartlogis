<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * 본사가 거래처 담당자를 초대한다. 초대 링크를 출력하며, 수신자는 링크에서
 * 최초 비밀번호를 설정해 계정을 활성화한다. 본사 초대 UI(Phase 3) 전까지 사용.
 *
 * 예) php artisan account:invite hong@hosp.com --login=hong --name=홍길동 --org=HOSP-SEOUL
 */
class InviteUserCommand extends Command
{
    protected $signature = 'account:invite
        {email : 초대할 담당자 이메일}
        {--login= : 부여할 로그인 아이디}
        {--name= : 담당자명}
        {--org= : 소속 조직 코드(organizations.code)}
        {--days=7 : 초대 링크 유효기간(일)}';

    protected $description = '거래처 담당자에게 초대 링크를 발급한다';

    public function handle(): int
    {
        $org = Organization::where('code', $this->option('org'))->first();
        if ($org === null) {
            $this->error("조직 코드를 찾을 수 없습니다: {$this->option('org')}");

            return self::FAILURE;
        }

        $loginId = (string) ($this->option('login') ?? Str::before((string) $this->argument('email'), '@'));

        if (User::where('login_id', $loginId)->exists()) {
            $this->error("이미 사용 중인 아이디입니다: {$loginId}");

            return self::FAILURE;
        }

        $invitation = Invitation::create([
            'token' => Str::random(48),
            'email' => (string) $this->argument('email'),
            'login_id' => $loginId,
            'name' => (string) ($this->option('name') ?? $loginId),
            'role' => $org->org_type,
            'org_id' => $org->id,
            'invited_by' => null,
            'expires_at' => now()->addDays((int) $this->option('days')),
        ]);

        $url = route('invitation.show', $invitation->token);

        $this->info('초대가 생성되었습니다.');
        $this->line("  대상 : {$invitation->name} <{$invitation->email}>");
        $this->line("  조직 : {$org->name} ({$org->code})");
        $this->line("  링크 : {$url}");

        return self::SUCCESS;
    }
}
