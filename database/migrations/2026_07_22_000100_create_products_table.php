<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products — 의료 제품 마스터. gtin 은 바코드(GS1 AI 01) 매칭 키.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 50)->unique();
            $table->string('product_name');
            $table->string('udi_di', 50)->nullable();
            $table->string('gtin', 14)->nullable()->index();      // 바코드 매칭 키
            $table->string('edi_code', 30)->nullable();           // 보험코드
            $table->string('spec', 100)->nullable();              // 규격/모델
            $table->string('manufacturer', 100)->nullable();
            $table->foreignId('supplier_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('unit', 10)->default('EA');
            $table->unsignedInteger('box_qty')->default(1);       // BOX 당 EA
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('sales_price', 15, 2)->default(0);
            $table->string('storage_type', 10)->default('ROOM');  // StorageType
            $table->boolean('is_sterile')->default(false);
            $table->boolean('use_lot_control')->default(true);
            $table->boolean('use_expiry')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
