<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 배송 증빙 — 기사가 병원에 물건을 넘길 때 남기는 현장 기록.
 *
 * 출고 1건에 증빙 1건(unique). 사진은 여러 장이라 따로 뺀다.
 * 인수 서명은 나중에 "받았다/못 받았다" 분쟁의 근거가 되므로 지우지 않는다
 * (출고가 지워지면 함께 지운다).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_id')->unique()->constrained()->cascadeOnDelete();

            // 인수자 — 서명한 사람. 서명 이미지만으로는 누구인지 알 수 없다.
            $table->string('signer_name')->nullable();
            $table->string('signature_path')->nullable();

            $table->string('memo', 500)->nullable();

            // 실제로 전달한 사람(기사) 과 시각.
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });

        Schema::create('delivery_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_proof_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_photos');
        Schema::dropIfExists('delivery_proofs');
    }
};
