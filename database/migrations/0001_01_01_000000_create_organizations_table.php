<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * organizations — 본사/물류창고/거점병원/공급사를 단일 테이블로 관리한다.
 * users 보다 먼저 생성되어야 하므로 파일명이 users 앞에 오도록 둔다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('org_type', 20)->index();          // OrgType
            $table->string('code', 30)->unique();             // 거래처 코드
            $table->string('name');
            $table->string('biz_reg_no', 20)->nullable();     // 사업자등록번호
            $table->string('hpid_no', 20)->nullable();        // 요양기관번호(병원)
            $table->string('address')->nullable();
            $table->string('tel', 30)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
