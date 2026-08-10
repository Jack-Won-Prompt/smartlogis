<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 사용/반납 지연 리마인더 발송 시각 — 중복 발송 방지용.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->timestamp('close_reminded_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('outbounds', function (Blueprint $table) {
            $table->dropColumn('close_reminded_at');
        });
    }
};
