@props([
    'summary' => null,   // "총 1,248건 · 합계 ₩34,120,000"
])

{{--
  리스트 공통 테이블 뼈대 (DESIGN.md §5.1).
  사용:
    <x-data-table summary="...">
      <x-slot:head> <tr>...</tr> </x-slot:head>
      <tr>...행...</tr>
    </x-data-table>
--}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-line bg-surface-1']) }}>
    @if($summary)
        <div class="flex items-center justify-between border-b border-line px-5 py-3">
            <p class="font-mono text-sm text-ink-600">{{ $summary }}</p>
            @isset($tools)<div class="flex items-center gap-2">{{ $tools }}</div>@endisset
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            @isset($head)
                <thead class="border-b border-line bg-surface-2/70">
                    {{ $head }}
                </thead>
            @endisset
            <tbody class="divide-y divide-line/70">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="border-t border-line px-5 py-3">{{ $footer }}</div>
    @endisset
</div>
