<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

function makeInvitation(array $overrides = []): Invitation
{
    $org = Organization::factory()->hospital()->create();

    return Invitation::create(array_merge([
        'token' => Str::random(48),
        'email' => 'doc@hosp.test',
        'login_id' => 'doc1',
        'name' => '김의사',
        'role' => OrgType::HOSPITAL,
        'org_id' => $org->id,
        'expires_at' => now()->addDays(7),
    ], $overrides));
}

it('유효한 초대 링크는 수락 화면을 보여준다', function () {
    $invitation = makeInvitation();

    $this->get(route('invitation.show', $invitation->token))
        ->assertOk()
        ->assertSee($invitation->login_id);
});

it('만료된 초대는 410을 반환한다', function () {
    $invitation = makeInvitation(['expires_at' => now()->subDay()]);

    $this->get(route('invitation.show', $invitation->token))->assertStatus(410);
});

it('초대 수락 시 최초 비밀번호로 ACTIVE 계정이 생성된다', function () {
    $invitation = makeInvitation();

    $this->post(route('invitation.accept', $invitation->token), [
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('login'));

    $user = User::where('login_id', 'doc1')->firstOrFail();
    expect($user->status)->toBe(UserStatus::ACTIVE);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    // 설정한 비밀번호로 로그인 가능
    $this->post('/login', ['login_id' => 'doc1', 'password' => 'password123'])
        ->assertRedirect(route('workspace'));
});

it('이미 수락된 초대는 재사용할 수 없다', function () {
    $invitation = makeInvitation(['accepted_at' => now()]);

    $this->get(route('invitation.show', $invitation->token))->assertStatus(410);
});
