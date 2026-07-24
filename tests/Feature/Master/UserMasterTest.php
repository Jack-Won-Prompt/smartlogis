<?php

declare(strict_types=1);

use App\Enums\OrgType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('본사만 사용자 마스터에 접근할 수 있다', function () {
    actingAsRole(OrgType::HQ);
    $this->get('/master/users')->assertOk();
});

it('비본사 역할은 사용자 마스터 접근이 차단된다(403)', function () {
    actingAsRole(OrgType::WAREHOUSE);
    $this->get('/master/users')->assertForbidden();
});

it('그리드 데이터는 페이지네이션 구조로 반환된다', function () {
    actingAsRole(OrgType::HQ);

    $this->getJson('/master/users/data?page=1&size=10')
        ->assertOk()
        ->assertJsonStructure(['last_page', 'total', 'data' => [['id', 'login_id', 'name', 'role', 'org_id', 'org_name', 'status']]]);
});

it('새 사용자를 등록하면 임시 비밀번호가 해시로 발급된다', function () {
    actingAsRole(OrgType::HQ);
    $org = Organization::factory()->hospital()->create();

    $res = $this->postJson('/master/users', [
        'email' => 'newuser@smartlogis.test', 'name' => '신규',
        'role' => OrgType::HOSPITAL->value, 'org_id' => $org->id,
    ])->assertOk()->assertJsonPath('login_id', 'newuser@smartlogis.test'); // login_id 미입력 → 이메일로 자동 세팅

    $temp = $res->json('temp_password');
    expect($temp)->not->toBeEmpty();

    $user = User::where('email', 'newuser@smartlogis.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->status)->toBe(UserStatus::ACTIVE)
        ->and((bool) $user->is_active)->toBeTrue()
        ->and(Hash::check($temp, $user->password))->toBeTrue(); // 응답 평문이 저장 해시와 일치
});

it('중복 이메일 는 422를 반환한다', function () {
    actingAsRole(OrgType::HQ);
    $org = Organization::factory()->hospital()->create();
    User::factory()->create(['email' => 'dup@smartlogis.test', 'org_id' => $org->id]);

    $this->postJson('/master/users', [
        'email' => 'dup@smartlogis.test', 'name' => '중복', 'role' => OrgType::HOSPITAL->value, 'org_id' => $org->id,
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});

it('인라인으로 이름을 수정한다', function () {
    actingAsRole(OrgType::HQ);
    $user = User::factory()->create(['name' => '옛이름']);

    $this->patchJson("/master/users/{$user->id}", ['field' => 'name', 'value' => '새이름'])
        ->assertOk()->assertJsonPath('name', '새이름');

    expect($user->fresh()->name)->toBe('새이름');
});

it('상태를 SUSPENDED 로 바꾸면 is_active 도 동기화된다', function () {
    actingAsRole(OrgType::HQ);
    $user = User::factory()->create(['status' => UserStatus::ACTIVE, 'is_active' => true]);

    $this->patchJson("/master/users/{$user->id}", ['field' => 'status', 'value' => UserStatus::SUSPENDED->value])
        ->assertOk();

    expect((bool) $user->fresh()->is_active)->toBeFalse();
});

it('비밀번호 초기화는 새 임시 비밀번호를 반환하고 해시를 바꾼다', function () {
    actingAsRole(OrgType::HQ);
    $user = User::factory()->create();
    $before = $user->password;

    $res = $this->postJson("/master/users/{$user->id}/reset-password")->assertOk();
    $temp = $res->json('temp');

    expect($temp)->not->toBeEmpty()
        ->and($user->fresh()->password)->not->toBe($before)
        ->and(Hash::check($temp, $user->fresh()->password))->toBeTrue();
});

it('일괄 삭제는 동작하되 자기 자신은 제외한다', function () {
    $me = actingAsRole(OrgType::HQ);
    $others = User::factory()->count(2)->create();

    $this->deleteJson('/master/users', ['ids' => [$me->id, ...$others->pluck('id')->all()]])
        ->assertOk()->assertJsonPath('deleted', 2);

    expect(User::find($me->id))->not->toBeNull()          // 자기 자신 보존
        ->and(User::whereIn('id', $others->pluck('id'))->count())->toBe(0);
});
