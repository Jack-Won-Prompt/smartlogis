<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;

it('가입 신청 화면을 렌더한다', function () {
    $this->get('/register')->assertOk();
});

it('자체 가입 신청은 PENDING 계정과 비활성 조직을 만든다', function () {
    $response = $this->post('/register', [
        'role' => OrgType::SUPPLIER->value,
        'org_name' => '테스트공급사',
        'name' => '박담당',
        'email' => 'sup1@test.co.kr',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'agree' => '1',
    ]);

    $response->assertRedirect(route('login'));

    $user = User::where('email', 'sup1@test.co.kr')->firstOrFail();
    expect($user->status)->toBe(UserStatus::PENDING);
    expect($user->login_id)->toBe('sup1@test.co.kr'); // 이메일이 로그인 계정
    expect($user->organization->is_active)->toBeFalse();
    expect($user->organization->org_type)->toBe(OrgType::SUPPLIER);
});

it('PENDING 계정은 로그인할 수 없다', function () {
    $org = Organization::factory()->supplier()->create(['is_active' => false]);
    User::factory()->pending()->create([
        'email' => 'pend1@test.co.kr',
        'org_id' => $org->id,
    ]);

    $this->post('/login', ['email' => 'pend1@test.co.kr', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
