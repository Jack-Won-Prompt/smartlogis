<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 가입 신청(PENDING) 계정을 승인해 활성화한다.
 * 본사 승인 UI(Phase 3) 도입 전까지 사용하는 운영 명령.
 *
 * 예) php artisan account:approve hospital-seoul
 */
class ApproveAccountCommand extends Command
{
    protected $signature = 'account:approve {login_id : 승인할 계정 아이디}';

    protected $description = '가입 신청(PENDING) 계정을 승인하고 소속 조직을 활성화한다';

    public function handle(): int
    {
        $user = User::where('login_id', $this->argument('login_id'))->first();

        if ($user === null) {
            $this->error("계정을 찾을 수 없습니다: {$this->argument('login_id')}");

            return self::FAILURE;
        }

        if ($user->status === UserStatus::ACTIVE) {
            $this->warn('이미 활성화된 계정입니다.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user) {
            $user->update([
                'status' => UserStatus::ACTIVE,
                'is_active' => true,
                'approved_at' => now(),
            ]);
            $user->organization->update(['is_active' => true]);
        });

        $this->info("승인 완료: {$user->name} ({$user->login_id}) · {$user->organization->name}");

        return self::SUCCESS;
    }
}
