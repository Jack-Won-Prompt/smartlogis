<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 매출 채널(사용분 분류) — 채널별 매출 리포트용. 기본값 DIRECT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_reports', function (Blueprint $table) {
            $table->string('sales_channel', 20)->default('DIRECT')->after('usage_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('usage_reports', function (Blueprint $table) {
            $table->dropColumn('sales_channel');
        });
    }
};
