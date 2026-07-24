<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_balances — 현재고 캐시. StockService 안에서만, 원장과 같은 트랜잭션으로 갱신한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lot_id')->constrained('product_lots')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->primary(['org_id', 'product_id', 'lot_id']);
            $table->index(['product_id', 'org_id']);   // 공급사 화면: 제품 기준 병원별 재고
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
