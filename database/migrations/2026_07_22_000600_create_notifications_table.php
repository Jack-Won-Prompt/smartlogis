<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * notifications — 화면 요건에 맞춘 자체 알림 테이블(Laravel 기본 notifications 미사용).
 * 역할 + 조직 대상으로 발송하고 알림 센터에서 조회한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('noti_type', 30)->index();        // NotiType
            $table->string('severity', 10)->index();         // Severity
            $table->string('target_role', 20)->nullable()->index();
            $table->foreignId('target_org_id')->nullable()->constrained('organizations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title');
            $table->string('message', 500);
            $table->string('link_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_org_id', 'is_read']);
            $table->index(['target_role', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
