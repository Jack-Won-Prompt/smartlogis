<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_lots — 제품 × Lot × 유통기한. 모든 재고/이동의 최소 단위.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('lot_no', 50);
            $table->date('expiry_date')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'lot_no']);
            $table->index('expiry_date');   // FEFO 정렬 / 유통기한 임박 조회
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lots');
    }
};
