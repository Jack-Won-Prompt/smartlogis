<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * outbounds / outbound_items — 물류창고 → 거점병원 출고 지시. lot_id 는 FEFO 로 배정된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbounds', function (Blueprint $table) {
            $table->id();
            $table->string('outbound_no', 30)->unique();     // OB-YYYYMMDD-####
            $table->foreignId('warehouse_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('hospital_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 20)->index();           // OutboundStatus
            $table->string('source_type', 20);               // OutboundSourceType
            $table->date('planned_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hospital_id', 'status']);
        });

        Schema::create('outbound_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('product_lots')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['outbound_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_items');
        Schema::dropIfExists('outbounds');
    }
};
