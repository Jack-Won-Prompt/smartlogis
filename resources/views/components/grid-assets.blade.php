{{-- withworks 방식 jQuery DataTables 그리드 자산 + SmartDT 래퍼. iframe 화면 내부에서만 로드된다. --}}
@once
    @push('head')
        <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/4.0.1/css/fixedHeader.dataTables.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/select/2.1.0/css/select.dataTables.min.css">
        <style>
            /* withworks DataTables 룩 — SmartLogis 브랜드로 정돈(심플·클린) */
            table.dataTable { border-collapse: separate; border-spacing: 0; font-size: 13.5px; width: 100% !important; }
            table.dataTable thead th {
                background: #fff; border-bottom: 1px solid #e8edf1; color: #8493a1;
                font-weight: 600; font-size: 12px; padding: 11px 14px; text-align: left; white-space: nowrap;
            }
            table.dataTable tbody td { padding: 10px 14px; border-bottom: 1px solid #f0f3f6; color: #3b4855; vertical-align: middle; }
            table.dataTable tbody tr:hover td { background: #f7f9fb; }
            table.dataTable tbody tr.selected td { background: #f2f6fe !important; box-shadow: none; }
            table.dataTable tbody tr.dt-new td { background: #f4f8ff; }
            .dt-container { border: 1px solid #e8edf1; border-radius: 14px; background: #fff; padding: 4px 6px 10px; box-shadow: 0 1px 2px rgba(16,27,38,.03); }
            .dt-container .dt-search, .dt-container .dt-length { padding: 10px 12px 4px; }
            .dt-container .dt-search input, .dt-container .dt-length select {
                border: 1px solid #e8edf1; border-radius: 8px; padding: 5px 10px; font-size: 13px; outline: none;
            }
            .dt-container .dt-search input:focus { border-color: #2d6ae0; box-shadow: 0 0 0 2px rgba(45,106,224,.15); }
            .dt-container .dt-paging .dt-paging-button.current { background: #2551c4 !important; color: #fff !important; border: none; border-radius: 8px; }
            .dt-container .dt-paging .dt-paging-button { border: none !important; background: transparent; color: #7a8a99; border-radius: 8px; padding: 5px 10px; margin: 0 1px; font-family: 'IBM Plex Mono', monospace; }
            .dt-container .dt-paging .dt-paging-button:hover:not(.current) { background: #f2f6fe !important; color: #2551c4 !important; }
            .dt-container .dt-info { color: #8493a1; font-size: 12.5px; padding: 6px 12px; }
            table.dataTable thead th.dt-orderable-asc:hover, table.dataTable thead th.dt-orderable-desc:hover { background: #f7f9fb; }
            /* 편집 셀 */
            .sdt-cell-edit { display: block; width: 100%; border: 1px solid #2d6ae0; border-radius: 6px; padding: 4px 8px; font: inherit; box-shadow: 0 0 0 2px rgba(45,106,224,.2); }
            .sdt-editable { cursor: text; }
            .sdt-editable:hover { box-shadow: inset 0 0 0 1px #bfd5fb; border-radius: 6px; }
            /* 헬퍼 */
            .sdt-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; color: #101b26; }
            .sdt-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 12px; font-weight: 500; }
            .sdt-badge::before { content: ''; width: 6px; height: 6px; border-radius: 999px; background: currentColor; opacity: .9; }
            .sdt-ok { background: #def3e8; color: #1e8a5b; } .sdt-warn { background: #fbefd8; color: #b4700a; }
            .sdt-crit { background: #fbe4e1; color: #c2362b; } .sdt-info { background: #e0ecf9; color: #2563a8; }
            .sdt-hold { background: #edf1f4; color: #6b7a88; }
            .sdt-act { cursor: pointer; padding: 2px 6px; border-radius: 6px; }
            .sdt-act:hover { background: #eef2f5; }
        </style>
    @endpush
    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/fixedheader/4.0.1/js/dataTables.fixedHeader.min.js"></script>
        <script src="https://cdn.datatables.net/select/2.1.0/js/dataTables.select.min.js"></script>
        <script src="{{ asset('js/smartdt.js') }}"></script>
    @endpush
@endonce
