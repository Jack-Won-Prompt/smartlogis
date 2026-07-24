@props([
    'paginator',
    'label' => '건',   // 총 N건
])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    // 표시할 페이지 범위(현재 기준 앞뒤 2)
    $start = max(1, $current - 2);
    $end = min($last, $current + 2);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-between gap-3 sm:flex-row']) }}>
    <p class="font-mono text-xs text-ink-400">
        총 {{ number_format($paginator->total()) }}{{ $label }} · {{ $current }} / {{ max($last, 1) }} 페이지
    </p>

    @if ($last > 1)
        <nav class="flex items-center gap-1" role="navigation" aria-label="페이지네이션">
            {{-- 이전 --}}
            <button wire:click="previousPage" @disabled($paginator->onFirstPage())
                    class="grid h-8 w-8 place-items-center rounded-lg border border-line text-ink-500 transition hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="이전">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>

            {{-- 첫 페이지 + 말줄임 --}}
            @if ($start > 1)
                <button wire:click="gotoPage(1)" class="h-8 min-w-8 rounded-lg border border-line px-2 font-mono text-sm text-ink-600 transition hover:bg-surface-2">1</button>
                @if ($start > 2)<span class="px-1 text-ink-300">…</span>@endif
            @endif

            {{-- 페이지 범위 --}}
            @for ($p = $start; $p <= $end; $p++)
                @if ($p === $current)
                    <span aria-current="page" class="grid h-8 min-w-8 place-items-center rounded-lg bg-brand-600 px-2 font-mono text-sm font-semibold text-white">{{ $p }}</span>
                @else
                    <button wire:click="gotoPage({{ $p }})" class="h-8 min-w-8 rounded-lg border border-line px-2 font-mono text-sm text-ink-600 transition hover:bg-surface-2">{{ $p }}</button>
                @endif
            @endfor

            {{-- 말줄임 + 마지막 --}}
            @if ($end < $last)
                @if ($end < $last - 1)<span class="px-1 text-ink-300">…</span>@endif
                <button wire:click="gotoPage({{ $last }})" class="h-8 min-w-8 rounded-lg border border-line px-2 font-mono text-sm text-ink-600 transition hover:bg-surface-2">{{ $last }}</button>
            @endif

            {{-- 다음 --}}
            <button wire:click="nextPage" @disabled(! $paginator->hasMorePages())
                    class="grid h-8 w-8 place-items-center rounded-lg border border-line text-ink-500 transition hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
                    aria-label="다음">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </nav>
    @endif
</div>
