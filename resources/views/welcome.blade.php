<!DOCTYPE html>
<html lang="ko" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SmartLogis — 의료 간납 물류 관제 시스템. 공급사부터 병원 선납창고, 사용·정산까지 Lot 단위로 정밀하게.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>SmartLogis · 의료 간납 물류 관제 시스템</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-ink-700 antialiased">

    {{-- ══ 헤더 ═══════════════════════════════════════════════════════ --}}
    <header data-site-header
            class="fixed inset-x-0 top-0 z-50 transition-all duration-300 ease-brand
                   [&.is-scrolled]:border-b [&.is-scrolled]:border-slate-200/70
                   [&.is-scrolled]:bg-white/80 [&.is-scrolled]:backdrop-blur-xl [&.is-scrolled]:shadow-soft">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                {{-- 상단(다크) → 다크 로고, 스크롤(화이트 헤더) → 컬러 로고 --}}
                <img src="{{ asset('images/smartlogis_300x100_dark_preview.png') }}" alt="삼에스메디컬 SmartLogis"
                     class="w-auto object-contain [.is-scrolled_&]:hidden">
                <img src="{{ asset('images/smartlogis_300x100.png') }}" alt="삼에스메디컬 SmartLogis"
                     class="hidden w-auto object-contain [.is-scrolled_&]:block">
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-ink-500 lg:flex">
                <a href="#flow" class="transition-colors hover:text-brand-700">물류 흐름</a>
                <a href="#roles" class="transition-colors hover:text-brand-700">역할별 기능</a>
                <a href="#features" class="transition-colors hover:text-brand-700">핵심 기능</a>
                <a href="#metrics" class="transition-colors hover:text-brand-700">성과</a>
            </nav>

            <div class="flex items-center gap-3">
                <a href="{{ route('login') }}" class="hidden text-sm font-semibold text-brand-700 transition-colors hover:text-brand-800 sm:inline">로그인</a>
                <a href="{{ route('register') }}" class="btn-primary !px-4 !py-2.5 text-sm" data-magnetic>가입 신청</a>
            </div>
        </div>
    </header>

    {{-- ══ 히어로 ═════════════════════════════════════════════════════ --}}
    <section class="relative overflow-hidden bg-mesh pt-32 pb-24 text-white lg:pt-40 lg:pb-32">
        <div class="absolute inset-0 bg-grid-lines bg-grid opacity-40"></div>
        <div data-parallax="0.15" class="pointer-events-none absolute -left-20 top-24 h-72 w-72 rounded-full bg-brand-500/30 blur-3xl"></div>
        <div data-parallax="0.25" class="pointer-events-none absolute -right-10 top-40 h-80 w-80 rounded-full bg-brand-400/20 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-6 lg:grid-cols-[1.05fr_1fr] lg:px-8">
            {{-- 좌: 카피 --}}
            <div>
                <span class="reveal pill bg-white/10 text-brand-100 ring-1 ring-inset ring-white/15">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-300 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-brand-300"></span>
                    </span>
                    <span class="font-bold text-white">삼에스메디컬</span> · 의료 간납 물류 관제
                </span>

                <h1 class="reveal mt-6 font-display text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl" style="--reveal-delay:80ms">
                    수술실에 닿는 제품,<br>
                    <span class="bg-gradient-to-r from-brand-200 via-white to-brand-200 bg-clip-text text-transparent">한눈에, 정밀하게.</span>
                </h1>

                <p class="reveal mt-6 max-w-xl text-lg leading-relaxed text-brand-100/85" style="--reveal-delay:160ms">
                    <span class="font-bold text-white">삼에스메디컬</span>이 공급사 → 물류창고 → 거점병원 선납창고 → 사용·정산의
                    모든 이동을 <span class="font-semibold text-white">Lot 단위</span>로 추적하고,
                    안전재고 미달을 <span class="font-semibold text-white">자동 보충</span>합니다.
                </p>

                <div class="reveal mt-9 flex flex-wrap items-center gap-4" style="--reveal-delay:240ms">
                    <a href="{{ route('register') }}" class="btn-primary" data-magnetic>
                        거래처 가입 신청
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a href="{{ route('login') }}" class="btn-dark">로그인</a>
                </div>

                <dl class="reveal mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-white/10 pt-8" style="--reveal-delay:320ms">
                    <div>
                        <dd class="font-display text-3xl font-extrabold text-white font-tnum"><span data-count="4" data-suffix="종">0</span></dd>
                        <dt class="mt-1 text-xs text-brand-100/70">사용자 역할</dt>
                    </div>
                    <div>
                        <dd class="font-display text-3xl font-extrabold text-white font-tnum"><span data-count="99.9" data-decimals="1" data-suffix="%">0</span></dd>
                        <dt class="mt-1 text-xs text-brand-100/70">재고 정확도</dt>
                    </div>
                    <div>
                        <dd class="font-display text-3xl font-extrabold text-white font-tnum"><span data-count="100" data-suffix="%">0</span></dd>
                        <dt class="mt-1 text-xs text-brand-100/70">Lot 추적성</dt>
                    </div>
                </dl>
            </div>

            {{-- 우: 관제 대시보드 목업 --}}
            <div class="reveal-scale relative" style="--reveal-delay:200ms">
                <div class="animate-float-slow">
                    <div class="glass p-5 shadow-lift" data-tilt="6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-crit-600/80"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-warn-600/80"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-ok-600/80"></span>
                            </div>
                            <span class="font-mono text-[11px] text-brand-100/60">CONTROL TOWER</span>
                        </div>

                        {{-- 미니 Flow Rail --}}
                        <div class="mt-5 rounded-xl bg-white/5 p-4 ring-1 ring-inset ring-white/10">
                            <x-brand.flow-rail current="HOSPITAL" variant="dark" />
                        </div>

                        {{-- 미니 KPI --}}
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-white/5 p-4 ring-1 ring-inset ring-white/10">
                                <p class="text-[11px] text-brand-100/60">오늘 출고</p>
                                <p class="mt-1 font-mono text-2xl font-semibold text-white font-tnum">1,248</p>
                                <p class="mt-1 text-[11px] text-ok-600">▲ 4.2% 전일</p>
                            </div>
                            <div class="rounded-xl bg-white/5 p-4 ring-1 ring-inset ring-white/10">
                                <p class="text-[11px] text-brand-100/60">안전재고 미달</p>
                                <p class="mt-1 font-mono text-2xl font-semibold text-white font-tnum">7</p>
                                <p class="mt-1 text-[11px] text-warn-600">보충 제안 대기</p>
                            </div>
                        </div>

                        {{-- 미니 바 차트 --}}
                        <div class="mt-4 rounded-xl bg-white/5 p-4 ring-1 ring-inset ring-white/10">
                            <p class="mb-3 text-[11px] text-brand-100/60">월별 사용분 매출</p>
                            <div class="flex h-20 items-end gap-2">
                                @foreach ([40,55,48,70,62,85,78,96] as $h)
                                    <span class="flex-1 rounded-t bg-gradient-to-t from-brand-500/40 to-brand-300" style="height: {{ $h }}%"></span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 떠 있는 Lot 칩 --}}
                <div class="absolute -left-6 top-16 hidden animate-float rounded-xl bg-white px-3 py-2 shadow-lift md:block" style="animation-delay:-2s">
                    <p class="font-mono text-xs font-semibold text-brand-700">LOT A23K01</p>
                    <p class="font-mono text-[10px] text-warn-600">EXP 2026-08 · D-25</p>
                </div>
                <div class="absolute -right-4 bottom-24 hidden animate-float rounded-xl bg-white px-3 py-2 shadow-lift md:block" style="animation-delay:-4s">
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-ok-600">
                        <span class="h-1.5 w-1.5 rounded-full bg-ok-600"></span> 사용분 승인
                    </p>
                    <p class="font-mono text-[10px] text-ink-400">세브란스 · 12건</p>
                </div>
            </div>
        </div>

        {{-- 신뢰 마퀴 --}}
        <div class="relative mt-20 border-t border-white/10 pt-8">
            <p class="mb-5 text-center text-xs font-medium uppercase tracking-widest text-brand-100/50">전국 거점병원 · 공급사와 함께합니다</p>
            <div class="mx-auto max-w-5xl overflow-hidden [mask-image:linear-gradient(90deg,transparent,#000_12%,#000_88%,transparent)]">
                <div class="flex w-max animate-marquee gap-12">
                    @foreach (['서울대학교병원','세브란스병원','서울아산병원','삼성서울병원','부산대학교병원','삼성메디슨','메드트로닉','존슨앤드존슨','스트라이커'] as $name)
                        <span class="whitespace-nowrap font-display text-lg font-bold text-white/40">{{ $name }}</span>
                    @endforeach
                    @foreach (['서울대학교병원','세브란스병원','서울아산병원','삼성서울병원','부산대학교병원','삼성메디슨','메드트로닉','존슨앤드존슨','스트라이커'] as $name)
                        <span class="whitespace-nowrap font-display text-lg font-bold text-white/40" aria-hidden="true">{{ $name }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ══ 물류 흐름 ═══════════════════════════════════════════════════ --}}
    <section id="flow" class="relative py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="reveal pill bg-brand-50 text-brand-700 ring-1 ring-brand-100">위탁판매 · 간납 흐름</span>
                <h2 class="reveal mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl" style="--reveal-delay:80ms">
                    네 개의 거점, <span class="text-gradient">하나의 흐름</span>
                </h2>
                <p class="reveal mt-4 text-lg text-ink-500" style="--reveal-delay:160ms">
                    제품은 공급사에서 시작해 병원 사용·정산으로 끝납니다. 각 단계가 서버에서 검증됩니다.
                </p>
            </div>

            {{-- 대형 Flow Rail --}}
            <div class="reveal mx-auto mt-14 max-w-4xl rounded-3xl border border-slate-200 bg-white p-8 shadow-soft sm:p-12" style="--reveal-delay:120ms">
                <x-brand.flow-rail variant="light" class="[&_svg]:h-6 [&_svg]:w-6" />
            </div>

            {{-- 흐름 단계 카드 --}}
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['01','선납 입고','공급사 제품을 거점병원 선납창고에 미리 입고합니다.','m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z'],
                    ['02','사용분 전송','병원이 사용한 품목·Lot·수량·금액을 본사로 전송합니다.','M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z'],
                    ['03','승인·차감','본사 승인 시 재고가 차감되고 정산 대상이 됩니다.','M20 6 9 17l-5-5'],
                    ['04','자동 보충','안전재고 미달 시 출고를 자동 제안·생성합니다.','M21 12a9 9 0 1 1-3-6.7L21 8'],
                ] as $i => [$no,$title,$desc,$icon])
                    <div class="reveal card p-6" style="--reveal-delay:{{ $i * 90 }}ms" data-tilt="5">
                        <div class="flex items-center justify-between">
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                            </span>
                            <span class="font-mono text-sm font-semibold text-ink-300">{{ $no }}</span>
                        </div>
                        <h3 class="mt-4 font-display text-lg font-bold text-ink-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-500">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══ 역할별 기능 ═════════════════════════════════════════════════ --}}
    <section id="roles" class="relative bg-white py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="reveal pill bg-brand-50 text-brand-700 ring-1 ring-brand-100">4가지 역할</span>
                <h2 class="reveal mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl" style="--reveal-delay:80ms">
                    각자의 화면, <span class="text-gradient">각자의 권한</span>
                </h2>
                <p class="reveal mt-4 text-lg text-ink-500" style="--reveal-delay:160ms">
                    역할과 소속 조직에 따라 서버에서 데이터가 자동 필터링됩니다.
                </p>
            </div>

            <div class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['본사','HQ','전체 데이터·사용분 승인·정산·마스터 관리','M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01'],
                    ['물류창고','WAREHOUSE','입출고·배송·FEFO 피킹·창고 재고','m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3ZM12 12 4 7.5M12 12l8-4.5M12 12v9'],
                    ['거점병원','HOSPITAL','자기 병원 재고·사용분 등록·입고 확인','M9 3h6v6h6v6h-6v6H9v-6H3V9h6V3Z'],
                    ['공급사','SUPPLIER','자사 제품 병원별 재고·부족·납품','M3 21V9l5 3V9l5 3V9l5 3v9H3ZM7 21v-3M12 21v-3M17 21v-3'],
                ] as $i => [$name,$code,$desc,$icon])
                    <div class="reveal group relative overflow-hidden card p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lift"
                         style="--reveal-delay:{{ $i * 90 }}ms">
                        <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-brand-50 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                        <span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-brand-gradient text-white shadow-glow">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        </span>
                        <h3 class="relative mt-5 font-display text-xl font-bold text-ink-900">{{ $name }}</h3>
                        <p class="relative mt-1 font-mono text-xs font-medium text-brand-500">{{ $code }}</p>
                        <p class="relative mt-3 text-sm leading-relaxed text-ink-500">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══ 핵심 기능 ═══════════════════════════════════════════════════ --}}
    <section id="features" class="relative py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="reveal pill bg-brand-50 text-brand-700 ring-1 ring-brand-100">핵심 기능</span>
                <h2 class="reveal mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl" style="--reveal-delay:80ms">
                    실수할 수 없게 만드는 <span class="text-gradient">정밀함</span>
                </h2>
            </div>

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Lot 추적성','모든 입출고·사용을 Lot 단위로 기록해 리콜에 즉시 대응합니다.','M12 2 2 7v10l10 5 10-5V7L12 2ZM2 7l10 5 10-5M12 12v10'],
                    ['FEFO 자동 출고','유통기한 임박 순으로 재고를 자동 배정하고 경과분은 제외합니다.','M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Z'],
                    ['안전재고 자동 보충','병원별·품목별 기준 미달 시 출고를 자동 제안·생성합니다.','M21 12a9 9 0 1 1-3-6.7M21 4v4h-4'],
                    ['GS1 바코드 스캔','UDI 바코드를 파싱해 제품·Lot·유통기한을 즉시 세팅합니다.','M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14'],
                    ['월 정산·마감','사용분 승인 시 매출/매입 정산이 생성되고 마감을 강제합니다.','M4 4h16v4H4zM4 12h16M4 12v8h16v-8M8 16h4'],
                    ['역할별 대시보드','승인 대기·안전재고·유통기한 임박을 한 화면에서 관제합니다.','M4 20V10M10 20V4M16 20v-7M2 20h20'],
                ] as $i => [$title,$desc,$icon])
                    <div class="reveal group card p-6 transition-all duration-300 hover:border-brand-200 hover:shadow-lift"
                         style="--reveal-delay:{{ ($i % 3) * 90 }}ms">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600 transition-colors group-hover:bg-brand-600 group-hover:text-white">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        </span>
                        <h3 class="mt-4 font-display text-lg font-bold text-ink-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-500">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══ 성과 지표 ═══════════════════════════════════════════════════ --}}
    <section id="metrics" class="relative overflow-hidden bg-mesh py-24 text-white lg:py-28">
        <div class="absolute inset-0 bg-grid-lines bg-grid opacity-30"></div>
        <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['30','종','관리 제품 마스터','+',''],
                    ['1248','건','일 평균 출고 처리','',''],
                    ['99.9','%','재고 캐시 정확도','','1'],
                    ['24','시간','유통기한 경고 배치','',''],
                ] as $m)
                    <div class="reveal text-center" style="--reveal-delay:{{ $loop->index * 90 }}ms">
                        <p class="font-display text-5xl font-extrabold text-white font-tnum">
                            <span data-count="{{ $m[0] }}" @if($m[4] !== '') data-decimals="{{ $m[4] }}" @endif data-suffix="{{ $m[3] }}">0</span><span class="text-brand-300">{{ $m[1] }}</span>
                        </p>
                        <p class="mt-2 text-sm text-brand-100/70">{{ $m[2] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══ CTA ════════════════════════════════════════════════════════ --}}
    <section class="relative py-24 lg:py-32">
        <div class="mx-auto max-w-5xl px-6 lg:px-8">
            <div class="reveal-scale relative overflow-hidden rounded-3xl bg-brand-gradient p-12 text-center shadow-lift sm:p-16">
                <div class="absolute inset-0 bg-grid-lines bg-grid opacity-20"></div>
                <div class="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
                <div class="pointer-events-none absolute -bottom-10 -right-10 h-48 w-48 rounded-full bg-brand-300/20 blur-3xl"></div>
                <div class="relative">
                    <h2 class="font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                        지금 <span class="text-brand-200">삼에스메디컬</span>과 시작하세요
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg text-brand-100/85">
                        가입 신청 후 삼에스메디컬 본사 승인이 완료되면 바로 관제 화면을 사용할 수 있습니다.
                    </p>
                    <div class="mt-9 flex flex-wrap items-center justify-center gap-4">
                        <a href="{{ route('register') }}" class="btn inline-flex bg-white text-brand-700 shadow-lift hover:-translate-y-0.5 hover:bg-brand-50" data-magnetic>
                            거래처 가입 신청
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                        <a href="{{ route('login') }}" class="btn-dark">이미 계정이 있어요</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══ 푸터 ════════════════════════════════════════════════════════ --}}
    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-6 sm:flex-row lg:px-8">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/smartlogis_300x100.png') }}" alt="삼에스메디컬 SmartLogis" class="w-auto object-contain">
            </div>
            <p class="text-center text-sm text-ink-400 sm:text-right">
                © {{ date('Y') }} 삼에스메디컬 · SmartLogis. 의료 간납 물류 관제 시스템.<br class="sm:hidden">
                <span class="hidden sm:inline"> · </span>인가된 사용자만 접근할 수 있습니다.
            </p>
        </div>
    </footer>

    @livewireScriptConfig
</body>
</html>
