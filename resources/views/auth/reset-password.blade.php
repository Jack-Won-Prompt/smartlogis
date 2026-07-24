<x-guest-layout title="비밀번호 재설정">
    <div class="reveal is-visible">
        <h2 class="font-display text-2xl font-extrabold tracking-tight text-ink-900">비밀번호 재설정</h2>
        <p class="mt-2 text-sm text-ink-500">새 비밀번호를 입력해 계정을 다시 사용하세요.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-auth.field name="email" label="이메일" type="email" required autocomplete="email"
            :value="$request->email"
            :icon="'<rect x=\'3\' y=\'5\' width=\'18\' height=\'14\' rx=\'2\'/><path d=\'m3 7 9 6 9-6\'/>'" />

        <x-auth.field name="password" label="새 비밀번호" type="password" required autofocus autocomplete="new-password"
            placeholder="8자 이상"
            :icon="'<rect x=\'4\' y=\'10\' width=\'16\' height=\'11\' rx=\'2\'/><path d=\'M8 10V7a4 4 0 0 1 8 0v3\'/>'" />

        <x-auth.field name="password_confirmation" label="새 비밀번호 확인" type="password" required autocomplete="new-password"
            placeholder="다시 입력"
            :icon="'<rect x=\'4\' y=\'10\' width=\'16\' height=\'11\' rx=\'2\'/><path d=\'M8 10V7a4 4 0 0 1 8 0v3\'/>'" />

        <button type="submit" class="btn-primary w-full" data-magnetic>비밀번호 재설정</button>
    </form>
</x-guest-layout>
