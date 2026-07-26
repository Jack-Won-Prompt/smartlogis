{{-- wwGrid(원본 css/js 100% 그대로) + 서버 연동 커넥터 로드. 페이지당 한 번만. --}}
@once
    @push('head')
        <link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v={{ filemtime(public_path('vendor/wwgrid/wwGrid.css')) }}">
        {{-- 행이 적어 고정 높이로 채워질 때도 마지막 행 아래 구분선이 보이도록(원본은 last-child border 제거) --}}
        <style>.cg-tbody tr:last-child { border-bottom: 1px solid var(--cg-row-border) !important; }</style>
    @endpush
    @push('scripts')
        <script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v={{ filemtime(public_path('vendor/wwgrid/wwGrid.js')) }}"></script>
        <script src="{{ asset('js/wwgrid-connect.js') }}?v={{ filemtime(public_path('js/wwgrid-connect.js')) }}"></script>
    @endpush
@endonce
