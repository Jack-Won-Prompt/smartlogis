<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 모바일 앱(Flutter) 전용 개인 액세스 토큰.
 *
 * 웹은 세션 인증을 유지하고, 모바일은 Bearer 토큰으로 인증한다.
 * 평문 토큰은 발급 시 1회만 반환하고 DB 에는 sha256 해시만 저장한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                 // 기기 이름 (예: "SM-G991N")
            $table->string('token_hash', 64)->unique();  // sha256 hex
            $table->string('platform', 20)->nullable();  // android | ios
            $table->string('push_token', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
