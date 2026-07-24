{{--
  리스트 공통 필터 바 (DESIGN.md §5.1). 필드는 기본 slot 으로,
  우측 버튼(적용/초기화 등)은 actions slot 으로, 활성 필터 칩은 chips slot 으로 조립한다.
--}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-white p-4 shadow-[0_1px_2px_rgba(16,27,38,0.03)]']) }}>
    <div class="flex flex-wrap items-end gap-3">
        {{ $slot }}

        <div class="ml-auto flex items-center gap-2">
            <button type="button" data-grid-search class="btn-primary !py-2 !text-sm" data-magnetic>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                검색
            </button>
            {{ $actions ?? '' }}
        </div>
    </div>

    @isset($chips)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            {{ $chips }}
        </div>
    @endisset
</div>

@once
    @push('scripts')
        <script>
            // 필터바 '검색' 버튼 → 현재 화면 그리드 새로고침(현재 필터값으로 재조회).
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-grid-search]') && window.__smartGridRefresh) window.__smartGridRefresh();
            });
        </script>
    @endpush
@endonce
