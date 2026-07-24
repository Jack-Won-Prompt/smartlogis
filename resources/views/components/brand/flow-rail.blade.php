@props([
    'current' => null,   // 'SUPPLIER' | 'WAREHOUSE' | 'HOSPITAL' | 'USAGE' — 점등할 노드
    'variant' => 'dark', // dark(브랜드 패널) | light(밝은 배경)
])

@php
    $nodes = [
        ['key' => 'SUPPLIER',  'label' => '공급사',  'icon' => 'factory'],
        ['key' => 'WAREHOUSE', 'label' => '물류창고', 'icon' => 'box'],
        ['key' => 'HOSPITAL',  'label' => '거점병원', 'icon' => 'cross'],
        ['key' => 'USAGE',     'label' => '사용·정산','icon' => 'chart'],
    ];

    $icons = [
        'factory' => '<path d="M3 21V9l5 3V9l5 3V9l5 3v9H3Z"/><path d="M7 21v-3M12 21v-3M17 21v-3"/>',
        'box'     => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="M12 12 4 7.5M12 12l8-4.5M12 12v9"/>',
        'cross'   => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6V3Z"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ];

    $activeIndex = collect($nodes)->search(fn ($n) => $n['key'] === $current);
    $activeIndex = $activeIndex === false ? -1 : $activeIndex;

    $isDark = $variant === 'dark';
@endphp

<div {{ $attributes->merge(['class' => 'flow-rail']) }} aria-label="물류 흐름: 공급사에서 사용·정산까지">
    <ol class="flex items-start justify-between">
        @foreach ($nodes as $i => $node)
            @php
                $isActive = $i === $activeIndex;
                $isPast = $activeIndex >= 0 && $i < $activeIndex;
                $lit = $current === null ? true : ($isActive || $isPast);
            @endphp
            <li class="relative flex flex-1 flex-col items-center gap-2.5 text-center">
                {{-- 연결선 --}}
                @unless ($loop->first)
                    <span class="absolute right-1/2 top-5 -z-0 h-px w-full
                        {{ $isDark ? 'bg-white/15' : 'bg-slate-200' }}">
                        <span class="block h-px w-0 {{ $isDark ? 'bg-brand-300' : 'bg-brand-500' }} transition-all duration-700 ease-brand
                            {{ ($current === null || $i <= $activeIndex) ? 'w-full' : 'w-0' }}"></span>
                    </span>
                @endunless

                {{-- 노드 --}}
                <span class="relative z-10 grid h-10 w-10 place-items-center rounded-2xl border transition-all duration-500
                    @if ($lit)
                        {{ $isDark ? 'border-white/30 bg-white/15 text-white' : 'border-brand-200 bg-brand-50 text-brand-600' }}
                    @else
                        {{ $isDark ? 'border-white/10 bg-white/5 text-white/40' : 'border-slate-200 bg-white text-ink-300' }}
                    @endif">
                    @if ($isActive)
                        <span class="absolute inset-0 rounded-2xl {{ $isDark ? 'bg-white/40' : 'bg-brand-400/40' }} animate-pulse-ring"></span>
                    @endif
                    <svg class="relative h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        {!! $icons[$node['icon']] !!}
                    </svg>
                </span>

                <span class="text-xs font-medium {{ $isDark ? ($lit ? 'text-brand-100' : 'text-white/40') : ($lit ? 'text-ink-700' : 'text-ink-300') }}">
                    {{ $node['label'] }}
                </span>
            </li>
        @endforeach
    </ol>
</div>
