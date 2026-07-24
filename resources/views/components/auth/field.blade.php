@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'autofocus' => false,
    'placeholder' => null,
    'hint' => null,
    'icon' => null,       // heroicon path (선택)
])

@php $hasError = $errors->has($name); @endphp

<div x-data="{ focused: false }" class="group">
    <label for="{{ $name }}" class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-500">
        {{ $label }}
        @if ($required)
            <span class="h-1 w-1 rounded-full bg-brand-500" title="필수"></span>
        @endif
    </label>

    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center
                         text-ink-300 transition-colors group-focus-within:text-brand-500">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
            </span>
        @endif

        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->merge(['class' => 'block w-full rounded-xl border-slate-200 bg-white py-3 text-sm text-ink-900 shadow-sm transition
                placeholder:text-ink-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-400/30
                '.($icon ? 'pl-11 pr-4' : 'px-4').' '.($hasError ? 'border-crit-600 focus:border-crit-600 focus:ring-crit-600/20' : '')]) }}
        />
    </div>

    @if ($hasError)
        <p class="mt-1.5 flex items-center gap-1 text-xs text-crit-600">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01" stroke-linecap="round"/>
            </svg>
            {{ $errors->first($name) }}
        </p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-ink-300">{{ $hint }}</p>
    @endif
</div>
