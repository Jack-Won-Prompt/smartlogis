<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 계정/데이터 삭제 요청 — 플레이스토어 요건(공개 삭제 요청 경로)용.
 * 비로그인 사용자도 제출할 수 있으므로 이메일로 본인 확인 후 관리자가 처리한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('request_type')->default('ACCOUNT'); // ACCOUNT | DATA
            $table->text('reason')->nullable();
            $table->string('status')->default('RECEIVED');       // RECEIVED | PROCESSING | DONE | REJECTED
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
