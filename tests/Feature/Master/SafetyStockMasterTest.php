<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SafetyStock;
use Illuminate\Support\Facades\DB;

it('본사만 안전재고 마스터에 접근할 수 있다', function () {
    actingAsRole(OrgType::HQ);
    $this->get('/master/safety-stocks')->assertOk();
});

it('비본사 역할은 안전재고 마스터 접근이 차단된다(403)', function () {
    actingAsRole(OrgType::HOSPITAL);
    $this->get('/master/safety-stocks')->assertForbidden();
});

it('그리드 데이터는 페이지네이션 구조로 반환된다(복합키 id=h:p)', function () {
    actingAsRole(OrgType::HQ);
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create();
    SafetyStock::create(['hospital_id' => $hospital->id, 'product_id' => $product->id, 'safety_qty' => 10, 'max_qty' => 30, 'reorder_qty' => 20]);

    $res = $this->getJson('/master/safety-stocks/data')->assertOk()
        ->assertJsonStructure(['last_page', 'total', 'data' => [['id', 'hospital_id', 'product_id', 'safety_qty', 'max_qty', 'reorder_qty']]]);
    expect($res->json('data.0.id'))->toBe("{$hospital->id}:{$product->id}");
});

it('새 안전재고를 등록하고 max/reorder 기본값을 산출한다', function () {
    actingAsRole(OrgType::HQ);
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create();

    $this->postJson('/master/safety-stocks', [
        'hospital_id' => $hospital->id, 'product_id' => $product->id, 'safety_qty' => 20,
    ])->assertOk();

    $row = SafetyStock::where('hospital_id', $hospital->id)->where('product_id', $product->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->safety_qty)->toBe(20)
        ->and($row->max_qty)->toBe(60)      // safety * 3
        ->and($row->reorder_qty)->toBe(40); // safety * 2
});

it('그리드 신규행(max/reorder=0)도 안전재고 기준으로 자동 산출한다', function () {
    actingAsRole(OrgType::HQ);
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create();

    // 그리드 addBlankRow 는 max_qty/reorder_qty 를 0 으로 전송한다.
    $this->postJson('/master/safety-stocks', [
        'hospital_id' => $hospital->id, 'product_id' => $product->id,
        'safety_qty' => 25, 'max_qty' => 0, 'reorder_qty' => 0,
    ])->assertOk();

    $row = SafetyStock::where('hospital_id', $hospital->id)->where('product_id', $product->id)->first();
    expect($row->safety_qty)->toBe(25)
        ->and($row->max_qty)->toBe(75)      // 0 이 아니라 safety*3 로 산출
        ->and($row->reorder_qty)->toBe(50); // safety*2
});

it('병원이 아닌 조직을 대상으로 하면 422', function () {
    actingAsRole(OrgType::HQ);
    $supplier = Organization::factory()->supplier()->create();
    $product = Product::factory()->create();

    $this->postJson('/master/safety-stocks', [
        'hospital_id' => $supplier->id, 'product_id' => $product->id, 'safety_qty' => 20,
    ])->assertStatus(422)->assertJsonValidationErrorFor('hospital_id');
});

it('복합키(h:p)로 인라인 수정한다', function () {
    actingAsRole(OrgType::HQ);
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create();
    SafetyStock::create(['hospital_id' => $hospital->id, 'product_id' => $product->id, 'safety_qty' => 10, 'max_qty' => 30, 'reorder_qty' => 20]);

    $this->patchJson("/master/safety-stocks/{$hospital->id}:{$product->id}", ['field' => 'safety_qty', 'value' => 55])
        ->assertOk()->assertJsonPath('safety_qty', 55);

    expect(DB::table('safety_stocks')->where('hospital_id', $hospital->id)->where('product_id', $product->id)->value('safety_qty'))->toBe(55);
});

it('음수 수량 수정은 422', function () {
    actingAsRole(OrgType::HQ);
    $hospital = Organization::factory()->hospital()->create();
    $product = Product::factory()->create();
    SafetyStock::create(['hospital_id' => $hospital->id, 'product_id' => $product->id, 'safety_qty' => 10, 'max_qty' => 30, 'reorder_qty' => 20]);

    $this->patchJson("/master/safety-stocks/{$hospital->id}:{$product->id}", ['field' => 'safety_qty', 'value' => -5])
        ->assertStatus(422);
});

it('복합키 일괄 삭제가 동작한다', function () {
    actingAsRole(OrgType::HQ);
    $hospital = Organization::factory()->hospital()->create();
    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();
    SafetyStock::create(['hospital_id' => $hospital->id, 'product_id' => $p1->id, 'safety_qty' => 10, 'max_qty' => 30, 'reorder_qty' => 20]);
    SafetyStock::create(['hospital_id' => $hospital->id, 'product_id' => $p2->id, 'safety_qty' => 10, 'max_qty' => 30, 'reorder_qty' => 20]);

    $this->deleteJson('/master/safety-stocks', ['ids' => ["{$hospital->id}:{$p1->id}", "{$hospital->id}:{$p2->id}"]])
        ->assertOk()->assertJsonPath('deleted', 2);

    expect(SafetyStock::where('hospital_id', $hospital->id)->count())->toBe(0);
});
