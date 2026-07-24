<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회원가입 승인 / 초대 / 비밀번호 재설정 흐름을 위한 users 확장 +
 * password_reset_tokens(비밀번호 찾기) + invitations(본사 초대) 테이블.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('login_id');
            $table->string('status', 20)->default('ACTIVE')->index()->after('org_id'); // UserStatus
            $table->timestamp('approved_at')->nullable()->after('last_login_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at');
        });

        // 비밀번호 찾기(이메일 재설정)용
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // 본사 초대 → 초대 링크 → 최초 비밀번호 설정
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('email');
            $table->string('login_id', 50);
            $table->string('name', 50);
            $table->string('role', 20);                       // OrgType
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // 수락 시 생성된 계정
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'status', 'approved_at', 'approved_by']);
        });
    }
};
