{{--
  리스트 공통 필터 바 (DESIGN.md §5.1). 필드는 기본 slot 으로,
  우측 버튼(적용/초기화 등)은 actions slot 으로, 활성 필터 칩은 chips slot 으로 조립한다.
--}}
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-surface-2 p-4']) }}>
    <div class="flex flex-wrap items-end gap-3">
        {{ $slot }}

        @isset($actions)
            <div class="ml-auto flex items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>

    @isset($chips)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            {{ $chips }}
        </div>
    @endisset
</div>
