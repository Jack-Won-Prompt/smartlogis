@props([
    'qty',           // 현재고
    'safety' => 0,   // 안전재고
])

@php
    $qty = (int) $qty; $safety = (int) $safety;
    $ratio = $safety > 0 ? $qty / $safety : ($qty > 0 ? 1.5 : 0);
    $tone = $ratio < 1 ? 'crit' : ($ratio < 1.2 ? 'warn' : 'ok');
    $pct = max(0, min(100, $safety > 0 ? ($qty / max($safety * 1.5, 1)) * 100 : 100));
    $bar = ['ok' => 'bg-ok-600', 'warn' => 'bg-warn-600', 'crit' => 'bg-crit-600'][$tone];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center justify-end gap-3']) }}>
    <div class="text-right leading-tight">
        <span class="font-mono text-sm font-semibold text-ink-900 tabular-nums">{{ number_format($qty) }}</span>
        @if($safety > 0)
            <span class="font-mono text-xs text-ink-300"> / 안전 {{ number_format($safety) }}</span>
        @endif
    </div>
    <div class="h-2 w-16 overflow-hidden rounded-full bg-surface-2" title="안전재고 대비 {{ round($ratio * 100) }}%">
        <div class="h-full rounded-full {{ $bar }} transition-all" style="width: {{ $pct }}%"></div>
    </div>
</div>
