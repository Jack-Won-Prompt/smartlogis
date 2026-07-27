@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <title>{{ $title ? $title.' · ' : '' }}SmartLogis</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-ink-700 antialiased">
    <div class="min-h-screen w-full lg:grid lg:grid-cols-[1.05fr_1fr] xl:grid-cols-[1.15fr_1fr]">

        {{-- ── 좌: 브랜드 패널 (의료 물류 관제탑 모티프) ─────────────────── --}}
        <aside class="relative hidden overflow-hidden bg-mesh lg:block">
            <div class="absolute inset-0 bg-grid-lines bg-grid opacity-[0.5]"></div>
            <div class="pointer-events-none absolute -left-24 top-16 h-72 w-72 rounded-full bg-brand-500/30 blur-3xl animate-float-slow"></div>
            <div class="pointer-events-none absolute -right-16 bottom-24 h-80 w-80 rounded-full bg-brand-400/20 blur-3xl animate-float"></div>

            <div class="relative z-10 flex h-full flex-col justify-between p-12 xl:p-16">
                {{-- 로고 (전체 워드마크를 흰 카드에 올려 어두운 패널에서도 또렷하게) --}}
                <a href="{{ url('/') }}" class="inline-flex self-start">
                    <span class="rounded-2xl bg-white px-6 py-4 shadow-lift">
                        <img src="{{ asset('images/smartlogis_300x100.png') }}" alt="삼에스메디컬 SmartLogis" class="w-auto object-contain" />
                    </span>
                </a>

                {{-- 카피 + 미니 Flow Rail --}}
                <div class="max-w-lg">
                    <p class="pill bg-white/10 text-brand-100 ring-1 ring-inset ring-white/15">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-300"></span>
                        <span class="font-bold text-white">삼에스메디컬</span> 의료 간납 물류 관제
                    </p>
                    <h1 class="mt-6 font-display text-4xl font-extrabold leading-tight tracking-tight text-white xl:text-5xl">
                        수술실에 닿는 제품,<br>
                        <span class="bg-gradient-to-r from-brand-200 to-white bg-clip-text text-transparent">한 화면에서 정밀하게.</span>
                    </h1>
                    <p class="mt-5 text-[15px] leading-relaxed text-brand-100/80">
                        <span class="font-semibold text-white">삼에스메디컬</span>이 공급사부터 물류창고, 거점병원 선납창고,
                        사용·정산까지 Lot 단위로 추적하고 안전재고를 자동 보충합니다.
                    </p>

                    <x-brand.flow-rail class="mt-10" />
                </div>

                {{-- 하단: 지표 + 모바일 앱 QR --}}
                {{--
                    플로팅 위젯 대신 인라인으로 둔다 — 좌하단 고정 위젯은 이 지표를 가린다.
                    QR 은 데스크톱에서만 의미가 있고(자기 화면의 QR 은 찍을 수 없다),
                    이 패널 자체가 lg 이상에서만 보이므로 조건이 맞아떨어진다.
                --}}
                <div class="flex items-end justify-between gap-8 border-t border-white/10 pt-8">
                <dl class="grid max-w-lg flex-1 grid-cols-3 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-brand-100/70">Lot 추적</dt>
                        <dd class="mt-1 font-mono text-2xl font-semibold text-white font-tnum">100%</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-brand-100/70">재고 정확도</dt>
                        <dd class="mt-1 font-mono text-2xl font-semibold text-white font-tnum">99.9%</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-brand-100/70">FEFO 출고</dt>
                        <dd class="mt-1 font-mono text-2xl font-semibold text-white font-tnum">자동</dd>
                    </div>
                </dl>

                    <a href="{{ url('/app/') }}" class="shrink-0 text-center">
                        <span class="block rounded-xl bg-white p-2">
                            <img src="{{ asset('app/install-qr.png') }}"
                                 alt="모바일 앱 설치 QR 코드"
                                 class="h-[92px] w-[92px]" width="92" height="92">
                        </span>
                        <span class="mt-2 block text-[11px] text-brand-100/70">모바일 앱 설치</span>
                    </a>
                </div>
            </div>
        </aside>

        {{-- ── 우: 폼 영역 ──────────────────────────────────────────── --}}
        <main class="relative flex min-h-screen items-center justify-center bg-slate-50 px-6 py-12 sm:px-10">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-radial-glow opacity-60 lg:hidden"></div>

            <div class="relative w-full max-w-md animate-fade-up">
                {{-- 모바일 로고 (밝은 배경 → 컬러 로고 직접) --}}
                <a href="{{ url('/') }}" class="mb-8 flex items-center justify-center lg:hidden">
                    <img src="{{ asset('images/smartlogis_300x100.png') }}" alt="삼에스메디컬 SmartLogis" class="h-8 w-auto object-contain" />
                </a>

                {{ $slot }}

                {{--
                    모바일: 좌측 브랜드 패널(QR 이 있는 곳)이 lg 미만에서 숨겨지므로
                    여기에 설치 링크를 둔다. 자기 화면의 QR 은 찍을 수 없으니 링크가 맞다.
                --}}
                <a href="{{ url('/app/') }}"
                   class="mt-8 flex items-center justify-center gap-2 rounded-md border border-slate-200
                          bg-white px-4 py-3 text-sm font-semibold text-brand-600 lg:hidden">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>
                    </svg>
                    모바일 앱 설치
                </a>

                <p class="mt-10 text-center text-xs text-ink-300">
                    © {{ date('Y') }} 삼에스메디컬 · SmartLogis. 인가된 사용자만 접근할 수 있습니다.
                </p>
            </div>
        </main>
    </div>
    @livewireScriptConfig
</body>
</html>
