<x-guest-layout title="비밀번호 찾기">
    <div class="reveal is-visible">
        <a href="{{ route('login') }}" class="mb-6 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-400 hover:text-brand-600">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            로그인으로
        </a>
        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-100">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 15v2"/>
            </svg>
        </span>
        <h2 class="mt-5 font-display text-2xl font-extrabold tracking-tight text-ink-900">비밀번호 찾기</h2>
        <p class="mt-2 text-sm text-ink-500">가입 시 등록한 이메일로 비밀번호 재설정 링크를 보내드립니다.</p>
    </div>

    @if (session('status'))
        <div class="mt-6 flex items-start gap-2 rounded-xl border border-ok-600/20 bg-ok-100 px-4 py-3 text-sm text-ok-600">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-auth.field name="email" label="이메일" type="email" required autofocus autocomplete="email"
            placeholder="you@company.com"
            :icon="'<rect x=\'3\' y=\'5\' width=\'18\' height=\'14\' rx=\'2\'/><path d=\'m3 7 9 6 9-6\'/>'" />

        <button type="submit" class="btn-primary w-full" data-magnetic>
            재설정 링크 보내기
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
        </button>
    </form>
</x-guest-layout>
