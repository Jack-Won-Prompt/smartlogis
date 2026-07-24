@props([
    'status' => null,  // HasTone&HasLabel Enum (권장)
    'tone' => null,    // 또는 App\Enums\Tone 직접 지정
    'label' => null,
])

@php
    use App\Enums\Tone;

    $resolvedTone = $tone
        ?? ($status instanceof \App\Enums\Concerns\HasTone ? $status->tone() : Tone::HOLD);
    // 문자열('ok' 등)로 넘어온 경우 Tone 으로 정규화
    if (is_string($resolvedTone)) {
        $resolvedTone = Tone::tryFrom($resolvedTone) ?? Tone::HOLD;
    }
    $resolvedLabel = $label
        ?? ($status instanceof \App\Enums\Concerns\HasLabel ? $status->label() : (string) $status);

    // 정적 클래스(Tailwind purge 대응)
    $map = [
        'ok'   => 'bg-ok-100 text-ok-600',
        'warn' => 'bg-warn-100 text-warn-600',
        'crit' => 'bg-crit-100 text-crit-600',
        'info' => 'bg-info-100 text-info-600',
        'hold' => 'bg-hold-100 text-hold-600',
    ];
    $dot = [
        'ok'   => 'bg-ok-600',
        'warn' => 'bg-warn-600',
        'crit' => 'bg-crit-600',
        'info' => 'bg-info-600',
        'hold' => 'bg-hold-600',
    ];
    $key = $resolvedTone->value;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium '.$map[$key]]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dot[$key] }}"></span>
    {{ $resolvedLabel }}
</span>
