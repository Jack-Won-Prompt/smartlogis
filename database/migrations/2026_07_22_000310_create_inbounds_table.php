<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inbounds / inbound_items — 입고. 공급사→창고(ASN)와 창고→병원 두 방향을 공용으로 처리한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbounds', function (Blueprint $table) {
            $table->id();
            $table->string('inbound_no', 30)->unique();      // IB-YYYYMMDD-####
            $table->string('direction', 20)->index();        // InboundDirection
            $table->foreignId('from_org_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('to_org_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 20)->index();           // InboundStatus
            $table->date('planned_date')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('outbound_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete(); // 창고→병원일 때 원 출고
            $table->string('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['to_org_id', 'status']);
        });

        Schema::create('inbound_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('lot_no', 50);
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('scanned_barcode')->nullable();   // GS1 원문 보관(추적)
            $table->timestamps();

            $table->index(['inbound_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_items');
        Schema::dropIfExists('inbounds');
    }
};
