{{-- fulfillment mv2 방식 Toast UI Grid(TUI Grid 4.21) 자산 + SmartTUI 래퍼. iframe 화면 내부에서만 로드된다. --}}
@once
    @push('head')
        <link rel="stylesheet" href="https://uicdn.toast.com/tui.pagination/latest/tui-pagination.css">
        <link rel="stylesheet" href="https://uicdn.toast.com/grid/v4.21.0/tui-grid.css">
        <style>
            /* TUI Grid — SmartLogis 클린 테마(심플) + 셀 헬퍼 */
            .tui-grid-container { font-family: 'Pretendard Variable', Pretendard, sans-serif; }
            .tui-grid-wrapper { border: 1px solid #e8edf1; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(16,27,38,.03); }
            .tui-grid-header-area { background: #fff; border-bottom: 1px solid #e8edf1; }
            .tui-grid-cell-header { background: #fff !important; border-color: #eef2f5 !important; color: #8493a1 !important; font-weight: 600 !important; font-size: 12px; }
            .tui-grid-cell { border-color: #f0f3f6 !important; color: #3b4855; font-size: 13.5px; }
            .tui-grid-row-odd .tui-grid-cell, .tui-grid-row-even .tui-grid-cell { background: #fff; }
            .tui-grid-cell-current-row { background: #f7f9fb !important; }
            .stui-cell { display: flex; align-items: center; height: 100%; }
            .stui-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; color: #101b26; }
            .stui-badge { display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 500; }
            .stui-badge::before { content: ''; width: 6px; height: 6px; border-radius: 999px; background: currentColor; opacity: .9; }
            .stui-ok { background: #def3e8; color: #1e8a5b; } .stui-warn { background: #fbefd8; color: #b4700a; }
            .stui-crit { background: #fbe4e1; color: #c2362b; } .stui-info { background: #e0ecf9; color: #2563a8; }
            .stui-hold { background: #edf1f4; color: #6b7a88; }
            .stui-act { cursor: pointer; padding: 2px 5px; border-radius: 6px; }
            .stui-act:hover { background: #eef2f5; }
            /* 페이지네이션 — 매우 세련된 스타일 */
            .tui-pagination { display: flex; align-items: center; justify-content: center; gap: 3px; padding: 16px 0 6px; }
            .tui-pagination .tui-page-btn {
                display: inline-flex; align-items: center; justify-content: center;
                min-width: 32px; height: 32px; padding: 0 7px; margin: 0;
                border: 0 !important; background: transparent !important; box-shadow: none;
                border-radius: 9px; color: #6b7a88;
                font-family: 'IBM Plex Mono', monospace; font-size: 13px; font-weight: 500;
                text-decoration: none; cursor: pointer; transition: background .15s ease, color .15s ease, box-shadow .15s ease;
            }
            .tui-pagination a.tui-page-btn:hover { background: #eef3fe !important; color: #2551c4; }
            .tui-pagination strong.tui-page-btn.tui-is-selected {
                background: #2551c4 !important; color: #fff !important; font-weight: 700;
                box-shadow: 0 3px 10px rgba(37, 81, 196, .30);
            }
            .tui-pagination .tui-page-btn.tui-is-disabled { opacity: .28; cursor: default; pointer-events: none; }
            /* 네비(처음/이전/다음/끝) — 깔끔한 셰브론 */
            .tui-pagination .tui-first, .tui-pagination .tui-last { min-width: 30px; }
            .tui-pagination .tui-ico-first, .tui-pagination .tui-ico-prev,
            .tui-pagination .tui-ico-next, .tui-pagination .tui-ico-last {
                display: inline-flex; align-items: center; justify-content: center;
                width: auto; height: auto; margin: 0; background: none !important;
                font-size: 0; text-indent: 0; overflow: visible; border: 0;
            }
            .tui-pagination .tui-ico-first::after { content: '«'; }
            .tui-pagination .tui-ico-prev::after  { content: '‹'; }
            .tui-pagination .tui-ico-next::after  { content: '›'; }
            .tui-pagination .tui-ico-last::after  { content: '»'; }
            .tui-pagination .tui-ico-first::after, .tui-pagination .tui-ico-prev::after,
            .tui-pagination .tui-ico-next::after, .tui-pagination .tui-ico-last::after {
                font-family: system-ui, sans-serif; font-size: 15px; font-weight: 600; line-height: 1; color: inherit;
            }
        </style>
    @endpush
    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        {{-- TUI Grid 4.21 + peer deps(pagination/date-picker/time-picker) — mv2 와 동일 버전 --}}
        <script src="https://uicdn.toast.com/tui.time-picker/latest/tui-time-picker.min.js"></script>
        <script src="https://uicdn.toast.com/tui.pagination/latest/tui-pagination.min.js"></script>
        <script src="https://uicdn.toast.com/tui.date-picker/latest/tui-date-picker.min.js"></script>
        <script src="https://uicdn.toast.com/grid/v4.21.0/tui-grid.js"></script>
        <script>
            // SmartLogis 클린 테마 적용
            if (window.tui && window.tui.Grid) {
                window.tui.Grid.applyTheme('default', {
                    cell: {
                        normal: { background: '#fff', border: '#f0f3f6', text: '#3b4855', showVerticalBorder: false, showHorizontalBorder: true },
                        header: { background: '#fff', border: '#eef2f5', text: '#8493a1', showVerticalBorder: false },
                        selectedHeader: { background: '#f2f6fe' },
                        rowHeader: { background: '#fff', border: '#f0f3f6' },
                        evenRow: { background: '#fff' },
                        currentRow: { background: '#f7f9fb' },
                    },
                });
            }
        </script>
        <script src="{{ asset('js/smarttui.js') }}?v={{ filemtime(public_path('js/smarttui.js')) }}"></script>
    @endpush
@endonce
