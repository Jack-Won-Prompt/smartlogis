<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 반납(병원 → 창고) — 미사용분 회수. 등록 → 배송 → 수령확인(재고 복귀).
 * 수령확인 시 병원 재고 차감(RETURN_HOSPITAL) + 창고 재고 복귀(RETURN_TO_WH)를
 * StockService 단일 진입점으로 원자 처리한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 30)->unique();          // RT-YYYYMMDD-####
            $table->foreignId('hospital_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('organizations')->restrictOnDelete();
            $table->string('status', 20)->index();               // ReturnStatus
            $table->text('reason')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hospital_id', 'status']);
        });

        Schema::create('stock_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('lot_id')->constrained('product_lots')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_return_items');
        Schema::dropIfExists('stock_returns');
    }
};
