<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

it('로그인 화면을 렌더한다', function () {
    $this->get('/login')->assertOk();
});

it('이메일과 비밀번호로 인증된다', function () {
    $org = Organization::factory()->hq()->create();
    $user = User::factory()->create([
        'email' => 'hq@smartlogis.test',
        'password' => Hash::make('secret123'),
        'role' => OrgType::HQ,
        'org_id' => $org->id,
    ]);

    $this->post('/login', [
        'email' => 'hq@smartlogis.test',
        'password' => 'secret123',
    ])->assertRedirect(route('workspace'));

    $this->assertAuthenticatedAs($user);
});

it('잘못된 비밀번호는 거부된다', function () {
    $org = Organization::factory()->hq()->create();
    User::factory()->create([
        'email' => 'hq@smartlogis.test',
        'password' => Hash::make('secret123'),
        'org_id' => $org->id,
    ]);

    $this->post('/login', [
        'email' => 'hq@smartlogis.test',
        'password' => 'wrong',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('비활성 계정은 로그인할 수 없다', function () {
    $org = Organization::factory()->hq()->create();
    User::factory()->inactive()->create([
        'email' => 'old@smartlogis.test',
        'password' => Hash::make('secret123'),
        'org_id' => $org->id,
    ]);

    $this->post('/login', [
        'email' => 'old@smartlogis.test',
        'password' => 'secret123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('거래처 가입 신청 라우트가 존재한다', function () {
    expect(Route::has('register'))->toBeTrue();
});
