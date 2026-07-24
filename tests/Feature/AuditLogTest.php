<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Services\ClosingService;

/**
 * Phase 7 — 감사 로그. Observer 가 도메인 모델 CRUD 를,
 * 서비스가 마감/마감취소를 audit_logs 에 자동 기록한다.
 */
it('제품 수정 시 변경 전/후가 audit_logs 에 기록된다', function () {
    actingAsRole(OrgType::HQ);
    $product = Product::factory()->create(['spec' => '구모델']);
    AuditLog::query()->delete(); // 생성 로그 제거 후 수정만 관찰

    $product->update(['spec' => '신모델']);

    $log = AuditLog::where('entity', 'Product')->where('action', AuditAction::UPDATE)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->entity_id)->toBe($product->id)
        ->and($log->before['spec'])->toBe('구모델')
        ->and($log->after['spec'])->toBe('신모델');
});

it('사용자 생성 로그는 비밀번호를 마스킹한다', function () {
    actingAsRole(OrgType::HQ);
    AuditLog::query()->delete();

    User::factory()->create();

    $log = AuditLog::where('entity', 'User')->where('action', AuditAction::CREATE)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->after['password'] ?? null)->toBe('***');
});

it('월 마감/마감취소는 CLOSE/REOPEN 으로 기록된다', function () {
    actingAsRole(OrgType::HQ);
    $service = app(ClosingService::class);

    $service->close('2026-06');
    expect(AuditLog::where('action', AuditAction::CLOSE)->where('entity', 'MonthlyClosing')->exists())->toBeTrue();

    $service->reopen('2026-06');
    expect(AuditLog::where('action', AuditAction::REOPEN)->where('entity', 'MonthlyClosing')->exists())->toBeTrue();
});
