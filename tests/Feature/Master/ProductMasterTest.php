<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\Product;

it('본사만 제품 마스터에 접근할 수 있다', function () {
    actingAsRole(OrgType::HQ);
    $this->get('/master/products')->assertOk();
});

it('비본사 역할은 제품 마스터 접근이 차단된다(403)', function () {
    actingAsRole(OrgType::HOSPITAL);
    $this->get('/master/products')->assertForbidden();
});

it('그리드 데이터는 페이지네이션 구조로 반환된다', function () {
    actingAsRole(OrgType::HQ);
    $supplier = Organization::factory()->supplier()->create();
    Product::factory()->count(3)->create(['supplier_id' => $supplier->id]);

    $this->getJson('/master/products/data?page=1&size=10')
        ->assertOk()
        ->assertJsonStructure(['last_page', 'total', 'data' => [['id', 'product_code', 'product_name', 'supplier_name', 'sales_price', 'is_active']]]);
});

it('새 제품을 등록한다', function () {
    actingAsRole(OrgType::HQ);
    $supplier = Organization::factory()->supplier()->create();

    $this->postJson('/master/products', [
        'product_code' => 'NEW-001',
        'product_name' => '테스트 카테터',
        'supplier_id' => $supplier->id,
        'storage_type' => 'ROOM',
        'sales_price' => 1500,
    ])->assertOk()->assertJsonPath('product_code', 'NEW-001');

    expect(Product::where('product_code', 'NEW-001')->exists())->toBeTrue();
});

it('중복 제품코드는 422를 반환한다', function () {
    actingAsRole(OrgType::HQ);
    $supplier = Organization::factory()->supplier()->create();
    Product::factory()->create(['product_code' => 'DUP-1', 'supplier_id' => $supplier->id]);

    $this->postJson('/master/products', [
        'product_code' => 'DUP-1',
        'product_name' => '중복',
        'supplier_id' => $supplier->id,
        'storage_type' => 'ROOM',
        'sales_price' => 10,
    ])->assertStatus(422)->assertJsonValidationErrorFor('product_code');
});

it('인라인 셀 수정이 저장된다', function () {
    actingAsRole(OrgType::HQ);
    $supplier = Organization::factory()->supplier()->create();
    $product = Product::factory()->create(['supplier_id' => $supplier->id, 'sales_price' => 100]);

    $this->patchJson("/master/products/{$product->id}", ['field' => 'sales_price', 'value' => 9999])
        ->assertOk();

    expect((float) $product->fresh()->sales_price)->toBe(9999.0);
});

it('일괄 삭제가 동작한다', function () {
    actingAsRole(OrgType::HQ);
    $supplier = Organization::factory()->supplier()->create();
    $products = Product::factory()->count(3)->create(['supplier_id' => $supplier->id]);

    $this->deleteJson('/master/products', ['ids' => $products->pluck('id')->all()])
        ->assertOk()->assertJsonPath('deleted', 3);

    expect(Product::count())->toBe(0);
});

it('필터 검색이 데이터를 좁힌다', function () {
    actingAsRole(OrgType::HQ);
    $supplier = Organization::factory()->supplier()->create();
    Product::factory()->create(['product_name' => '고유한스텐트', 'supplier_id' => $supplier->id]);
    Product::factory()->create(['product_name' => '다른품목', 'supplier_id' => $supplier->id]);

    $res = $this->getJson('/master/products/data?keyword=고유한')->assertOk();
    expect($res->json('total'))->toBe(1);
});
