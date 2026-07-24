@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],   // ['기준정보' => null, '제품' => route(...)]
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div>
        @if(!empty($breadcrumbs))
            <nav class="mb-1.5 flex items-center gap-1.5 text-xs text-ink-300">
                @foreach($breadcrumbs as $label => $url)
                    @if(!$loop->first)<span>/</span>@endif
                    @if($url)<a href="{{ $url }}" class="hover:text-brand-600">{{ $label }}</a>
                    @else<span class="text-ink-500">{{ $label }}</span>@endif
                @endforeach
            </nav>
        @endif
        <h1 class="font-display text-[22px] font-extrabold tracking-tight text-ink-900">{{ $title }}</h1>
        @if($subtitle)<p class="mt-1 text-sm text-ink-500">{{ $subtitle }}</p>@endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
