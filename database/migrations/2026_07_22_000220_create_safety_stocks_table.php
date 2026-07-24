<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * safety_stocks — 병원 × 품목 안전재고 기준. 자동 보충 판단의 근거.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_stocks', function (Blueprint $table) {
            $table->foreignId('hospital_id')->constrained('organizations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('safety_qty')->default(0);   // 미달 시 보충 트리거
            $table->unsignedInteger('max_qty')->default(0);      // 최대 적정 재고
            $table->unsignedInteger('reorder_qty')->default(0);  // 1회 보충 수량
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->primary(['hospital_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_stocks');
    }
};
