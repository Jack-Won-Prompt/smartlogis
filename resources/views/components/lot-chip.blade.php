@props([
    'label' => null,       // 'LOT' | 'EXP' | 'GTIN' 등
    'value',
    'expiry' => null,      // Carbon|string — 넘기면 유통기한 상태로 자동 변색
    'href' => null,        // 지정 시 Lot 추적 등으로 이동
])

@php
    use Illuminate\Support\Carbon;

    $tone = 'default';
    if ($expiry !== null) {
        $date = $expiry instanceof Carbon ? $expiry : Carbon::parse($expiry);
        $days = Carbon::today()->diffInDays($date, false);
        $tone = $days < 30 ? 'crit' : ($days < 90 ? 'warn' : 'ok');
    }

    $tones = [
        'default' => 'border-line bg-surface-2 text-ink-700',
        'ok'      => 'border-ok-600/20 bg-ok-100 text-ok-600',
        'warn'    => 'border-warn-600/20 bg-warn-100 text-warn-600',
        'crit'    => 'border-crit-600/20 bg-crit-100 text-crit-600',
    ];
    $tag = $href ? 'a' : 'span';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-mono text-xs '.$tones[$tone].($href ? ' transition hover:brightness-95' : '')]) }}>
    @if($label)
        <span class="text-ink-300">{{ $label }}</span>
    @endif
    <span class="font-medium tabular-nums">{{ $value }}</span>
</{{ $tag }}>
