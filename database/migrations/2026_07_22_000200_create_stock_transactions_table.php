<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_transactions — 재고 변경의 유일한 경로(원장). 절대 UPDATE/DELETE 하지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_type', 20)->index();                  // TxType
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete(); // 재고 위치
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lot_id')->constrained('product_lots')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('qty');                                  // 입고 +, 출고 -
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('ref_type', 20)->nullable();              // RefType
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['org_id', 'product_id', 'lot_id']);
            $table->index(['ref_type', 'ref_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
