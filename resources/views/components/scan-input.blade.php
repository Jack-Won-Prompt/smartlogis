@props([
    'placeholder' => '바코드를 스캔하거나 입력 후 Enter',
    'autofocus' => false,
])

{{--
  바코드 스캔 입력 (Scan Pulse, DESIGN.md §4.3).
  성공 시 scan:matched, 실패 시 scan:unmatched 이벤트를 상위로 dispatch.
  사용 예:  <x-scan-input @scan:matched="..." autofocus />
--}}
<div x-data="scanInput('{{ route('barcode.parse') }}', '{{ csrf_token() }}')"
     {{ $attributes }}>
    <div x-ref="box"
         class="flex items-center gap-2 rounded-xl border border-line bg-surface-1 px-3 transition-colors focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-400/25"
         :class="state === 'error' && 'border-crit-600'">
        <svg class="h-5 w-5 shrink-0 text-ink-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
            <path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14"/>
        </svg>
        <input x-ref="input" x-model="value" @keydown.enter.prevent="submit()"
               type="text" placeholder="{{ $placeholder }}" @if($autofocus) autofocus @endif
               inputmode="text" autocomplete="off"
               class="w-full border-0 bg-transparent py-3 text-sm text-ink-900 placeholder:text-ink-300 focus:ring-0">
        <template x-if="state === 'loading'">
            <svg class="h-4 w-4 shrink-0 animate-spin text-brand-500" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25"/>
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
        </template>
    </div>

    {{-- 파싱 결과 칩 (stagger 등장) --}}
    <div class="mt-2 flex flex-wrap items-center gap-1.5" x-show="chips.length" x-cloak>
        <template x-for="(chip, i) in chips" :key="chip.label">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface-2 px-2.5 py-1 font-mono text-xs text-ink-700"
                  x-transition:enter="transition ease-brand duration-300"
                  x-transition:enter-start="opacity-0 translate-y-1"
                  x-transition:enter-end="opacity-100 translate-y-0"
                  :style="`transition-delay:${i * 40}ms`">
                <span class="text-ink-300" x-text="chip.label"></span>
                <span class="font-medium" x-text="chip.value"></span>
            </span>
        </template>
    </div>

    {{-- 상태 메시지 --}}
    <p class="mt-1.5 flex items-center gap-1 text-xs" x-show="message" x-cloak
       :class="state === 'success' ? 'text-ok-600' : 'text-crit-600'">
        <span x-text="state === 'success' ? '✓ ' + message : message"></span>
    </p>
</div>
