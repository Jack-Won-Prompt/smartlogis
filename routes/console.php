<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| SmartLogis 배치 스케줄 (CLAUDE.md §7.2, §7.4)
|--------------------------------------------------------------------------
| 운영: `php artisan schedule:work`(개발) / cron `* * * * * php artisan schedule:run`(운영)
*/

// 유통기한 경고 — 매일 06:00
Schedule::command('expiry:alert')->dailyAt('06:00')->withoutOverlapping();

// 자동 보충 점검 — 매일 05:30
Schedule::command('replenishment:check')->dailyAt('05:30')->withoutOverlapping();
