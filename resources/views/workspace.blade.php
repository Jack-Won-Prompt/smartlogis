@php
    /** @var \App\Models\User $me */
    $me = auth()->user();
    $role = $me->role;
    $menu = config('menu.groups');
    $icons = config('menu.icons');
    $visibleGroups = collect($menu)->map(function ($group) use ($role) {
        [$label, $icon, $items] = $group;
        $items = collect($items)->filter(fn ($it) => empty($it[2]) || in_array($role, $it[2], true))->values()->all();
        return [$label, $icon, $items];
    })->filter(fn ($g) => count($g[2]) > 0)->values();
    $dashUrl = route('dashboard').'?frame=1';
@endphp
<!DOCTYPE html>
<html lang="ko" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>SmartLogis 관제</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-screen overflow-hidden bg-surface-0 font-sans text-ink-700 antialiased"
      x-data="workspace({ home: { url: @js($dashUrl), title: '대시보드' } })" x-init="init()">
<div class="flex h-screen">

    {{-- ── 사이드바 ─────────────────────────────────────── --}}
    <aside class="flex w-[232px] shrink-0 flex-col bg-navy" :class="navOpen ? 'fixed inset-y-0 z-50' : 'hidden lg:flex'">
        <div class="flex h-14 items-center border-b border-white/5 px-5">
            <img src="{{ asset('images/smartlogis_300x100_dark_preview.png') }}" alt="SmartLogis" class="w-auto object-contain">
        </div>
        <nav class="flex flex-1 flex-col gap-5 overflow-y-auto px-3 py-5">
            @foreach($visibleGroups as $gi => [$groupLabel, $groupIcon, $items])
                <div x-data="{ open: {{ $gi === 0 ? 'true' : 'false' }} }">
                    {{-- 대메뉴(그룹) 헤더 — 클릭하면 하위 메뉴 펼침/접힘 --}}
                    <button type="button" @click="open = !open"
                            class="mb-1 flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-white/40 transition-colors hover:bg-white/5 hover:text-white/75">
                        <span class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$groupIcon] }}"/></svg>
                            {{ $groupLabel }}
                        </span>
                        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top class="space-y-0.5">
                    @foreach($items as [$itemLabel, $routeName, $roles])
                        @if(\Illuminate\Support\Facades\Route::has($routeName))
                            <button type="button" @click="openTab(@js(route($routeName).'?frame=1'), @js($itemLabel)); navOpen=false"
                                    class="group relative flex w-full items-center rounded-lg px-3 py-2 text-left text-sm text-white/70 transition-colors hover:bg-white/5 hover:text-white"
                                    :class="isActive(@js(route($routeName).'?frame=1')) && 'bg-brand-500/15 !text-white font-semibold'">
                                <span class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-brand-500" x-show="isActive(@js(route($routeName).'?frame=1'))"></span>
                                {{ $itemLabel }}
                            </button>
                        @else
                            <span class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-white/30">{{ $itemLabel }}<span class="rounded-full bg-white/5 px-1.5 py-0.5 text-[10px]">준비중</span></span>
                        @endif
                    @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
    </aside>
    <div x-show="navOpen" x-cloak @click="navOpen=false" class="fixed inset-0 z-40 bg-navy/50 backdrop-blur-sm lg:hidden"></div>

    {{-- ── 메인(탭 영역) ─────────────────────────────────── --}}
    <div class="flex min-w-0 flex-1 flex-col">
        {{-- 상단바 --}}
        <header class="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-line bg-surface-1 px-4">
            <div class="flex items-center gap-3">
                <button @click="navOpen=true" class="grid h-9 w-9 place-items-center rounded-lg text-ink-500 hover:bg-surface-2 lg:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="text-sm font-semibold text-ink-900">관제 워크스페이스</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden items-center gap-1.5 rounded-full bg-surface-2 px-3 py-1.5 text-xs font-medium text-ink-700 sm:inline-flex">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>{{ $me->organization->name }}
                </span>
                <div x-data="{ open:false }" class="relative">
                    <button @click="open=!open" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-surface-2">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-gradient text-xs font-bold text-white">{{ mb_substr($me->name, 0, 1) }}</span>
                        <svg class="h-4 w-4 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open=false" x-transition class="absolute right-0 z-50 mt-2 w-52 rounded-xl border border-line bg-surface-1 p-1.5 shadow-lift">
                        <div class="border-b border-line px-3 py-2">
                            <p class="text-sm font-semibold text-ink-900">{{ $me->name }}</p>
                            <p class="font-mono text-xs text-ink-400">{{ $me->login_id }} · {{ $role->label() }}</p>
                        </div>
                        <button @click="openTab(@js(route('profile.edit').'?frame=1'), '프로필')" class="mt-1 block w-full rounded-lg px-3 py-2 text-left text-sm text-ink-700 hover:bg-surface-2">프로필</button>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm text-crit-600 hover:bg-crit-100">로그아웃</button></form>
                    </div>
                </div>
            </div>
        </header>

        {{-- 탭 스트립 --}}
        <div class="flex h-11 shrink-0 items-stretch gap-1 overflow-x-auto border-b border-line bg-surface-1 px-2">
            <template x-for="t in tabs" :key="t.id">
                <div @click="active=t.id"
                     class="group flex cursor-pointer items-center gap-2 self-center rounded-lg px-3 py-1.5 text-sm transition-colors"
                     :class="active===t.id ? 'bg-brand-50 font-semibold text-brand-700' : 'text-ink-500 hover:bg-surface-2'">
                    <span x-text="t.title" class="whitespace-nowrap"></span>
                    <button @click.stop="close(t.id)" x-show="!t.home"
                            class="grid h-4 w-4 place-items-center rounded text-ink-400 hover:bg-crit-100 hover:text-crit-600">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- iframe 컨테이너(탭별 iframe 유지 → 상태 보존) --}}
        <div class="relative min-h-0 flex-1 bg-white">
            <template x-for="t in tabs" :key="t.id">
                <iframe :src="t.url" x-show="active===t.id" loading="lazy"
                        class="absolute inset-0 h-full w-full border-0"></iframe>
            </template>
        </div>
    </div>
</div>

<script>
    window.workspace = function (cfg) {
        return {
            tabs: [], active: null, navOpen: false, _seq: 0,
            init() { this.openTab(cfg.home.url, cfg.home.title, true); },
            openTab(url, title, isHome = false) {
                const found = this.tabs.find(t => t.url === url);
                if (found) { this.active = found.id; return; }
                const id = 'tab' + (++this._seq);
                this.tabs.push({ id, url, title, home: isHome });
                this.active = id;
            },
            isActive(url) { const t = this.tabs.find(x => x.id === this.active); return t && t.url === url; },
            close(id) {
                const i = this.tabs.findIndex(t => t.id === id);
                if (i < 0 || this.tabs[i].home) return;
                const wasActive = this.active === id;
                this.tabs.splice(i, 1);
                if (wasActive) this.active = (this.tabs[Math.max(0, i - 1)] || this.tabs[0]).id;
            },
        };
    };
</script>
@livewireScriptConfig
</body>
</html>
