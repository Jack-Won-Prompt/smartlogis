<x-guest-layout title="초대 수락">
    <div class="reveal is-visible">
        <span class="pill bg-brand-50 text-brand-700 ring-1 ring-brand-100">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            본사 초대
        </span>
        <h2 class="mt-4 font-display text-2xl font-extrabold tracking-tight text-ink-900">SmartLogis에 오신 것을 환영합니다</h2>
        <p class="mt-2 text-sm text-ink-500">최초 비밀번호를 설정하면 바로 이용할 수 있습니다.</p>
    </div>

    {{-- 초대 정보 요약 --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <dl class="grid grid-cols-3 gap-3 text-sm">
            <div>
                <dt class="text-xs text-ink-400">거래처</dt>
                <dd class="mt-0.5 font-semibold text-ink-900">{{ $invitation->organization->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-ink-400">역할</dt>
                <dd class="mt-0.5 font-semibold text-ink-900">{{ $invitation->role->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-ink-400">아이디</dt>
                <dd class="mt-0.5 font-mono font-semibold text-ink-900">{{ $invitation->login_id }}</dd>
            </div>
        </dl>
    </div>

    <form method="POST" action="{{ route('invitation.accept', $invitation->token) }}" class="mt-6 space-y-5">
        @csrf
        <x-auth.field name="password" label="비밀번호 설정" type="password" required autofocus autocomplete="new-password"
            placeholder="8자 이상" hint="영문·숫자를 조합해 8자 이상 입력하세요."
            :icon="'<rect x=\'4\' y=\'10\' width=\'16\' height=\'11\' rx=\'2\'/><path d=\'M8 10V7a4 4 0 0 1 8 0v3\'/>'" />

        <x-auth.field name="password_confirmation" label="비밀번호 확인" type="password" required autocomplete="new-password"
            placeholder="다시 입력"
            :icon="'<rect x=\'4\' y=\'10\' width=\'16\' height=\'11\' rx=\'2\'/><path d=\'M8 10V7a4 4 0 0 1 8 0v3\'/>'" />

        <button type="submit" class="btn-primary w-full" data-magnetic>
            비밀번호 설정하고 시작하기
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>
</x-guest-layout>
