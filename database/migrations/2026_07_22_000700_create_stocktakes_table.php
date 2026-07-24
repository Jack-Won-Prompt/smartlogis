<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stocktakes / stocktake_items — 재고 실사. 확정 시 diff_qty 만큼 ADJUST 트랜잭션을 생성한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->string('stocktake_no', 30)->unique();    // ST-YYYYMMDD-####
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 20)->index();           // StocktakeStatus
            $table->date('count_date');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stocktake_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stocktake_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lot_id')->constrained('product_lots')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('system_qty')->default(0);
            $table->integer('counted_qty')->nullable();
            $table->integer('diff_qty')->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['stocktake_id', 'lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_items');
        Schema::dropIfExists('stocktakes');
    }
};
