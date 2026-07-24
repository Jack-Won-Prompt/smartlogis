<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * usage_reports / usage_report_items — 병원 사용분 보고. 본사 승인 시 재고 차감 + 정산 생성.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_no', 40)->unique();       // UR-YYYYMM-HOSP-####
            $table->foreignId('hospital_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 20)->index();           // UsageStatus
            $table->date('usage_date')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hospital_id', 'status']);
            $table->index(['status', 'usage_date']);
        });

        Schema::create('usage_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usage_report_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('lot_id')->constrained('product_lots')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('dept', 50)->nullable();            // 사용부서
            $table->string('procedure_info')->nullable();      // 시술정보
            $table->string('scanned_barcode')->nullable();
            $table->timestamps();

            $table->index(['usage_report_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_report_items');
        Schema::dropIfExists('usage_reports');
    }
};
