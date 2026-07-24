<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * settlements / settlement_items — 월 정산. 사용분 승인 시 SALES/PURCHASE 쌍으로 항목이 쌓인다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('year_month', 7);                 // "2026-07"
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('settle_type', 20);               // SettleType
            $table->string('status', 20)->index();           // SettlementStatus
            $table->unsignedInteger('total_qty')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['year_month', 'org_id', 'settle_type']);
        });

        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('usage_report_item_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            // 같은 사용분 항목이 같은 정산서에 중복 반영되지 않도록 보장(승인 멱등성).
            $table->unique(['settlement_id', 'usage_report_item_id'], 'settlement_items_unique_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
    }
};
