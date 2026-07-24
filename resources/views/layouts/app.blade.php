@php
    use App\Enums\OrgType;
    /** @var \App\Models\User $me */
    $me = auth()->user();
    $role = $me->role;

    // MDI 워크스페이스 iframe 안에서는 사이드바/상단바 없이 콘텐츠만 렌더한다.
    $frame = request()->boolean('frame');

    // 메뉴 정의는 config/menu.php 로 공유(워크스페이스 셸과 동일 소스).
    $menu = config('menu.groups');
    $icons = config('menu.icons');

    $visibleGroups = collect($menu)->map(function ($group) use ($role) {
        [$label, $icon, $items] = $group;
        $items = collect($items)
            ->filter(fn ($it) => empty($it[2]) || in_array($role, $it[2], true))
            ->values()->all();
        return [$label, $icon, $items];
    })->filter(fn ($g) => count($g[2]) > 0)->values();
@endphp
<!DOCTYPE html>
<html lang="ko" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? '관제' }} · SmartLogis</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="{{ $frame ? 'bg-white' : 'bg-surface-0' }} font-sans text-ink-700 antialiased" x-data="{ mobileNav: false }">

    @unless($frame)
    {{-- ── 사이드바 (232px, 고정 네이비) ─────────────────────────── --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-[232px] -translate-x-full bg-navy transition-transform duration-300 ease-brand lg:translate-x-0"
           :class="mobileNav && '!translate-x-0'">
        <div class="flex h-14 items-center border-b border-white/5 px-5">
            {{-- 사이드바(솔리드 네이비) → 다크 전용 로고 직접 --}}
            <img src="{{ asset('images/smartlogis_300x100_dark_preview.png') }}" alt="삼에스메디컬 SmartLogis" class="h-8 w-auto object-contain">
        </div>

        <nav class="flex h-[calc(100vh-3.5rem)] flex-col gap-6 overflow-y-auto px-3 py-5">
            @foreach($visibleGroups as [$groupLabel, $groupIcon, $items])
                <div>
                    <p class="mb-1.5 flex items-center gap-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/35">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$groupIcon] }}"/></svg>
                        {{ $groupLabel }}
                    </p>
                    @foreach($items as [$itemLabel, $routeName, $roles])
                        @php
                            $exists = \Illuminate\Support\Facades\Route::has($routeName);
                            $active = $exists && request()->routeIs($routeName.'*');
                        @endphp
                        @if($exists)
                            <a href="{{ route($routeName) }}"
                               class="relative flex items-center rounded-lg px-3 py-2 text-sm transition-colors
                                      {{ $active ? 'bg-brand-500/15 font-semibold text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                                @if($active)<span class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-brand-500"></span>@endif
                                {{ $itemLabel }}
                            </a>
                        @else
                            <span class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-white/30" title="준비 중">
                                {{ $itemLabel }}
                                <span class="rounded-full bg-white/5 px-1.5 py-0.5 text-[10px]">준비중</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- 모바일 오버레이 --}}
    <div x-show="mobileNav" x-cloak @click="mobileNav = false" class="fixed inset-0 z-30 bg-navy/50 backdrop-blur-sm lg:hidden"></div>
    @endunless

    {{-- ── 메인 영역 ─────────────────────────────────────────────── --}}
    <div class="{{ $frame ? '' : 'lg:pl-[232px]' }}">
        @unless($frame)
        {{-- 상단바 56px --}}
        <header class="sticky top-0 z-20 flex h-14 items-center justify-between gap-4 border-b border-line bg-surface-1/80 px-4 backdrop-blur-xl sm:px-6">
            <div class="flex items-center gap-3">
                <button @click="mobileNav = true" class="grid h-9 w-9 place-items-center rounded-lg text-ink-500 hover:bg-surface-2 lg:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="hidden text-sm text-ink-400 sm:block">
                    {{ $breadcrumb ?? '대시보드' }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- 전역 스캔 입력 --}}
                <div class="relative hidden md:block">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14" stroke-linecap="round"/></svg>
                    <input type="text" placeholder="바코드 스캔 / 검색" class="h-9 w-56 rounded-lg border-line bg-surface-0 pl-9 pr-3 text-sm text-ink-900 placeholder:text-ink-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                </div>

                {{-- 알림 벨 --}}
                <a href="{{ \Illuminate\Support\Facades\Route::has('notifications.index') ? route('notifications.index') : '#' }}"
                   class="relative grid h-9 w-9 place-items-center rounded-lg text-ink-500 hover:bg-surface-2">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.5 21a1.7 1.7 0 0 1-3 0"/></svg>
                    <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-brand-500 ring-2 ring-surface-1"></span>
                </a>

                {{-- 조직 배지 --}}
                <span class="hidden items-center gap-1.5 rounded-full bg-surface-2 px-3 py-1.5 text-xs font-medium text-ink-700 sm:inline-flex">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                    {{ $me->organization->name }}
                </span>

                {{-- 프로필 --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-surface-2">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-gradient text-xs font-bold text-white">
                            {{ mb_substr($me->name, 0, 1) }}
                        </span>
                        <svg class="h-4 w-4 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition
                         class="absolute right-0 mt-2 w-52 rounded-xl border border-line bg-surface-1 p-1.5 shadow-lift">
                        <div class="border-b border-line px-3 py-2">
                            <p class="text-sm font-semibold text-ink-900">{{ $me->name }}</p>
                            <p class="font-mono text-xs text-ink-400">{{ $me->login_id }} · {{ $role->label() }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="mt-1 block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-surface-2">프로필</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full rounded-lg px-3 py-2 text-left text-sm text-crit-600 hover:bg-crit-100">로그아웃</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        @endunless

        {{-- 콘텐츠 --}}
        <main class="{{ $frame ? 'p-5' : 'p-6' }}">
            {{ $slot }}
        </main>
    </div>

    {{-- 커스텀 토스트/확인 다이얼로그 (네이티브 alert/confirm 미사용) --}}
    <x-toast-host />
    <x-confirm-dialog />

    @stack('scripts')
    @livewireScriptConfig
</body>
</html>
