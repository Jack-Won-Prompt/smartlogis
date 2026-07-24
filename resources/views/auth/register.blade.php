<x-guest-layout title="거래처 가입 신청">
    <div class="reveal is-visible">
        <a href="{{ route('login') }}" class="mb-6 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-400 hover:text-brand-600">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            로그인으로
        </a>
        <h2 class="font-display text-2xl font-extrabold tracking-tight text-ink-900">거래처 가입 신청</h2>
        <p class="mt-2 text-sm text-ink-500">신청 후 본사 승인이 완료되면 로그인할 수 있습니다.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5"
          x-data="{ role: '{{ old('role', 'HOSPITAL') }}' }">
        @csrf

        {{-- 거래처 유형 세그먼트 --}}
        <div>
            <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-500">
                거래처 유형 <span class="h-1 w-1 rounded-full bg-brand-500"></span>
            </span>
            <div class="grid grid-cols-3 gap-2">
                @foreach ([['HOSPITAL','거점병원'],['SUPPLIER','공급사'],['WAREHOUSE','물류창고']] as [$val, $lbl])
                    <label class="cursor-pointer">
                        <input type="radio" name="role" value="{{ $val }}" x-model="role" class="peer sr-only">
                        <span class="flex flex-col items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2 py-3 text-center text-xs font-semibold text-ink-500 transition-all
                                     hover:border-brand-200 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:shadow-glow">
                            {{ $lbl }}
                        </span>
                    </label>
                @endforeach
            </div>
            @error('role')<p class="mt-1.5 text-xs text-crit-600">{{ $message }}</p>@enderror
        </div>

        <x-auth.field name="org_name" label="거래처(기관)명" required autofocus placeholder="예: 서울대학교병원"
            :icon="'<path d=\'M3 21h18M5 21V7l8-4v18M13 9h6v12M9 9v.01M9 13v.01M9 17v.01\'/>'" />

        <div class="grid grid-cols-2 gap-4">
            <x-auth.field name="biz_reg_no" label="사업자등록번호" placeholder="000-00-00000" hint="선택" />
            <x-auth.field name="tel" label="연락처" placeholder="02-000-0000" autocomplete="tel" />
        </div>

        <div class="my-2 flex items-center gap-3">
            <span class="h-px flex-1 bg-slate-100"></span>
            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-300">담당자 계정</span>
            <span class="h-px flex-1 bg-slate-100"></span>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <x-auth.field name="name" label="담당자명" required placeholder="홍길동" autocomplete="name" />
            <x-auth.field name="login_id" label="아이디" required placeholder="영문/숫자" autocomplete="username" />
        </div>

        <x-auth.field name="email" label="이메일" type="email" required placeholder="you@company.com" autocomplete="email"
            hint="승인 결과와 초대 링크가 이 주소로 발송됩니다."
            :icon="'<rect x=\'3\' y=\'5\' width=\'18\' height=\'14\' rx=\'2\'/><path d=\'m3 7 9 6 9-6\'/>'" />

        <div class="grid grid-cols-2 gap-4">
            <x-auth.field name="password" label="비밀번호" type="password" required placeholder="8자 이상" autocomplete="new-password" />
            <x-auth.field name="password_confirmation" label="비밀번호 확인" type="password" required placeholder="다시 입력" autocomplete="new-password" />
        </div>

        <label class="flex cursor-pointer items-start gap-2 text-xs text-ink-500">
            <input type="checkbox" name="agree" value="1" required class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-400/40">
            <span>개인정보 수집·이용 및 거래처 정보 확인에 동의합니다. (필수)</span>
        </label>
        @error('agree')<p class="text-xs text-crit-600">{{ $message }}</p>@enderror

        <button type="submit" class="btn-primary w-full" data-magnetic>
            가입 신청하기
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>
</x-guest-layout>
