<x-guest-layout title="로그인">
    <div class="reveal is-visible">
        <h2 class="font-display text-2xl font-extrabold tracking-tight text-ink-900">로그인</h2>
        <p class="mt-2 text-sm text-ink-500">계정 정보로 SmartLogis 관제 화면에 접속하세요.</p>
    </div>

    {{-- 상태 메시지(비밀번호 재설정 완료 등) --}}
    @if (session('status'))
        <div class="mt-6 flex items-start gap-2 rounded-xl border border-ok-600/20 bg-ok-100 px-4 py-3 text-sm text-ok-600">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <x-auth.field
            name="email" label="이메일" type="email" required autofocus autocomplete="username"
            placeholder="예: hospital@sammes.co.kr"
            :icon="'<rect x=\'3\' y=\'5\' width=\'18\' height=\'14\' rx=\'2\'/><path d=\'m3 7 9 6 9-6\'/>'" />

        <div x-data="{ show: false }">
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="flex items-center gap-1 text-xs font-semibold text-ink-500">
                    비밀번호 <span class="h-1 w-1 rounded-full bg-brand-500"></span>
                </label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                    비밀번호 찾기
                </a>
            </div>
            <div class="relative group">
                <span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-ink-300 transition-colors group-focus-within:text-brand-500">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                    </svg>
                </span>
                <input :type="show ? 'text' : 'password'" id="password" name="password" required autocomplete="current-password"
                    placeholder="••••••••"
                    class="block w-full rounded-xl border-slate-200 bg-white py-3 pl-11 pr-11 text-sm text-ink-900 shadow-sm transition placeholder:text-ink-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-400/30 @error('password') border-crit-600 focus:border-crit-600 focus:ring-crit-600/20 @enderror" />
                <button type="button" @click="show = !show" tabindex="-1"
                    class="absolute inset-y-0 right-0 grid w-11 place-items-center text-ink-300 hover:text-ink-500" :aria-label="show ? '숨기기' : '표시'">
                    <svg x-show="!show" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg x-show="show" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                        <path d="m3 3 18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A9.5 9.5 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-2.2 3.1M6.1 6.1A17 17 0 0 0 2 12s3.5 7 10 7a9.5 9.5 0 0 0 3-.5"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 flex items-center gap-1 text-xs text-crit-600">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01" stroke-linecap="round"/></svg>
                    {{ $message }}
                </p>
            @enderror
            @error('email')
                <p class="mt-1.5 flex items-center gap-1 text-xs text-crit-600">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01" stroke-linecap="round"/></svg>
                    {{ $message }}
                </p>
            @enderror
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-500">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-400/40">
            로그인 상태 유지
        </label>

        <button type="submit" class="btn-primary w-full" data-magnetic>
            로그인
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
        </button>
    </form>

    @if(config('app.show_test_accounts'))
        @php
            // 테스트용 대표 계정(역할별). 모든 시드/데모 계정 비밀번호는 'password'.
            $testAccounts = [
                ['본사',   'hq@smartlogis.test',          '전체 · 승인/정산/마스터'],
                ['물류창고', 'wh1@smartlogis.test',         '입출고 · 배송 · 창고재고'],
                ['라이프',  'life1@smartlogis.test',       '라이프사이언스 · 요청/사용확정'],
                ['병원',   'seoul@smartlogis.test',       '서울대병원 · 재고/사용분'],
                ['공급사',  'sup-samsung@smartlogis.test', '삼성메디슨 · 자사재고/부족'],
            ];
        @endphp
        <div x-data="{
                open: true,
                fill(email) {
                    const e = document.getElementById('email');
                    const p = document.getElementById('password');
                    e.value = email; p.value = 'password';
                    e.dispatchEvent(new Event('input')); p.dispatchEvent(new Event('input'));
                    window.toast?.('테스트 계정이 입력되었습니다. 로그인 버튼을 누르세요.', 'info');
                }
             }"
             class="mt-8 rounded-2xl border border-amber-300/60 bg-amber-50/70 p-4">
            <button type="button" @click="open = !open"
                    class="flex w-full items-center justify-between text-left">
                <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    테스트 계정 (개발용)
                </span>
                <svg class="h-4 w-4 text-amber-600 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="open" x-transition class="mt-3 space-y-1.5">
                <p class="text-[11px] text-amber-700/80">클릭하면 자동 입력됩니다. 모든 계정 비밀번호는 <span class="font-mono font-semibold">password</span></p>
                @foreach($testAccounts as [$role, $email, $desc])
                    <button type="button" @click="fill('{{ $email }}')"
                            class="group flex w-full items-center gap-3 rounded-lg border border-amber-200 bg-white/80 px-3 py-2 text-left transition-colors hover:border-amber-400 hover:bg-white">
                        <span class="inline-flex w-14 shrink-0 justify-center rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-700">{{ $role }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-mono text-xs font-semibold text-ink-800">{{ $email }}</span>
                            <span class="block truncate text-[11px] text-ink-400">{{ $desc }}</span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-amber-400 opacity-0 transition-opacity group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-8 flex items-center gap-3 text-xs text-ink-300">
        <span class="h-px flex-1 bg-slate-200"></span>
        아직 거래처 계정이 없으신가요?
        <span class="h-px flex-1 bg-slate-200"></span>
    </div>
    <a href="{{ route('register') }}" class="btn-ghost mt-4 w-full">
        거래처 가입 신청
    </a>
</x-guest-layout>
