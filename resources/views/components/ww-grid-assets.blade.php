{{-- wwGrid(원본 css/js 100% 그대로) + 서버 연동 커넥터 로드. 페이지당 한 번만. --}}
@once
    @push('head')
        <link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v={{ filemtime(public_path('vendor/wwgrid/wwGrid.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v={{ filemtime(public_path('vendor/wwgrid/wwGrid.js')) }}"></script>
        <script src="{{ asset('js/wwgrid-connect.js') }}?v={{ filemtime(public_path('js/wwgrid-connect.js')) }}"></script>
    @endpush
@endonce
