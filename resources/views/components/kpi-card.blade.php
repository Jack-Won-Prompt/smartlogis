@props([
    'label',
    'value',              // 표시 문자열(포맷 완료)
    'delta' => null,      // 예: 4.2 (전월 대비 %)
    'deltaUp' => true,    // 상승이 긍정인지
    'tone' => null,       // 'ok'|'warn'|'crit'|'info' — 경고성 KPI면 좌측 컬러바
    'href' => null,       // 클릭 시 필터된 리스트로 이동
    'spark' => [],        // 미니 스파크라인 값 배열(선택)
])

@php
    $bar = [
        'ok' => 'before:bg-ok-600', 'warn' => 'before:bg-warn-600',
        'crit' => 'before:bg-crit-600', 'info' => 'before:bg-info-600',
    ][$tone] ?? '';
    $deltaPositive = $delta !== null && (($delta >= 0) === $deltaUp);
    $tag = $href ? 'a' : 'div';

    // 스파크라인 폴리라인 좌표
    $points = '';
    if (!empty($spark)) {
        $max = max($spark) ?: 1; $min = min($spark);
        $range = ($max - $min) ?: 1; $n = count($spark);
        foreach (array_values($spark) as $i => $v) {
            $x = $n > 1 ? ($i / ($n - 1)) * 100 : 0;
            $y = 24 - (($v - $min) / $range) * 22 - 1;
            $points .= round($x, 1).','.round($y, 1).' ';
        }
    }
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl border border-line bg-surface-1 p-5 transition
        '.($bar ? 'pl-6 before:absolute before:inset-y-0 before:left-0 before:w-1 '.$bar : '')
        .($href ? ' hover:border-brand-200 hover:shadow-soft' : '')]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium text-ink-500">{{ $label }}</p>
        @if($points)
            <svg viewBox="0 0 100 24" preserveAspectRatio="none" class="h-6 w-20 shrink-0">
                <polyline points="{{ trim($points) }}" fill="none" stroke="#2D6AE0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endif
    </div>
    <p class="mt-3 font-mono text-3xl font-bold text-ink-900 tabular-nums">{{ $value }}</p>
    @if($delta !== null)
        <p class="mt-1 flex items-center gap-1 text-xs font-medium {{ $deltaPositive ? 'text-ok-600' : 'text-crit-600' }}">
            <span>{{ $delta >= 0 ? '▲' : '▼' }}</span>
            {{ number_format(abs($delta), 1) }}% <span class="text-ink-300">전월</span>
        </p>
    @endif
</{{ $tag }}>
