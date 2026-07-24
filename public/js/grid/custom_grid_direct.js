// Grid Directly Related Source (checked)
// Range: custom.js lines 2149-6846
// Note: syntax checked after split

function withworksIsPetIndustry() {
    var viewCall = window.WITHWORKS_VIEW_CALL ||
        (window.SCREEN_MANUAL_CONFIG && window.SCREEN_MANUAL_CONFIG.viewCall) ||
        '';
    var normalizedViewCall = String(viewCall).replace(/\\/g, '/').replace(/\./g, '/').toLowerCase();

    if (normalizedViewCall.indexOf('pet/standard') !== -1) {
        return true;
    }

    return /(^|\/)pet(\/|$)/i.test(window.location.pathname || '');
}

function withworksCleanExcelFileNamePart(value) {
    return String(value || '')
        .replace(/\s+/g, ' ')
        .replace(/[\\/:*?"<>|]/g, '')
        .trim();
}

function withworksGetGridElement(gridSelector) {
    var selector = String(gridSelector || '').trim();
    if (!selector) return null;

    if (selector.charAt(0) === '#' || selector.charAt(0) === '.') {
        try {
            return document.querySelector(selector);
        } catch (e) {
            return null;
        }
    }

    return document.getElementById(selector);
}

function withworksCssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }

    return String(value || '').replace(/["\\]/g, '\\$&');
}

function withworksGetPetExcelFileName(gridSelector, extension) {
    if (!withworksIsPetIndustry()) return null;

    var ext = extension || '.xlsx';
    var screenName = withworksCleanExcelFileNamePart(
        document.querySelector('h4.m-b-10') ? document.querySelector('h4.m-b-10').textContent : ''
    );
    var tabName = '';
    var gridEl = withworksGetGridElement(gridSelector);
    var tabPane = gridEl && gridEl.closest ? gridEl.closest('.tab-pane') : null;

    if (tabPane) {
        var paneId = tabPane.getAttribute('id');
        var tabButton = null;

        if (paneId) {
            var escapedPaneId = withworksCssEscape(paneId);
            tabButton = document.querySelector(
                '.nav-link[data-bs-target="#' + escapedPaneId + '"], ' +
                '.nav-link[data-target="#' + escapedPaneId + '"], ' +
                '.nav-link[href="#' + escapedPaneId + '"]'
            );
        }

        if (!tabButton) {
            tabButton = document.querySelector('.nav-link.active');
        }

        tabName = withworksCleanExcelFileNamePart(tabButton ? tabButton.textContent : '');
    }

    if (screenName && tabName) return screenName + '_' + tabName + ext;
    if (screenName) return screenName + ext;

    var fallback = String(gridSelector || '').replace(/^#/, '') || 'grid';
    return withworksCleanExcelFileNamePart(fallback) + ext;
}

function drawGrid(selector, header, body, footer, chkBox=true, wrapSelector='.add_new_row',page = 50) {
    // localStorage에서 그리드 설정을 불러옴
    let savedSettings = null

    // 로컬 스토리지에 등록 할 세그먼트 이름 구하기
    var pathname = window.location.pathname;
    var segments = pathname.split('/');
    var segment = segments.pop() || "No Segment";
    //if(segment != 'No Segment'){
    //    savedSettings = localStorage.getItem('grid_settings_'+segment+'_'+selector);

    //    if (savedSettings) {
    //        header = JSON.parse(savedSettings);
    //    }
    //}

    // 복합 컬럼 분리 및 header에서 제거
    let complexColumns = [];
    let columns = [];

    // 복합 컬럼 추출
    if (header && Array.isArray(header.complexColumns)) {
        complexColumns = header.complexColumns; // 복합 컬럼 저장
        delete header.complexColumns; // 복합 컬럼 제거
    }

    // 일반 컬럼 필터링
    if (Array.isArray(header)) {
        // header가 배열인 경우
        columns = header.filter(item => !item.childNames);
    } else if (header && typeof header === 'object') {
        // header가 객체인 경우 Object.values로 변환
        const headerArray = Object.values(header).filter(item => typeof item === 'object'); // 객체로부터 유효한 값만 추출
        columns = headerArray.filter(item => !item.childNames);
    }

    // 셀 커스텀 (autoSet 셀(input+button), button 셀)
    columns.forEach(function(col) {
        if (col['editor']) {
            const type = typeof col['editor'] === 'object' ? col['editor']['type'] : col['editor'];
            switch (type) {
                case 'customInput':
                    col['editor'] = {
                        type: CustomTextEditor,
                        options: {
                            inputClassName: 'popup_'+col['name'],
                            btnClassName: 'search_'+col['name'],
                            btnInnerHtml: '<span class="btn-inner--icon"><i class="ti ti-search"></i></span>'
                        }
                    };
                    break;
                case 'customDatePicker':
                    col['editor'] = {
                        type: CustomDatePickerEditor
                    };
                    break;
                case 'customDatePickerMDY':
                    col['editor'] = {
                        type: CustomDatePickerEditorMDY
                    };
                    break;
            }
        }
        if (col['renderer']) {
            switch (col['renderer']['type']) {
                case 'customButton':
                    col['renderer']['type'] = CustomButtonRenderer;
                    break;
                case 'customChkBox':
                    col['renderer']['type'] = CustomChkBoxRenderer;
                    break;
            }
        }
        // col['sortable'] = true;
        // col['sortingType'] = 'asc';
    })

    // 그리드 생성 전 개인화 컬럼 순서 동기 로드 (handle_draw_grid의 build_final_config와 동일한 방식)
    const gridDbConfig = {
        selector: selector,
        header: Array.isArray(header) ? header : columns,
        columns: columns,
        header_flag: null
    };
    preload_grid_column_order_runtime_state(gridDbConfig);
    const _dbState = get_grid_column_order_runtime_state(gridDbConfig);
    const _dbOrder = Array.isArray(_dbState.lastLoadedOrder) && _dbState.lastLoadedOrder.length > 0
        ? _dbState.lastLoadedOrder
        : (Array.isArray(_dbState.lastSavedOrder) ? _dbState.lastSavedOrder : []);
    const _dbWidths = Object.keys(_dbState.lastLoadedWidths || {}).length > 0
        ? _dbState.lastLoadedWidths
        : (_dbState.lastSavedWidths || {});
    if (_dbOrder.length > 0) {
        columns = reorder_grid_column_definitions(columns, _dbOrder);
    }
    if (Object.keys(_dbWidths).length > 0) {
        columns = apply_grid_column_widths_to_definitions(columns, _dbWidths);
    }

    // 고정열 정의
    // 정규 표현식을 사용하여 "popup-excel-XYZ-grid" 패턴 매칭 검사
    var pattern = /^popup-excel-.*-grid$/;
    let rowHeaders = []
    if (!pattern.test(selector)) { // 엑셀 업로드 팝업 그리드
        rowHeaders.push({
            type: 'rowNum', // 행 인덱스
            renderer: {
                type: RowNumberRenderer,
                options: {
                    pageNum: $('.paging.'+selector+' ul li.on a').text() || 1,
                    bodyLength: page
                }
            }
        });
    }
    if (chkBox) {
        rowHeaders.push({
            type: 'checkbox', // table checkbox
            header: `
                <label for="all-checkbox" class="checkbox">
                    <input type="checkbox" class="form-check-input" name="_checked" />
                    <span class="custom-input"></span>
                </label>
            `,
            renderer: { type: CheckboxRenderer }
        });
    }

    let grid = tui.Grid;
    grid.applyTheme('clean', grid_option);
    var body_height01 = ($(wrapSelector).find('.tab_btn_wrap').innerHeight() > 1) ? $(wrapSelector).find('.tab_btn_wrap').innerHeight() : 0;
    var body_height02 = ($(wrapSelector).find('.table_btn_wrap').innerHeight() > 1) ? $(wrapSelector).find('.table_btn_wrap').innerHeight() : 0;
    var body_height03 = ($(wrapSelector).find('.paging_wrap').innerHeight() > 1) ? $(wrapSelector).find('.paging_wrap').innerHeight() + 23 : 0;
    var height_sum = (body_height01 + body_height02 +body_height03 + 62)
    const tabId2 = $(wrapSelector).attr('grid-tab');

    if(wrapSelector.includes('inner')) {
        height_sum = (body_height01 + body_height02 +body_height03 + 180)
    }

    let complex_height = complexColumns.length > 0 ? 30 : 0;

    // // 컬럼 헤더 옆 필터 아이콘 자동 활성화 (grid_search.js)
    // if (typeof window.enableGridColumnFilters === 'function') {
    //     window.enableGridColumnFilters(columns);
    // }
    // 날짜 컬럼 가운데 정렬
    if (typeof window.centerAlignDateColumns === 'function') {
        window.centerAlignDateColumns(columns, body);
    }
    // Y/N 등 boolean 컬럼 가운데 정렬
    if (typeof window.centerAlignBooleanColumns === 'function') {
        window.centerAlignBooleanColumns(columns, body);
    }
    // 숫자 컬럼 천단위 콤마 포매터 자동 적용
    if (typeof window.applyNumberCommaFormat === 'function') {
        window.applyNumberCommaFormat(columns, body);
    }
    // 합계(summary) 행 template 결과에 콤마 자동 적용
    if (typeof window.applyCommaToSummaryTemplates === 'function') {
        window.applyCommaToSummaryTemplates(footer);
    }

    grid = new tui.Grid({
        el: document.getElementById(selector),
        data: body,
        editingEvent: 'click',
        // editingEvent: 'dblclick',
        rowHeaders: rowHeaders,
        header: {
            height: 30 + complex_height, // complexColumns가 비어있으면 30, 그렇지 않으면 60
            complexColumns: complexColumns // 복합 헤더 적용
        },
        columns: columns, // 일반 컬럼 적용
        summary: {
            height: 30,
            position: 'bottom', // or 'top'
            columnContent: footer
        },
        bodyHeight: wrapSelector.includes('PopGrid') ? 435 : $(wrapSelector).innerHeight() - height_sum - complex_height,
        minBodyHeight: tabId2 ? 100 : $(wrapSelector).innerHeight() - height_sum - complex_height,
        rowHeight: 30,
        minRowHeight: 30,
        scrollX: true,
        scrollY: true,
        includeHiddenColumns: true,
        columnOptions: {
            resizable: true,
            frozenCount: header[0].name == 'icon' ? 1 : 0,
        },
        draggable: true,
        copyOptions: {
            useFormattedValue: false, // 복사된 값이 포맷된 값으로 붙여넣어짐
        },
        onGridMounted(ev) {
            // 동일 행에 대한 className 세팅 (공통팝업과 autoSet 기능을 위해)
            for(var i=0; i<grid.getRowCount(); i++) {
                grid.addRowClassName(i, 'tr_'+i);
            }
            // grid 렌더링 후 실행되어야 하는 함수 (각 화면별 정의 함수)
            if (typeof configAfterMounted === 'function') configAfterMounted();
        },
    });

    // 셀 편집 이벤트
    let orginValue = '';
    grid.on('editingStart', (ev) => {
        orginValue = ev.value;
    });
    grid.on('editingFinish', (ev) => {
        // 값 수정 시 행 자동 체크 및 crud_type 'U' 처리
        const rowKey = ev.rowKey;
        if (ev.value != orginValue) {
            grid.check(rowKey);
            const rowDetail = grid.getRow(rowKey)
            const crudType = rowDetail.crud_type;
            if(crudType != 'D' && crudType != 'C') {
                grid.setValue(rowKey, 'crud_type', 'U', false);
                grid.setValue(rowKey, 'icon', udtIcon, false);
            }
        }
    });
    // grid.on('afterChange', (ev) => {
    //     console.log('ev',ev)
    //     console.log('ev.columnName',ev.columnName)
    //     console.log('ev.rowKey',ev.rowKey)
    //
    //     if(ev.origin == 'paste') {
    //         console.log('paste')
    //         console.log('ev.change',ev.changes)
    //
    //         ev.changes.forEach(function(change) {
    //             console.log(change);
    //             console.log('1');
    //             // grid.setValue(change.rowKey, change.columnName, change.value, false);
    //             console.log('2');
    //             // grid.startEditing(change.rowKey, change.columnName, true)
    //             // console.log('test',change.value + ' ');
    //             // grid.finishEditing(change.rowKey, change.columnName, change.value + ' ')
    //             // 키인 띄어쓰기 엔터 처리
    //             console.log('4');
    //         });
    //
    //     }
    //
    // });



    grid.on('afterChange', (ev) => {
        if(ev.origin == 'paste') {
            ev.changes.forEach(function(change) {
                grid.setValue(change.rowKey, change.columnName, '', false);
                grid.startEditing(change.rowKey, change.columnName, true)
                // grid.setValue(change.rowKey, change.columnName, change.value , false);

                const inputElements = document.querySelectorAll(`input[data-column-name="${change.columnName}"]`);

                if(inputElements.length == 1) {
                    inputElements.forEach(inputElement => {
                        inputElement.value = change.value; // 값 설정
                        const event = new Event('change', { bubbles: true });
                        inputElement.dispatchEvent(event); // change 이벤트 트리거
                    });
                } else {
                    // grid.setValue(change.rowKey, change.columnName, change.value , false);
                    grid.finishEditing(change.rowKey, change.columnName, change.value)
                }
            });
        }
    });

    // 스플리터 및 필터폼 토글 기능으로 높이 변경 시 bodyHeight 재세팅
    const splitterResizeEvent = 'igsplitterresizeended igsplitterexpanded igsplittercollapsed';

    $('.splitter-hrz').on(splitterResizeEvent, function (evt, ui) {
        const children = $(this).find('.table-card-body');
        if (children.length && children.hasClass(wrapSelector.replace('.',''))) {
            var body_height01 = ($(wrapSelector).find('.tab_btn_wrap').actual('innerHeight') > 1) ? $(wrapSelector).find('.tab_btn_wrap').actual('innerHeight') : 0;
            var body_height02 = ($(wrapSelector).find('.table_btn_wrap').actual('innerHeight') > 1) ? $(wrapSelector).find('.table_btn_wrap').actual('innerHeight') : 0;
            var body_height03 = ($(wrapSelector).find('.paging_wrap').actual('innerHeight') > 1) ? $(wrapSelector).find('.paging_wrap').actual('innerHeight') + 23 : 0;
            var height_sum = (body_height01 + body_height02 +body_height03 + 62)

            if(wrapSelector.includes('inner')) {
                height_sum = (body_height01 + body_height02 +body_height03 + 180)
            }

            grid.setBodyHeight($(wrapSelector).innerHeight() - height_sum);
        }
    });
    $('.splitter-vtc').on(splitterResizeEvent, function (evt, ui) {
        const children = $(this).find('.table-card-body');
        const children2 = $(this).find('.right-grid');
        var newWidth = children.width(); // 컨테이너의 새로운 너비를 가져옵니다.
        var rightWidth = children2.width(); // 스플리터 기준 오른쪽 그리드 width를 가져옴
        if (children.length && children.hasClass(wrapSelector.replace('.',''))) { // 해당 그리드에만 적용
            grid.setWidth(newWidth); // TUI 그리드의 너비를 새로운 너비로 설정합니다.
        }

        if (children2.length && children2.hasClass(wrapSelector.replace('.',''))) {
            grid.setWidth(rightWidth);
        }
    });
    $('.btn-toggle').on('click', function () {
        var body_height01 = ($(wrapSelector).find('.tab_btn_wrap').actual('innerHeight') > 1) ? $(wrapSelector).find('.tab_btn_wrap').actual('innerHeight') : 0;
        var body_height02 = ($(wrapSelector).find('.table_btn_wrap').actual('innerHeight') > 1) ? $(wrapSelector).find('.table_btn_wrap').actual('innerHeight') : 0;
        var body_height03 = ($(wrapSelector).find('.paging_wrap').actual('innerHeight') > 1) ? $(wrapSelector).find('.paging_wrap').actual('innerHeight') + 23 : 0;
        var height_sum = (body_height01 + body_height02 +body_height03 + 62)

        if(wrapSelector.includes('inner')) {
            height_sum = (body_height01 + body_height02 +body_height03 + 180)
        }

        grid.setBodyHeight($(wrapSelector).innerHeight() - height_sum);
    });

    // 테이블 바깥 영역으로 포커스 아웃시 셀 편집 이벤트 강제 종료 트리거
    $(document).on('blur', '#'+selector+' input', (ev) => {
        const editorWrap = $(ev.relatedTarget).parents('.tui-grid-layer-editing');
        if (editorWrap.length < 1) { // 셀 편집 영역 내의 버튼을 클릭한 경우 예외 처리
            const selectedRow = grid.getFocusedCell();
            grid.finishEditing(selectedRow.rowKey, selectedRow.columnName);
        }
    });

    $(document).on('blur', '#'+selector+' select', (ev) => {
        const editorWrap = $(ev.relatedTarget).parents('.tui-grid-layer-editing');
        if (editorWrap.length < 1) { // 셀 편집 영역 내의 버튼을 클릭한 경우 예외 처리
            const selectedRow = grid.getFocusedCell();
            grid.finishEditing(selectedRow.rowKey, selectedRow.columnName);
        }
    });
    $(document).on('click','.tui-select-box-item',function(){
        grid.blur();
    });

    // customEditor change trigger -> 셀 편집 이벤트 강제 트리거
    $(document).on('change', 'input.tui-grid-content-text-custom', (ev) => {
        const rowKey = ev.target.dataset.rowKey;
        const colName = ev.target.dataset.columnName;
        grid.finishEditing(rowKey, colName);
    });


    // 테이블이 숨겨진 엘리먼트일 경우 (탭이나 모달)
    if (!$('#'+selector).is(':visible')) {
        const tabId = $('#' + selector).parents('.tab-pane').attr('aria-labelledby');
        const tabId2 = $(wrapSelector).attr('grid-tab');
        $(`#${tabId}`).on('shown.bs.tab', function (e) {
            console.log('비활성요소1')
            grid.refreshLayout();
            grid.setBodyHeight($(wrapSelector).innerHeight() - 150);
            //grid.setBodyHeight($(wrapSelector).innerHeight() - height_sum);
        });
        $(`#${tabId2}`).on('click', function (e) {
            grid.refreshLayout();
            grid.setBodyHeight($(wrapSelector).innerHeight() - 141);
            // grid.setBodyHeight($(wrapSelector).innerHeight() - height_sum);
        });
        // todo: 모달 그리드 작업시 테스트 후 주석 풀기 (그리드 행 높이 조절되는지 확인 필요)
        // const modalId = $('#' + selector).parents('.modal').attr('id');
        // $(`#${modalId}`).one('shown.bs.modal', function (e) {
        //     grid.refreshLayout();
        //     grid.setBodyHeight($(wrapSelector).height() - 150);
        // });
    }

    if(segment != 'No Segment'){
        grid.on('drop', (ev) => {
            changeGrid(segment+'_'+selector, grid, gridDbConfig);
        });
        grid.on('columnResize', (ev) => {
            changeGrid(segment+'_'+selector, grid, gridDbConfig);
        });
    }

    // preload로 컬럼 순서가 이미 적용됐으므로, 비동기 로드 시 apply_grid_column_order_to_grid의
    // 시그니처 동일 체크에서 setColumns 호출이 생략됨 (handle_draw_grid와 동일한 방식)
    load_grid_column_order_from_db(grid, gridDbConfig)
        .catch(error => console.warn('[grid db order] apply failed:', error));

    /*// 폼 데이터 객체 생성
    var formData = new FormData();

    // 체크된 행들의 rowKey 값을 가져와서 폼 데이터에 추가
    grid.getCheckedRows().forEach(function (ev) {
        // 각 rowKey에 해당하는 데이터 가져오기
        var rowData = grid1.getRow(ev.rowKey);

        // 폼 데이터에 추가
        Object.keys(rowData).forEach(function (columnName) {
            formData.append(columnName, rowData[columnName]);
        });
    });
    // 만들어진 폼 데이터 확인
    for (var pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }*/

    // 리스트형 그리드라면 상단에 통합 검색창 자동 부착 (grid_search.js)
    // if (typeof window.autoAttachListGridFeatures === 'function') {
    //     window.autoAttachListGridFeatures(grid, selector, body);
    // }

    return grid;
}

function build_final_config(config) {
    /********************************************
     * Config 통합
     ********************************************/
    const default_config = {
        footer          : {},
        check_box       : true,
        paste_row       : false,
        wrap_selector   : '.add_new_row',
        icon            : false,
        page            : 50,
        sortable        : true,
        header_flag     : 'v3',
        row_count       : true,
        db_column_order : true,
        copy_enabled    : true,
        // ─── 그룹핑 옵션 ───────────────────────────────────────
        grouping        : false,   // true 로 설정 시 그룹핑 드롭존이 자동으로 그리드 위에 생성됨
        groupingColumns : [],      // 그룹핑 기본 컬럼
        groupSortColumn : null,    // 그룹 내 정렬 기준 컬럼명 (예: 'total_amt')
        useColumnReorder: true,    // true 이면 컬럼 드래그 순서 변경 활성, 그룹핑은 Shift+드래그 (기본값: true)
        groupingSummaryColumns : [], // 합계 계산할 지정 컬럼
        groupingAvgColumns : [], // 평균값 계산할 지정 컬럼
        groupingAvgScale : null, // 평균값 계산 시 반올림 소수점 지정
        // ─── 엑셀 다운로드 옵션 ───────────────────────────────────
        excelExport   : false,   // null = 자동 바인딩, false = 명시적 비활성화
        excelBtn      : null,   // 버튼 CSS selector (예: '.btn-excel1'). null 이면 selector에서 자동 추출
        excelFileName : null,   // 파일명 (예: '목록.xlsx'). null 이면 selector명.xlsx
        excelSheet    : 'Sheet1', // 시트명
    }

    // 기본설정 + Custom 설정
    const final_config = { ...default_config, ...config };
    preload_grid_column_order_runtime_state(final_config);
    const state = get_grid_column_order_runtime_state(final_config);
    const runtimeOrder = Array.isArray(state.lastLoadedOrder) && state.lastLoadedOrder.length > 0
        ? state.lastLoadedOrder
        : (Array.isArray(state.lastSavedOrder) ? state.lastSavedOrder : []);
    const runtimeWidths = Object.keys(state.lastLoadedWidths || {}).length > 0
        ? state.lastLoadedWidths
        : (state.lastSavedWidths || {});

    if(final_config.icon) {
        // icon 기능을 쓰는 그리드는, crud_type이 없으면 기본 'U'로 세팅
        if (Array.isArray(final_config.body)) {
            final_config.body = final_config.body.map(r => ({
                ...r,
                crud_type: (r && r.crud_type) ? r.crud_type : 'U'
            }));
        }

        const hasIconColumn = Array.isArray(final_config.header)
            ? final_config.header.some(col => col && col.name === 'icon')
            : false;

        if (!hasIconColumn && Array.isArray(final_config.header)) {
            const icon_column = {
                header: ' ',
                name: 'icon',
                width: 30,
                minWidth: 30,
                align: 'center',
                disabled: 1,
                resizable: 0,
            };

            final_config.header.unshift(icon_column);
        }
    }

    if (Array.isArray(final_config.header) && runtimeOrder.length > 0) {
        final_config.header = reorder_grid_column_definitions(final_config.header, runtimeOrder);
    }
    if (Array.isArray(final_config.header) && Object.keys(runtimeWidths).length > 0) {
        final_config.header = apply_grid_column_widths_to_definitions(final_config.header, runtimeWidths);
    }

    /********************************************
     * 복합 컬럼
     ********************************************/
    if (final_config.header && Array.isArray(final_config.header.complexColumns)) {
        final_config.complex_columns = final_config.header.complexColumns; // 복합 컬럼 저장
        delete final_config.header.complexColumns; // 복합 컬럼 제거
    }
    if (Array.isArray(final_config.header)) {
        // final_config.header가 배열인 경우
        final_config.columns = final_config.header.filter(item => !item.childNames);
    } else if (final_config.header && typeof final_config.header === 'object') {
        // header가 객체인 경우 Object.values로 변환
        const headerArray = Object.values(final_config.header).filter(item => typeof item === 'object'); // 객체로부터 유효한 값만 추출
        final_config.columns = headerArray.filter(item => !item.childNames);
    }

    if (Array.isArray(final_config.columns) && runtimeOrder.length > 0) {
        final_config.columns = reorder_grid_column_definitions(final_config.columns, runtimeOrder);
    }
    if (Array.isArray(final_config.columns) && Object.keys(runtimeWidths).length > 0) {
        final_config.columns = apply_grid_column_widths_to_definitions(final_config.columns, runtimeWidths);
    }

    /********************************************
     * 포메터 - 콤마
     ********************************************/
    if (Array.isArray(final_config.columns)) {
        final_config.columns.forEach(col => {
            // 예: format === 'number_comma'
            if (typeof col.format === 'string' && col.format.startsWith('number_comma')) {

                let scale = 0;
                const match = col.format.match(/number_comma_(\d+)/);
                if (match) scale = parseInt(match[1], 10);

                if (!col.formatter) {
                    col.formatter = (v) => {
                        const value = (v && typeof v === 'object' && 'value' in v)
                            ? v.value
                            : v;

                        return formatNumberCommaRound(value, scale);
                    };
                }

                col._useCommaFormat = true;
                col._commaScale = scale;
            }
        });
    }

    /********************************************
     * 푸터 포메터 - 콤마
     ********************************************/
    if (final_config.footer && Array.isArray(final_config.columns)) {
        Object.keys(final_config.footer).forEach(colName => {
            const footerCol = final_config.footer[colName];
            const colMeta = final_config.columns.find(c => c.name === colName);

            if (!footerCol || !footerCol.template || !colMeta) return;

            // 이 컬럼이 comma 대상인지 판단
            if (typeof colMeta.format === 'string' && colMeta.format.startsWith('number_comma')) {
                const originTemplate = footerCol.template;
                const scale = colMeta._commaScale ?? 0;

                footerCol.template = function(valueMap) {
                    const result = originTemplate(valueMap);
                    return formatNumberCommaRound(result, scale);
                };
            }
        });
    }

    return final_config;
}

function formatNumberCommaRound(value, scale = 0) {
    if (value == null || value === '') return '';

    const num = Number(value);
    if (isNaN(num)) return value;

    const factor = Math.pow(10, scale);
    const rounded = Math.round(num * factor) / factor;

    return rounded.toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: scale
    });
}

function create_grid(config) {
    /********************************************
     * 그리드 생성
     ********************************************/
    apply_custom_editors_renderers(config);
    const row_headers = build_row_headers(config);
    const body_height = calculate_body_height(config);
    const tab_id2 = $(config.wrap_selector).attr('grid-tab');

    let complex_height = config.complex_columns ? 30 : 0;
    tui.Grid.applyTheme('clean', grid_option);

    const grid_options = {
        el: document.getElementById(config.selector),
        data: config.body,
        editingEvent: 'click',
        // editingEvent: 'dblclick',
        rowHeaders: row_headers,
        header: {
            height: 30 + complex_height, // final_config.complex_columns 비어있으면 30, 그렇지 않으면 60
            complexColumns: config.complex_columns // 복합 헤더 적용
        },
        columns: config.columns, // 일반 컬럼 적용
        summary: {
            height: 30,
            position: 'bottom', // or 'top'
            columnContent: config.footer
        },
        pasteRow: config.paste_row,
        bodyHeight: config.wrap_selector.includes('PopGrid')
            ? 435
            : $(config.wrap_selector).innerHeight() - body_height - complex_height,
        minBodyHeight: tab_id2
            ? 100
            : $(config.wrap_selector).innerHeight() - body_height - complex_height,
        rowHeight: 30,
        minRowHeight: 30,
        scrollX: true,
        scrollY: true,
        includeHiddenColumns: true,
        columnOptions: {
            resizable: true,
            frozenCount: config.header[0].name == 'icon' ? 1 : 0,
        },
        draggable: true,
        copyOptions: {
            useFormattedValue: false, // 복사된 값이 포맷된 값으로 붙여넣어짐
        },
        onGridMounted(ev) {
            // 동일 행에 대한 className 세팅 (공통팝업과 autoSet 기능을 위해)
            for(let i=0; i<grid.getRowCount(); i++) {
                grid.addRowClassName(i, 'tr_'+i);
            }
            // grid 렌더링 후 실행되어야 하는 함수 (각 화면별 정의 함수)
            if (typeof configAfterMounted === 'function') configAfterMounted();
        },
    };

    if (config.extra_options && typeof config.extra_options === 'object') {
        Object.assign(grid_options, config.extra_options);
    }

    const grid = new tui.Grid(grid_options);
    bind_grid_copy_event(grid, config);

    if (config.row_count !== false) {

        const render = () => {
            setTimeout(() => {
                setSummaryRowCount(grid, config.selector);
            }, 30);
        };

        render();

        grid.on('onGridUpdated', render);
        grid.on('sort', render);
        grid.on('filter', render);
        grid.on('afterPageMove', render);
    }

    return grid;
}

function bind_grid_copy_event(grid, config) {
    if (config.copy_enabled !== false) {
        return;
    }

    const grid_element = document.getElementById(config.selector);
    if (!grid_element || grid_element.dataset.copyDisabled == '1') {
        return;
    }

    grid_element.dataset.copyDisabled = '1';

    const prevent_copy = function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
    };

    grid_element.addEventListener('copy', prevent_copy, true);
    grid_element.addEventListener('keydown', function (event) {
        const key = (event.key || '').toLowerCase();
        if ((event.ctrlKey || event.metaKey) && key == 'c') {
            prevent_copy(event);
        }
    }, true);
}

function apply_custom_editors_renderers(config) {
    /********************************************
     * 그리드 커스텀
     * 렌더링 (버튼, 체크박스)
     * customInput : 공통팝업
     * customDatePicker : 날짜
     ********************************************/
    let columns = config.columns;
    columns.forEach(function(col) {
        if (col['editor']) {
            const type = typeof col['editor'] === 'object' ? col['editor']['type'] : col['editor'];
            switch (type) {
                case 'customInput':
                    col['editor'] = {
                        type: CustomTextEditor,
                        options: {
                            inputClassName: 'popup_'+col['name'],
                            btnClassName: 'search_'+col['name'],
                            btnInnerHtml: '<span class="btn-inner--icon"><i class="ti ti-search"></i></span>'
                        }
                    };
                    break;
                case 'customDatePicker':
                    col['editor'] = {
                        type: CustomDatePickerEditor
                    };
                    break;
                case 'customDatePickerMDY':
                    col['editor'] = {
                        type: CustomDatePickerEditorMDY
                    };
                    break;
            }
        }
        if (col['renderer']) {
            switch (col['renderer']['type']) {
                case 'customButton':
                    col['renderer']['type'] = CustomButtonRenderer;
                    break;
                case 'customChkBox':
                    col['renderer']['type'] = CustomChkBoxRenderer;
                    break;
            }
        }

        if(config.sortable) {
            col['sortable'] = true;
        }
        // col['sortingType'] = 'asc';
    })
}

function build_row_headers(config) {
    /********************************************
     * 고정열 정의
     * 정규 표현식을 사용하여 "popup-excel-XYZ-grid" 패턴 매칭 검사
     ********************************************/
    let row_headers = []
    var pattern = /^popup-excel-.*-grid$/;
    if (!pattern.test(config.selector)) { // 엑셀 업로드 팝업 그리드
        row_headers.push({
            type: 'rowNum', // 행 인덱스
            renderer: {
                type: RowNumberRenderer,
                options: {
                    pageNum: $('.paging.'+config.selector+' ul li.on a').text() || 1,
                    bodyLength: config.page
                }
            }
        });
    }
    if (config.check_box) {
        row_headers.push({
            type: 'checkbox', // table checkbox
            header: `
                <label for="all-checkbox" class="checkbox">
                    <input type="checkbox" class="form-check-input" name="_checked" />
                    <span class="custom-input"></span>
                </label>
            `,
            renderer: { type: CheckboxRenderer }
        });
    }

    return row_headers;
}

function calculate_body_height(config) {
    var body_height01 = ($(config.wrap_selector).find('.tab_btn_wrap').innerHeight() > 1)
        ? $(config.wrap_selector).find('.tab_btn_wrap').innerHeight()
        : 0;

    var body_height02 = ($(config.wrap_selector).find('.table_btn_wrap').innerHeight() > 1)
        ? $(config.wrap_selector).find('.table_btn_wrap').innerHeight()
        : 0;

    var body_height03 = ($(config.wrap_selector).find('.paging_wrap').innerHeight() > 1)
        ? $(config.wrap_selector).find('.paging_wrap').innerHeight() + 23
        : 0;

    var body_height04 = ($(config.wrap_selector).find('.table_list').innerHeight() > 1)
        ? $(config.wrap_selector).find('.table_list').innerHeight()
        : 0;

    // ✅ 그룹핑 드롭존 높이 추가
    var body_height05 = 0;
    if (config.grouping) {
        const zone = document.querySelector('.tui-grp-zone[data-for="' + config.selector + '"]');
        body_height05 = zone ? Math.ceil(zone.getBoundingClientRect().height) + 5 : 43;
    }

    var height_sum = (body_height01 + body_height02 + body_height03 + body_height04 + body_height05 + 62);

    if (config.wrap_selector.includes('inner')) {
        height_sum = (body_height01 + body_height02 + body_height03 + body_height04 + body_height05 + 180);
    }

    if (config.wrap_selector.includes('dashboard_inner')) {
        height_sum = 100 + body_height05;
    }

    return height_sum;
}

function setSummaryRowCount(grid, selector) {

    const el = document.querySelector('#' + selector);
    if (!el) return;

    const cell = el.querySelector(
        '.tui-grid-summary-area td[data-column-name="_number"]'
    );

    if (!cell) return;

    cell.innerText = grid.getRowCount();

    cell.style.textAlign = 'left';
    cell.style.paddingLeft = '6px';
}

function bind_grid_events(grid, config) {
    /********************************************
     * 그리드 값 수정 시 행 자동 체크
     ********************************************/
    let origin_value = '';
    grid.on('editingStart', (ev) => {
        origin_value = ev.value ?? '';
    });
    grid.on('editingFinish', (ev) => {
        const rowKey = ev.rowKey;

        if (ev.value != origin_value) {
            grid.check(rowKey);
            const rowDetail = grid.getRow(rowKey)
            const crudType = rowDetail.crud_type;
            if(crudType != 'D' && crudType != 'C') {
                grid.setValue(rowKey, 'crud_type', 'U', false);
                grid.setValue(rowKey, 'icon', udtIcon, false);
            }

            // 일괄 등록 초기화
            grid.setValue(rowKey, 'validation_flag', null);
            grid.setValue(rowKey, 'validation', null);
            // 브라우저 렌더링이 끝나는 정확한 타이밍에 실행
            requestAnimationFrame(() => {
                grid.removeCellClassName(rowKey, 'validation', 'tui-grid-cell-darkred');
            });
        }
    });


    /********************************************
     * 다중 데이터 붙여넣기시 공통팝업 autoSet
     * rowKey 기준으로 그룹핑 후 행 단위 순차 처리
     ********************************************/
    grid.on('afterChange', (ev) => {
        if(ev.origin == 'paste') {
            // 붙여넣기된 행 자동 체크 (editingFinish의 자동 체크와 동일한 동작)
            const pastedRowKeys = [...new Set(ev.changes.map(change => change.rowKey))];
            pastedRowKeys.forEach(rowKey => {
                grid.check(rowKey);
            });

            // 모든 컬럼을 rowKey 기준으로 그룹핑 (CustomTextEditor + 일반 컬럼 통합 처리)
            const rowGroupMap = new Map();
            ev.changes.forEach(function(change) {
                if (!rowGroupMap.has(change.rowKey)) {
                    rowGroupMap.set(change.rowKey, []);
                }
                rowGroupMap.get(change.rowKey).push(change);
            });

            // 행 단위로 순차 처리
            const rowGroups = Array.from(rowGroupMap.values());

            // 다중 행 붙여넣기 플래그: AJAX 응답 시 팝업 억제 여부 판단에 사용
            if (rowGroups.length > 1) {
                window.__isPasteMultiRow = true;
            }

            function processNextRow(rowIdx) {
                if (rowIdx >= rowGroups.length) {
                    window.__isPasteMultiRow = false;
                    return;
                }

                const changes = rowGroups[rowIdx];

                // 한 행 내 컬럼들을 순차 처리
                function processNextCol(colIdx) {
                    if (colIdx >= changes.length) {
                        // 이 행의 모든 컬럼 처리 완료 → 다음 행으로
                        processNextRow(rowIdx + 1);
                        return;
                    }

                    const change = changes[colIdx];
                    const colDef = grid.getColumn(change.columnName);
                    const isAutoSet = colDef && colDef.editor && colDef.editor.type?.name === 'CustomTextEditor';

                    grid.setValue(change.rowKey, change.columnName, '', false);
                    grid.startEditing(change.rowKey, change.columnName, true);

                    // 그리드 셀 내부의 input만 대상으로 좁혀서 동명 컬럼 충돌 방지
                    const gridEl = document.getElementById(config.selector);
                    const inputElements = gridEl
                        ? gridEl.querySelectorAll(`input[data-column-name="${change.columnName}"]`)
                        : document.querySelectorAll(`input[data-column-name="${change.columnName}"]`);

                    if (isAutoSet && inputElements.length >= 1) {
                        // CustomTextEditor: change 이벤트 dispatch 후 autoset:done 대기
                        inputElements[0].value = change.value;
                        const event = new Event('change', { bubbles: true });
                        inputElements[0].dispatchEvent(event);

                        if (gfn_is_null(change.value)) {
                            // 빈 값: autoset:done이 발생하지 않으므로 바로 다음으로
                            processNextCol(colIdx + 1);
                        } else {
                            // change 이벤트 후 오토셋 완료를 기다렸다가 다음 컬럼으로
                            $(document).one('autoset:done', function() {
                                processNextCol(colIdx + 1);
                            });
                        }
                    } else {
                        // 일반 컬럼: finishEditing으로 editingFinish 발생 (crud_type 'U' 포함)
                        grid.finishEditing(change.rowKey, change.columnName, change.value);
                        processNextCol(colIdx + 1);
                    }
                }

                processNextCol(0);
            }

            processNextRow(0);
        }
    });

    // grid.on('afterChange', (ev) => {
    //     if (ev.origin !== 'paste') return;
    //
    //     const changes = ev.changes.slice();
    //     let i = 0;
    //
    //     const next = () => {
    //         const change = changes.shift();
    //         if (!change) return;
    //
    //         grid.setValue(change.rowKey, change.columnName, '', false);
    //
    //         // ✅ editor 생성이 안정되도록 한 틱 뒤에 startEditing
    //         setTimeout(() => {
    //             try {
    //                 grid.startEditing(change.rowKey, change.columnName, true);
    //
    //                 // ✅ editor가 실제로 올라온 다음 input 잡기 (셀 내부에서)
    //                 setTimeout(() => {
    //                     const cellEl = grid.getElement(change.rowKey, change.columnName);
    //                     const inputEl = cellEl ? cellEl.querySelector('input[data-column-name], input, textarea, select') : null;
    //
    //                     if (inputEl) {
    //                         inputEl.value = change.value;
    //                         inputEl.dispatchEvent(new Event('input', { bubbles: true }));
    //                         inputEl.dispatchEvent(new Event('change', { bubbles: true }));
    //                     }
    //
    //                     grid.finishEditing(change.rowKey, change.columnName, change.value);
    //
    //                     // ✅ 다음 row 처리
    //                     next();
    //                 }, 0);
    //             } catch (e) {
    //                 console.error('paste autoSet error', e);
    //                 // 실패해도 다음으로 진행
    //                 next();
    //             }
    //         }, 0);
    //         console.log('종료======================================================================');
    //     };
    //
    //     next();
    // });


    /********************************************
     * 그리드 데이터 DELETE로 삭제시
     ********************************************/
    grid.on('afterChange', (ev) => {
        if (ev.origin === 'delete') {
            // 일괄등록 검증 메시지 초기화
            if(ev.changes[0].prevValue !== ev.changes[0].value) {
                requestAnimationFrame(() => {
                    grid.setValue(ev.changes[0].rowKey, 'validation_flag', null);
                    grid.setValue(ev.changes[0].rowKey, 'validation', null);
                    grid.removeCellClassName(ev.changes[0].rowKey, 'validation', 'tui-grid-cell-darkred');
                });
            }

            // 공통팝업 오토셋 트리거
            ev.changes.forEach(function(change) {
                // 강제 에디팅 트리거
                grid.startEditing(change.rowKey, change.columnName, true);

                const inputElements = document.querySelectorAll(`input[data-column-name="${change.columnName}"]`);

                if (inputElements.length === 1) {
                    inputElements.forEach(inputElement => {
                        inputElement.value = ''; // delete 상황에서는 공백
                        const event = new Event('change', { bubbles: true });
                        inputElement.dispatchEvent(event); // 강제 change 이벤트 트리거
                    });
                } else {
                    grid.finishEditing(change.rowKey, change.columnName, '');
                }
            });
        }
    });


    /********************************************
     * 테이블 바깥 영역으로 포커스 아웃시
     * 셀 편집 이벤트 강제 종료 트리거
     ********************************************/
    $(document).on('blur', '#'+config.selector+' input', (ev) => {
        const editorWrap = $(ev.relatedTarget).parents('.tui-grid-layer-editing');
        if (editorWrap.length < 1) { // 셀 편집 영역 내의 버튼을 클릭한 경우 예외 처리
            const selectedRow = grid.getFocusedCell();
            grid.finishEditing(selectedRow.rowKey, selectedRow.columnName);
        }
    });
    $(document).on('blur', '#'+config.selector+' select', (ev) => {
        const editorWrap = $(ev.relatedTarget).parents('.tui-grid-layer-editing');
        if (editorWrap.length < 1) { // 셀 편집 영역 내의 버튼을 클릭한 경우 예외 처리
            const selectedRow = grid.getFocusedCell();
            grid.finishEditing(selectedRow.rowKey, selectedRow.columnName);
        }
    });
    $(document).on('click','.tui-select-box-item',function(){
        grid.blur();
    });

    /********************************************
     * customEditor change trigger -> 셀 편집 이벤트 강제 트리거
     * 셀 편집 이벤트 강제 종료 트리거
     ********************************************/
    $(document).on('change', 'input.tui-grid-content-text-custom', (ev) => {
        const rowKey = ev.target.dataset.rowKey;
        const colName = ev.target.dataset.columnName;
        grid.finishEditing(rowKey, colName);
    });

    /********************************************
     * Ctrl+Click → 비연속 다중 행 선택 + Ctrl+C 복사
     ********************************************/
    if (!document.getElementById('tui-grid-ctrl-selected-style')) {
        const style = document.createElement('style');
        style.id = 'tui-grid-ctrl-selected-style';
        style.textContent = '';
        document.head.appendChild(style);
    }

    const ctrlSelectedKeys = new Set();
    const ctrlSelectedCells = new Map(); // rowKey → Set<columnName>
    const CTRL_CLS = 'tui-grid-ctrl-selected';
    let normalClickAnchor = null; // { rowKey, columnName } — 사용자가 명시적으로 일반 클릭한 셀

    // shift-click overlay와 동일한 색상: 행 헤더 solid teal, 데이터 열 barely-visible teal
    function updateCtrlStyle() {
        const styleEl = document.getElementById('tui-grid-ctrl-selected-style');
        if (!styleEl) return;
        if (ctrlSelectedCells.size === 0) {
            styleEl.textContent = '';
            return;
        }
        // 항상 셀 단위 CSS (행 헤더 강조 + 클릭한 셀만 배경)
        let css = `td.tui-grid-ctrl-selected.tui-grid-cell-row-header { background-color: #95d3d3 !important; }`;
        ctrlSelectedCells.forEach(function(cols, rk) {
            cols.forEach(function(colName) {
                css += `td[data-row-key="${rk}"][data-column-name="${colName}"] { background-color: rgba(0,154,147,0.04) !important; }`;
            });
        });
        // ctrl-click된 모든 열의 헤더 강조
        const allCtrlCols = new Set();
        ctrlSelectedCells.forEach(function(cols) {
            cols.forEach(function(colName) { allCtrlCols.add(colName); });
        });
        allCtrlCols.forEach(function(colName) {
            css += `th[data-column-name="${colName}"].tui-grid-cell-header { background-color: #95d3d3 !important; }`;
        });
        styleEl.textContent = css;
    }

    function clearCtrlSelection() {
        ctrlSelectedKeys.forEach(rk => grid.removeRowClassName(rk, CTRL_CLS));
        ctrlSelectedKeys.clear();
        ctrlSelectedCells.clear();
        normalClickAnchor = null;
        updateCtrlStyle();
    }

    const gridEl = document.getElementById(config.selector);
    if (gridEl) {
        // capture phase — ctrl+click 시 포커스/선택 상태 캡처 후 tui-grid mousedown 차단
        // (차단하지 않으면 tui-grid가 shift-selection을 지우고 포커스를 이동시킴)
        gridEl.addEventListener('mousedown', function(ev) {
            if ((ev.ctrlKey || ev.metaKey) && ev.target.closest('td[data-row-key]')) {
                ev.stopPropagation();
            }
        }, true);

        gridEl.addEventListener('click', function(ev) {
            if (ev.ctrlKey || ev.metaKey) {
                const td = ev.target.closest('td[data-row-key]');
                if (!td) return;
                const rowKey = Number(td.getAttribute('data-row-key'));
                if (isNaN(rowKey)) return;

                const clickedColumn = td.getAttribute('data-column-name');

                // 항상 셀 단위 토글 (shift 여부 무관)
                if (!ctrlSelectedCells.has(rowKey)) {
                    ctrlSelectedKeys.add(rowKey);
                    grid.addRowClassName(rowKey, CTRL_CLS);
                    ctrlSelectedCells.set(rowKey, new Set([clickedColumn]));
                } else {
                    const cols = ctrlSelectedCells.get(rowKey);
                    if (cols.has(clickedColumn)) {
                        cols.delete(clickedColumn);
                        if (cols.size === 0) {
                            ctrlSelectedKeys.delete(rowKey);
                            grid.removeRowClassName(rowKey, CTRL_CLS);
                            ctrlSelectedCells.delete(rowKey);
                        }
                    } else {
                        cols.add(clickedColumn);
                    }
                }

                updateCtrlStyle();
            } else {
                // 일반/shift 클릭 → ctrl 선택 초기화 후 앵커 기록
                if (ctrlSelectedKeys.size > 0) clearCtrlSelection(); // clearCtrlSelection이 normalClickAnchor도 초기화함
                const anchorTd = ev.target.closest('td[data-row-key]');
                if (anchorTd) {
                    normalClickAnchor = {
                        rowKey: Number(anchorTd.getAttribute('data-row-key')),
                        columnName: anchorTd.getAttribute('data-column-name')
                    };
                }
            }
        });
    }

    // 그리드 밖 클릭 → ctrl 선택 초기화
    document.addEventListener('mousedown', function(ev) {
        if (ev.ctrlKey || ev.metaKey) return;
        if (ctrlSelectedKeys.size === 0) return;
        const el = document.getElementById(config.selector);
        if (el && !el.contains(ev.target)) clearCtrlSelection();
    });

    // Ctrl+C → shift 선택 행 + ctrl 선택 행 모두 복사 (중복 제외)
    document.addEventListener('keydown', function(ev) {
        if (ctrlSelectedKeys.size === 0) return;
        if ((!ev.ctrlKey && !ev.metaKey) || ev.key.toLowerCase() !== 'c') return;

        ev.preventDefault();
        ev.stopImmediatePropagation();

        const lines = [];
        const copiedKeys = new Set();
        const selRange = grid.getSelectionRange();

        // tui-grid API로 컬럼 순서 확보 (DOM 파싱 대신 직접 사용)
        const allColumns = grid.getColumns();

        // 1. tui-grid shift-selection(또는 일반 클릭 포커스) 행 — selRange의 실제 열로 복사
        //    ctrl-click된 행은 section 2에서 처리하므로 여기서 skip
        if (selRange) {
            const minRow = Math.min(selRange.start[0], selRange.end[0]);
            const maxRow = Math.max(selRange.start[0], selRange.end[0]);
            const minColIdx = Math.min(selRange.start[1], selRange.end[1]);
            const maxColIdx = Math.max(selRange.start[1], selRange.end[1]);
            const rangeColNames = allColumns.slice(minColIdx, maxColIdx + 1).map(function(col) {
                return col.name;
            });
            for (let i = minRow; i <= maxRow; i++) {
                const row = grid.getRowAt(i);
                if (!row) continue;
                const rk = row.rowKey;
                if (copiedKeys.has(rk)) continue;
                if (ctrlSelectedCells.has(rk)) continue; // ctrl-click된 행은 section 2에서 처리
                copiedKeys.add(rk);
                if (rangeColNames.length > 1) {
                    lines.push(rangeColNames.map(function(col) {
                        const v = row[col];
                        return (v === null || v === undefined) ? '' : String(v);
                    }).join('\t'));
                } else {
                    const v = rangeColNames.length === 1 ? row[rangeColNames[0]] : '';
                    lines.push((v === null || v === undefined) ? '' : String(v));
                }
            }
        } else {
            // selRange가 null이면 사용자가 명시적으로 일반 클릭한 앵커 셀을 확인
            // getFocusedCell()은 tui-grid 자동 포커스(row 0)도 반환하므로 사용 금지
            if (normalClickAnchor && !ctrlSelectedCells.has(normalClickAnchor.rowKey)) {
                var rk = normalClickAnchor.rowKey;
                var row = grid.getRow(rk);
                if (row && !copiedKeys.has(rk)) {
                    copiedKeys.add(rk);
                    var v = row[normalClickAnchor.columnName];
                    lines.push((v === null || v === undefined) ? '' : String(v));
                }
            }
        }

        // 2. ctrl-selected 셀 (shift 선택과 중복 제외, 행당 한 줄 tab-separated)
        const gridColOrder = allColumns.map(function(col) { return col.name; });

        ctrlSelectedKeys.forEach(function(rk) {
            if (copiedKeys.has(rk)) return;
            const row = grid.getRow(rk);
            if (!row) return;
            const selectedCols = ctrlSelectedCells.get(rk);
            if (!selectedCols) return; // 앵커만 있고 ctrlSelectedCells 미등록인 경우 방어
            const orderedCols = gridColOrder.filter(function(col) { return selectedCols.has(col); });
            const vals = orderedCols.map(function(col) {
                const v = row[col];
                return (v === null || v === undefined) ? '' : String(v);
            });
            lines.push(vals.join('\t'));
        });

        const text = lines.join('\n');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
    }, true);

}

/********************************************
 * 그리드 체크 이벤트 버스
 ********************************************/
function enable_custom_grid_events(grid) {
    if (grid.__custom_events_enabled) return;

    const originalOn  = grid.on.bind(grid);
    const originalOff = typeof grid.off === 'function' ? grid.off.bind(grid) : null;

    grid.__custom_handlers = {}; // { eventName: [fn, fn...] }

    grid.on = function (eventName, handler) {
        // 이벤트 가로채기
        if (eventName === 'check_change') {
            (grid.__custom_handlers[eventName] ||= []).push(handler);
            return;
        }
        return originalOn(eventName, handler);
    };

    // off도 있으면 같이 지원(선택)
    if (originalOff) {
        grid.off = function (eventName, handler) {
            if (eventName === 'check_change') {
                if (!handler) {
                    delete grid.__custom_handlers[eventName];
                    return;
                }
                const list = grid.__custom_handlers[eventName] || [];
                grid.__custom_handlers[eventName] = list.filter(fn => fn !== handler);
                return;
            }
            return originalOff(eventName, handler);
        };
    }

    grid.__emitCustom = function (eventName, payload) {
        const list = grid.__custom_handlers?.[eventName] || [];
        list.forEach(fn => {
            try { fn(payload); } catch (e) { console.error(e); }
        });
    };

    grid.__custom_events_enabled = true;
}

/********************************************
 * 그리드 체크 이벤트 버스 세팅
 ********************************************/
function bind_check_change_event(grid, config) {
    enable_custom_grid_events(grid);

    function fire(type, ev) {
        grid.__emitCustom('check_change', {
            type,                               // 'check' | 'uncheck' | 'checkAll' | 'uncheckAll'
            selector: config.selector,
            rowKey: ev?.rowKey ?? null,
            checkedRowKeys: grid.getCheckedRowKeys(),
            checkedRows: grid.getCheckedRows(),
            originalEvent: ev ?? null,
        });
    }

    grid.on('check',      (ev) => fire('check', ev));
    grid.on('uncheck',    (ev) => fire('uncheck', ev));
    grid.on('checkAll',   (ev) => fire('checkAll', ev));
    grid.on('uncheckAll', (ev) => fire('uncheckAll', ev));
}

function setup_resizing(grid, config) {
    /********************************************
     * 스플리터 조절 시 body_height 재적용
     ********************************************/
    const splitterResizeEvent = 'igsplitterresizeended igsplitterexpanded igsplittercollapsed';

    $('.splitter-hrz').on(splitterResizeEvent, function (evt, ui) {
        const children = $(this).find('.table-card-body');
        if (children.length && children.hasClass(config.wrap_selector.replace('.',''))) {
            const body_height = calculate_body_height(config);
            const complex_height = config.complex_columns ? 30 : 0;
            grid.setBodyHeight($(config.wrap_selector).innerHeight() - body_height - complex_height);
        }
    });
    $('.splitter-vtc').on(splitterResizeEvent, function (evt, ui) {
        const children = $(this).find('.table-card-body');
        const children2 = $(this).find('.right-grid');
        var newWidth = children.width(); // 컨테이너의 새로운 너비를 가져옵니다.
        var rightWidth = children2.width(); // 스플리터 기준 오른쪽 그리드 width를 가져옴
        if (children.length && children.hasClass(config.wrap_selector.replace('.',''))) { // 해당 그리드에만 적용
            grid.setWidth(newWidth); // TUI 그리드의 너비를 새로운 너비로 설정합니다.
        }

        if (children2.length && children2.hasClass(config.wrap_selector.replace('.',''))) {
            grid.setWidth(rightWidth);
        }
    });


    $('.btn-toggle').on('click', function () {
        requestAnimationFrame(() => {
            const body_height = calculate_body_height(config);
            const complex_height = config.complex_columns ? 30 : 0;

            grid.refreshLayout();
            grid.setBodyHeight(
                config.wrap_selector.includes('PopGrid')
                    ? 435
                    : $(config.wrap_selector).innerHeight() - body_height - complex_height
            );
        });
    });
}

function handle_visibility(grid, config) {
    /********************************************
     * 탭이나 모달로 숨겨진 그리드 크기 자동 조절
     ********************************************/
    if (!$('#'+config.selector).is(':visible')) {
        const tabId = $('#' + config.selector).parents('.tab-pane').attr('aria-labelledby');
        const tabId2 = $(config.wrap_selector).attr('grid-tab');

        $(`#${tabId}`).on('shown.bs.tab', function (e) {
            let body_height = calculate_body_height(config);
            let complex_height = config.complex_columns ? 30 : 0;
            grid.refreshLayout();
            grid.setBodyHeight($(config.wrap_selector).innerHeight() - body_height - complex_height);
        });

        $(`#${tabId2}`).on('click', function (e) {
            grid.refreshLayout();
            grid.setBodyHeight($(config.wrap_selector).innerHeight() - 141);
        });
        // todo: 모달 그리드 작업시 테스트 후 주석 풀기 (그리드 행 높이 조절되는지 확인 필요)
        // const modalId = $('#' + final_config.selector).parents('.modal').attr('id');
        // $(`#${modalId}`).one('shown.bs.modal', function (e) {
        //     grid.refreshLayout();
        //     grid.setBodyHeight($(final_config.wrap_selector).height() - 150);
        // });
    }
}


/********************************************
 * Grid 컬럼 이동 및 사이즈 변경시
 * 개인 그리드 정보 삭제
 ********************************************/
function remove_persistence() {
    const menu_list = [
        // 'grid_settings_shipping_carrier_management'
    ];

    menu_list.forEach(prefix => {
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            if (key && key.startsWith(prefix)) {
                localStorage.removeItem(key);
            }
        }
    });
}


function get_current_grid_user_id() {
    const candidates = [
        window?.Laravel?.user?.id,
        window?.laravel?.user?.id,
        window?.AuthUser?.id,
        window?.authUser?.id,
        window?.user?.id,
        window?.USER_ID,
        window?.LOGIN_USER_ID,
        document.querySelector('meta[name="user-id"]')?.content,
        document.querySelector('meta[name="auth-user-id"]')?.content,
        document.querySelector('meta[name="login-user-id"]')?.content,
        document.body?.dataset?.userId,
        document.querySelector('input[name="user_id"]')?.value,
    ];

    const matched = candidates.find(value => value !== undefined && value !== null && String(value).trim() !== '');
    return matched ?? null;
}

function get_grid_column_order_store() {
    if (!window.__gridDbColumnOrderState) {
        window.__gridDbColumnOrderState = {};
    }

    return window.__gridDbColumnOrderState;
}

function get_grid_column_order_identity(config = {}) {
    const pagePath = window.location.pathname || '';
    const segment = pagePath.split('/').pop() || 'No Segment';
    const selector = config.selector || '';
    const headerFlag = config.header_flag || null;
    const gridKey = headerFlag ? `${selector}_${headerFlag}` : selector;

    return {
        user_id: get_current_grid_user_id(),
        page_url: window.location.href,
        page_path: pagePath,
        segment,
        selector,
        header_flag: headerFlag,
        grid_key: gridKey,
    };
}

function get_grid_column_order_state_key(config = {}) {
    const identity = get_grid_column_order_identity(config);
    return [identity.user_id || 'anonymous', identity.page_path || '', identity.grid_key || ''].join('::');
}

function get_grid_column_order_local_storage_key(config = {}) {
    const identity = get_grid_column_order_identity(config);
    if (!identity.segment || identity.segment === 'No Segment' || !identity.selector) return null;

    let storageKey = 'grid_settings_' + identity.segment + '_' + identity.selector;
    if (identity.header_flag) {
        storageKey += '_' + identity.header_flag;
    }

    return storageKey;
}

function read_grid_column_order_from_local_storage(config = {}) {
    const storageKey = get_grid_column_order_local_storage_key(config);
    if (!storageKey || typeof localStorage === 'undefined') return null;

    const raw = localStorage.getItem(storageKey);
    if (!raw) return null;

    try {
        const parsed = JSON.parse(raw);
        let orderNames = [];
        let columnWidths = {};

        if (Array.isArray(parsed)) {
            orderNames = normalize_grid_column_order_names(parsed);
            columnWidths = extract_grid_column_widths(parsed);
        } else if (parsed && typeof parsed === 'object') {
            if (Array.isArray(parsed.columns)) {
                orderNames = normalize_grid_column_order_names(parsed.columns);
                columnWidths = extract_grid_column_widths(parsed.columns);
            } else if (Array.isArray(parsed.header)) {
                orderNames = normalize_grid_column_order_names(parsed.header);
                columnWidths = extract_grid_column_widths(parsed.header);
            } else {
                orderNames = normalize_grid_column_order_names(parsed.column_order || parsed.order || parsed.columns);
                columnWidths = normalize_grid_column_widths(parsed.column_widths || parsed.widths || parsed.columnWidths);
            }
        }

        if (orderNames.length === 0 && Object.keys(columnWidths).length === 0) {
            return null;
        }

        return {
            storageKey,
            orderNames,
            columnWidths,
            raw: parsed,
        };
    } catch (error) {
        console.warn('[grid db order] localStorage parse failed:', error);
        return null;
    }
}

function sync_request_grid_column_order_api(type, payload = {}) {
    const apiConfig = get_grid_column_order_api_config();
    if (apiConfig.enabled === false) return null;

    const identity = get_grid_column_order_identity(payload);
    const method = String(type === 'load' ? apiConfig.loadMethod : apiConfig.saveMethod || 'GET').toUpperCase();
    let url = resolve_grid_column_order_url(type, identity, payload);

    if (!url) return null;

    const xhr = new XMLHttpRequest();

    if (method === 'GET') {
        const queryString = build_grid_column_order_query(payload);
        if (queryString) {
            url += (url.includes('?') ? '&' : '?') + queryString;
        }
        xhr.open(method, url, false);
        if (apiConfig.credentials === 'include') {
            xhr.withCredentials = true;
        }
        const headers = get_grid_column_order_headers(false);
        Object.entries(headers).forEach(([key, value]) => xhr.setRequestHeader(key, value));
        xhr.send();
    } else {
        xhr.open(method, url, false);
        if (apiConfig.credentials === 'include') {
            xhr.withCredentials = true;
        }
        const headers = get_grid_column_order_headers(true);
        Object.entries(headers).forEach(([key, value]) => xhr.setRequestHeader(key, value));
        xhr.send(JSON.stringify(payload));
    }

    if (xhr.status < 200 || xhr.status >= 300) {
        throw new Error(`[grid-column-order:${type}:sync] HTTP ${xhr.status}`);
    }

    const contentType = xhr.getResponseHeader('content-type') || '';
    const responseText = xhr.responseText || '';

    if (contentType.includes('application/json')) {
        return responseText ? JSON.parse(responseText) : null;
    }

    try {
        return responseText ? JSON.parse(responseText) : null;
    } catch (e) {
        return responseText;
    }
}

function preload_grid_column_order_runtime_state(config = {}) {
    const state = get_grid_column_order_runtime_state(config);

    if (state.preloadChecked) {
        return state;
    }

    state.preloadChecked = true;

    try {
        const identity = get_grid_column_order_identity(config);
        const payload = {
            user_id: identity.user_id,
            page_url: identity.page_url,
            page_path: identity.page_path,
            segment: identity.segment,
            selector: identity.selector,
            header_flag: identity.header_flag,
            grid_key: identity.grid_key,
        };

        const response = sync_request_grid_column_order_api('load', payload);
        const orderNames = normalize_grid_column_order_response(response);
        const columnWidths = normalize_grid_column_widths_response(response);

        if (orderNames.length > 0) {
            const signature = create_grid_column_layout_signature(orderNames, columnWidths);
            state.bootstrapSource = 'db';
            state.ignoreLocalStorage = true;
            state.needsDbMigration = false;
            state.lastLoadedSignature = signature;
            state.lastSavedSignature = signature;
            state.lastLoadedOrder = orderNames.slice();
            state.lastSavedOrder = orderNames.slice();
            state.lastLoadedWidths = columnWidths;
            state.lastSavedWidths = columnWidths;
            return state;
        }
    } catch (error) {
        console.warn('[grid db order] preload failed:', error);
    }

    bootstrap_grid_column_order_runtime_state(config);
    return state;
}

function bootstrap_grid_column_order_runtime_state(config = {}) {
    const state = get_grid_column_order_runtime_state(config);

    if (state.ignoreLocalStorage) {
        return state;
    }

    if (state.localStorageBootstrapChecked) {
        return state;
    }

    state.localStorageBootstrapChecked = true;

    const legacyState = read_grid_column_order_from_local_storage(config);
    if (!legacyState || legacyState.orderNames.length === 0) {
        return state;
    }

    const hasLoadedOrder = Array.isArray(state.lastLoadedOrder) && state.lastLoadedOrder.length > 0;
    const hasSavedOrder = Array.isArray(state.lastSavedOrder) && state.lastSavedOrder.length > 0;
    const hasLoadedWidths = Object.keys(state.lastLoadedWidths || {}).length > 0;
    const hasSavedWidths = Object.keys(state.lastSavedWidths || {}).length > 0;

    state.bootstrapSource = 'localStorage';
    state.bootstrapStorageKey = legacyState.storageKey;
    state.bootstrapOrder = legacyState.orderNames.slice();
    state.bootstrapWidths = Object.assign({}, legacyState.columnWidths || {});
    state.needsDbMigration = true;

    if (!hasLoadedOrder && !hasSavedOrder) {
        state.lastLoadedOrder = legacyState.orderNames.slice();
        state.lastSavedOrder = legacyState.orderNames.slice();
    }

    if (!hasLoadedWidths && !hasSavedWidths) {
        state.lastLoadedWidths = Object.assign({}, legacyState.columnWidths || {});
        state.lastSavedWidths = Object.assign({}, legacyState.columnWidths || {});
    }

    return state;
}

function get_grid_column_order_api_config() {
    const defaults = {
        enabled: true,
        loadUrl: './grid-column-order',
        saveUrl: './grid-column-order',
        loadMethod: 'GET',
        saveMethod: 'POST',
        credentials: 'same-origin',
    };

    const globalConfig = window.GRID_COLUMN_ORDER_API || window.gridColumnOrderApi || {};
    return Object.assign({}, defaults, globalConfig);
}

function resolve_grid_column_order_url(type, identity, payload) {
    const apiConfig = get_grid_column_order_api_config();
    const resolver = type === 'load' ? apiConfig.loadUrl : apiConfig.saveUrl;

    if (typeof resolver === 'function') {
        return resolver(identity, payload, apiConfig);
    }

    return resolver;
}

function get_grid_column_order_headers(withJsonBody = false) {
    const headers = {
        Accept: 'application/json'
    };

    if (withJsonBody) {
        headers['Content-Type'] = 'application/json';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    return headers;
}

function build_grid_column_order_query(payload = {}) {
    const searchParams = new URLSearchParams();

    Object.entries(payload).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return;

        if (Array.isArray(value)) {
            value.forEach(item => {
                if (item !== undefined && item !== null && item !== '') {
                    searchParams.append(`${key}[]`, item);
                }
            });
            return;
        }

        searchParams.append(key, value);
    });

    return searchParams.toString();
}

async function request_grid_column_order_api(type, payload = {}) {
    const apiConfig = get_grid_column_order_api_config();
    if (apiConfig.enabled === false) return null;

    const identity = get_grid_column_order_identity(payload);
    const method = String(type === 'load' ? apiConfig.loadMethod : apiConfig.saveMethod || 'GET').toUpperCase();
    let url = resolve_grid_column_order_url(type, identity, payload);

    if (!url) return null;

    const options = {
        method,
        credentials: apiConfig.credentials || 'same-origin',
        headers: get_grid_column_order_headers(method !== 'GET'),
    };

    if (method === 'GET') {
        const queryString = build_grid_column_order_query(payload);
        if (queryString) {
            url += (url.includes('?') ? '&' : '?') + queryString;
        }
    } else {
        options.body = JSON.stringify(payload);
    }

    const response = await fetch(url, options);
    if (!response.ok) {
        throw new Error(`[grid-column-order:${type}] HTTP ${response.status}`);
    }

    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
        return await response.json();
    }

    const text = await response.text();

    try {
        return JSON.parse(text);
    } catch (e) {
        return text;
    }
}

function normalize_grid_column_order_response(response) {
    if (!response) return [];

    if (Array.isArray(response)) {
        return response
            .map(item => typeof item === 'string' ? item : item?.name)
            .filter(Boolean);
    }

    if (typeof response === 'string') {
        const trimmed = response.trim();

        if (!trimmed) return [];

        try {
            const parsed = JSON.parse(trimmed);
            return normalize_grid_column_order_response(parsed);
        } catch (e) {
            return trimmed.split(',').map(item => item.trim()).filter(Boolean);
        }
    }

    if (response.data !== undefined) {
        const nested = normalize_grid_column_order_response(response.data);
        if (nested.length > 0) return nested;
    }

    if (response.column_order !== undefined) {
        const nested = normalize_grid_column_order_response(response.column_order);
        if (nested.length > 0) return nested;
    }

    if (response.order !== undefined) {
        const nested = normalize_grid_column_order_response(response.order);
        if (nested.length > 0) return nested;
    }

    if (response.columns !== undefined) {
        const nested = normalize_grid_column_order_response(response.columns);
        if (nested.length > 0) return nested;
    }

    return [];
}

function extract_grid_column_order_names(grid) {
    if (!grid || typeof grid.getColumns !== 'function') return [];

    return grid.getColumns()
        .map(column => column && column.name)
        .filter(Boolean);
}

function normalize_grid_column_order_names(orderNames = []) {
    return Array.isArray(orderNames)
        ? orderNames
            .map(item => typeof item === 'string' ? item : item?.name)
            .filter(Boolean)
        : [];
}

function normalize_grid_column_widths(widths = null) {
    if (!widths) return {};

    if (typeof widths === 'string') {
        const trimmed = widths.trim();
        if (!trimmed) return {};

        try {
            return normalize_grid_column_widths(JSON.parse(trimmed));
        } catch (e) {
            return {};
        }
    }

    if (Array.isArray(widths)) {
        return widths.reduce((acc, item) => {
            if (!item) return acc;
            const name = typeof item === 'string' ? item : item.name;
            const width = Number(typeof item === 'string' ? null : (item.width ?? item.baseWidth ?? item.minWidth));
            if (name && Number.isFinite(width) && width > 0) {
                acc[name] = width;
            }
            return acc;
        }, {});
    }

    if (typeof widths === 'object') {
        return Object.entries(widths).reduce((acc, [name, value]) => {
            const width = Number(value);
            if (name && Number.isFinite(width) && width > 0) {
                acc[name] = width;
            }
            return acc;
        }, {});
    }

    return {};
}

function normalize_grid_column_widths_response(response) {
    if (!response) return {};

    if (response.data !== undefined) {
        const nested = normalize_grid_column_widths_response(response.data);
        if (Object.keys(nested).length > 0) return nested;
    }

    if (response.column_widths !== undefined) {
        const nested = normalize_grid_column_widths(response.column_widths);
        if (Object.keys(nested).length > 0) return nested;
    }

    if (response.widths !== undefined) {
        const nested = normalize_grid_column_widths(response.widths);
        if (Object.keys(nested).length > 0) return nested;
    }

    if (response.columnWidths !== undefined) {
        const nested = normalize_grid_column_widths(response.columnWidths);
        if (Object.keys(nested).length > 0) return nested;
    }

    return {};
}

function extract_grid_column_widths(source) {
    const columns = Array.isArray(source)
        ? source
        : (source && typeof source.getColumns === 'function' ? source.getColumns() : []);

    return (Array.isArray(columns) ? columns : []).reduce((acc, column) => {
        if (!column || !column.name) return acc;
        const width = Number(column.width ?? column.baseWidth ?? column.minWidth);
        if (Number.isFinite(width) && width > 0) {
            acc[column.name] = width;
        }
        return acc;
    }, {});
}

function apply_grid_column_widths_to_definitions(columns = [], widthMap = {}) {
    const normalizedWidths = normalize_grid_column_widths(widthMap);
    if (!Array.isArray(columns) || columns.length === 0 || Object.keys(normalizedWidths).length === 0) {
        return Array.isArray(columns) ? columns.slice() : [];
    }

    return columns.map(column => {
        if (!column || !column.name) return column;
        const width = normalizedWidths[column.name];
        if (!Number.isFinite(width) || width <= 0) return column;
        return {
            ...column,
            width,
            baseWidth: width,
        };
    });
}

function create_grid_column_layout_signature(orderNames = [], widthMap = {}) {
    const normalizedOrder = normalize_grid_column_order_names(orderNames);
    const normalizedWidths = normalize_grid_column_widths(widthMap);

    return normalizedOrder
        .map(name => `${name}:${normalizedWidths[name] ?? ''}`)
        .join('|');
}

function get_grid_column_order_runtime_state(config = {}) {
    const store = get_grid_column_order_store();
    const stateKey = get_grid_column_order_state_key(config);
    return store[stateKey] || (store[stateKey] = {});
}

function reorder_grid_column_definitions(columns = [], orderNames = []) {
    const normalizedOrder = normalize_grid_column_order_names(orderNames);
    const originalColumns = Array.isArray(columns) ? columns.slice() : [];

    if (originalColumns.length === 0 || normalizedOrder.length === 0) {
        return originalColumns;
    }

    const namedColumnMap = new Map();
    const unnamedColumns = [];

    originalColumns.forEach(column => {
        if (column && column.name) {
            namedColumnMap.set(column.name, column);
        } else if (column) {
            unnamedColumns.push(column);
        }
    });

    const orderedColumns = [];

    normalizedOrder.forEach(columnName => {
        if (namedColumnMap.has(columnName)) {
            orderedColumns.push(namedColumnMap.get(columnName));
            namedColumnMap.delete(columnName);
        }
    });

    originalColumns.forEach(column => {
        if (!column) return;

        if (!column.name) {
            if (unnamedColumns.length > 0) {
                orderedColumns.push(unnamedColumns.shift());
            }
            return;
        }

        if (namedColumnMap.has(column.name)) {
            orderedColumns.push(namedColumnMap.get(column.name));
            namedColumnMap.delete(column.name);
        }
    });

    return orderedColumns;
}

function wait_for_grid_columns_ready(grid, maxRetry = 30, interval = 50) {
    return new Promise(resolve => {
        let retry = 0;

        const check = () => {
            const columnNames = extract_grid_column_order_names(grid);
            if (columnNames.length > 0 || retry >= maxRetry) {
                resolve(columnNames);
                return;
            }

            retry += 1;
            setTimeout(check, interval);
        };

        check();
    });
}

function apply_grid_column_order_to_grid(grid, orderNames, config = {}, widthMap = {}) {
    const desiredOrder = normalize_grid_column_order_names(orderNames);
    const desiredWidths = normalize_grid_column_widths(widthMap);

    if (!grid || desiredOrder.length === 0) return false;

    const state = get_grid_column_order_runtime_state(config);
    const currentColumns = typeof grid.getColumns === 'function' ? grid.getColumns() : [];
    let reorderedColumns = reorder_grid_column_definitions(currentColumns, desiredOrder);
    reorderedColumns = apply_grid_column_widths_to_definitions(reorderedColumns, desiredWidths);

    const currentSignature = create_grid_column_layout_signature(
        extract_grid_column_order_names(grid),
        extract_grid_column_widths(currentColumns)
    );
    const nextSignature = create_grid_column_layout_signature(
        reorderedColumns.map(column => column && column.name).filter(Boolean),
        extract_grid_column_widths(reorderedColumns)
    );

    if (!nextSignature || currentSignature === nextSignature) {
        state.lastAppliedSignature = nextSignature;
        state.lastLoadedOrder = desiredOrder.slice();
        state.lastLoadedWidths = desiredWidths;
        return false;
    }

    try {
        if (typeof grid.setColumns === 'function') {
            grid.setColumns(reorderedColumns);
        } else {
            desiredOrder.forEach((columnName, targetIndex) => {
                const currentOrder = extract_grid_column_order_names(grid);
                const currentIndex = currentOrder.indexOf(columnName);
                if (currentIndex === -1 || currentIndex === targetIndex) return;
                grid.moveColumn(columnName, targetIndex);
            });
        }

        if (typeof grid.refreshLayout === 'function') {
            grid.refreshLayout();
        }

        state.lastAppliedSignature = nextSignature;
        state.lastLoadedOrder = desiredOrder.slice();
        state.lastLoadedWidths = desiredWidths;

        requestAnimationFrame(() => {
            const reorderApi = window['__colReorder_' + (config.selector || '')];
            if (reorderApi && typeof reorderApi.rebind === 'function') {
                reorderApi.rebind();
            }
        });

        return true;
    } catch (e) {
        console.warn('[grid db order] apply error:', e);
        return false;
    }
}

async function sync_grid_column_order_to_db(config = {}, grid, updatedColumns = null) {
    if (config.db_column_order === false) return null;

    const orderNames = Array.isArray(updatedColumns)
        ? updatedColumns.map(column => typeof column === 'string' ? column : column?.name).filter(Boolean)
        : extract_grid_column_order_names(grid);
    const columnWidths = Array.isArray(updatedColumns)
        ? extract_grid_column_widths(updatedColumns)
        : extract_grid_column_widths(grid);

    if (orderNames.length === 0) return null;

    const state = get_grid_column_order_runtime_state(config);
    const signature = create_grid_column_layout_signature(orderNames, columnWidths);

    if (state.lastSavedSignature === signature) {
        return state.lastSaveResponse || null;
    }

    const identity = get_grid_column_order_identity(config);
    const payload = {
        user_id: identity.user_id,
        page_url: identity.page_url,
        page_path: identity.page_path,
        segment: identity.segment,
        selector: identity.selector,
        header_flag: identity.header_flag,
        grid_key: identity.grid_key,
        column_order: orderNames,
        column_widths: columnWidths,
        column_count: orderNames.length,
    };

    const response = await request_grid_column_order_api('save', payload);

    state.lastSavedSignature = signature;
    state.lastLoadedSignature = signature;
    state.lastSavedOrder = orderNames.slice();
    state.lastSavedWidths = columnWidths;
    state.lastSaveResponse = response;

    return response;
}

async function load_grid_column_order_from_db(grid, config = {}) {
    if (!grid || config.db_column_order === false) return [];

    const state = get_grid_column_order_runtime_state(config);

    if (state.loadingPromise) {
        return state.loadingPromise;
    }

    state.loadingPromise = (async () => {
        const identity = get_grid_column_order_identity(config);
        const payload = {
            user_id: identity.user_id,
            page_url: identity.page_url,
            page_path: identity.page_path,
            segment: identity.segment,
            selector: identity.selector,
            header_flag: identity.header_flag,
            grid_key: identity.grid_key,
        };

        const response = await request_grid_column_order_api('load', payload);
        const orderNames = normalize_grid_column_order_response(response);
        const columnWidths = normalize_grid_column_widths_response(response);

        if (orderNames.length === 0) {
            const bootstrapOrder = normalize_grid_column_order_names(state.bootstrapOrder || state.lastLoadedOrder || state.lastSavedOrder || []);
            const bootstrapWidths = normalize_grid_column_widths(state.bootstrapWidths || state.lastLoadedWidths || state.lastSavedWidths || {});

            if (bootstrapOrder.length > 0) {
                const signature = create_grid_column_layout_signature(bootstrapOrder, bootstrapWidths);
                state.lastLoadedSignature = signature;
                state.lastLoadedOrder = bootstrapOrder.slice();
                state.lastSavedOrder = bootstrapOrder.slice();
                state.lastLoadedWidths = bootstrapWidths;
                state.lastSavedWidths = bootstrapWidths;

                if (state.needsDbMigration && !state.dbMigrationPromise) {
                    state.dbMigrationPromise = sync_grid_column_order_to_db(config, grid)
                        .then(result => {
                            state.needsDbMigration = false;
                            return result;
                        })
                        .catch(error => {
                            console.warn('[grid db order] localStorage migration failed:', error);
                            throw error;
                        })
                        .finally(() => {
                            state.dbMigrationPromise = null;
                        });
                }

                return bootstrapOrder;
            }

            state.lastLoadedWidths = columnWidths;
            return [];
        }

        await wait_for_grid_columns_ready(grid);
        apply_grid_column_order_to_grid(grid, orderNames, config, columnWidths);

        const signature = create_grid_column_layout_signature(orderNames, columnWidths);
        state.lastLoadedSignature = signature;
        state.lastSavedSignature = signature;
        state.lastLoadedOrder = orderNames.slice();
        state.lastSavedOrder = orderNames.slice();
        state.lastLoadedWidths = columnWidths;
        state.lastSavedWidths = columnWidths;
        state.needsDbMigration = false;

        return orderNames;
    })().catch(error => {
        console.warn('[grid db order] load failed:', error);
        return [];
    }).finally(() => {
        state.loadingPromise = null;
    });

    return state.loadingPromise;
}


/********************************************
 * Grid 컬럼 이동 및 사이즈 변경시
 * 개인 그리드 정보 생성
 ********************************************/
function set_persistence(grid, config) {
    const segment = window.location.pathname.split('/').pop() || "No Segment";
    if (segment != 'No Segment') {
        let key = segment + '_' + config.selector;

        if(config.header_flag) {
            key += '_' + config.header_flag;
        }

        grid.on('drop', () => change_grid(key, grid, config));
        grid.on('columnResize', () => change_grid(key, grid, config));

        $(document).on('click', ".page-refresh-btn", function (e) {
            e.stopImmediatePropagation();
            delete_custom_grid();
        });
    }
}

function change_grid(header, grid, config, options = {}) { // 그리드 개인화 (로컬 저장 + DB 순서 저장)
    var columns = grid.getColumns(); // 그리드의 현재 컬럼 설정을 가져옴

    // name => format 매핑 (원본 config.columns/header에는 format이 있음)
    const formatMap = {};
    (config?.columns || config?.header || []).forEach(c => {
        if (c && c.name && c.format) formatMap[c.name] = c.format;
    });

    var updatedColumns = columns.map(function(column) {
        var updatedColumn = Object.assign({}, column); // 기존 컬럼 객체를 변경하지 않고 복제

        // baseWidth가 있으면 width로 변경
        if (updatedColumn.baseWidth !== undefined) {
            updatedColumn.width = updatedColumn.baseWidth;
            delete updatedColumn.baseWidth; // baseWidth 속성 삭제
        }

        // format 복원 (grid.getColumns()에는 format이 없을 수 있음)
        if ((updatedColumn.format === undefined || updatedColumn.format === null) && formatMap[updatedColumn.name] != null) {
            updatedColumn.format = formatMap[updatedColumn.name];
        }

        // formatter 함수는 JSON.stringify로 저장 불가 → 제거 (build_final_config에서 format으로 다시 생성됨)
        if (typeof updatedColumn.formatter === 'function') {
            delete updatedColumn.formatter;
        }

        // editor가 함수인 경우에만 변경
        if (updatedColumn.editor && updatedColumn.editor.type && typeof updatedColumn.editor.type === 'function') {
            const editorType = updatedColumn.editor.type;
            switch (editorType.prototype.constructor.name) {
                case 'CustomTextEditor':
                    updatedColumn.editor = Object.assign({}, updatedColumn.editor, {
                        type: 'customInput'
                    });
                    break;
                case 'SelectEditor':
                    updatedColumn.editor = Object.assign({}, updatedColumn.editor, {
                        type: 'select'
                    });
                    break;
                case 'CustomDatePickerEditor':
                    updatedColumn.editor = Object.assign({}, updatedColumn.editor, {
                        type: 'customDatePicker'
                    });
                    break;
                default:
                    updatedColumn.editor = updatedColumn.editor.options.type;
                    break;
            }
        }

        // renderer가 함수인 경우에만 변경
        if (updatedColumn.renderer && updatedColumn.renderer.type && typeof updatedColumn.renderer.type === 'function') {
            const rendererType = updatedColumn.renderer.type;
            switch (rendererType.prototype.constructor.name) {
                case 'CustomButtonRenderer':
                    updatedColumn.renderer = Object.assign({}, updatedColumn.renderer, {
                        type: 'customButton'
                    });
                    break;
                case 'CustomChkBoxRenderer':
                    updatedColumn.renderer = Object.assign({}, {}, {
                        type: 'customChkBox'
                    });
                    break;
            }
        }

        return updatedColumn;
    });

    // 수정된 컬럼 설정을 로컬 스토리지에 저장
    //localStorage.setItem('grid_settings_' + header, JSON.stringify(updatedColumns));

    if (!options.skipDbSync) {
        sync_grid_column_order_to_db(config || {}, grid, updatedColumns)
            .catch(error => console.warn('[grid db order] save failed:', error));
    }
}


/********************************************
 * Grid 새로고침
 * 개인 그리드 삭제 및 새로고침
 ********************************************/
async function delete_custom_grid() {
    const isConfirmed = await customTosters(window.GRID_RESET_MESSAGE); // 확인창

    if (!isConfirmed) return; // 취소하면 종료

    const store = get_grid_column_order_store();
    Object.keys(store).forEach(key => delete store[key]);
    location.reload();
}


function handle_draw_grid(config) {
    remove_persistence(); // 개인 그리드 삭제
    const final_config = build_final_config(config); // config 설정 빌드

    // 컬럼 헤더 옆 필터 아이콘 자동 활성화 (grid_search.js) — 그리드 생성 전에 적용
    // if (typeof window.enableGridColumnFilters === 'function') {
    //     window.enableGridColumnFilters(final_config.columns);
    // }
    // 날짜 컬럼 가운데 정렬
    if (typeof window.centerAlignDateColumns === 'function') {
        window.centerAlignDateColumns(final_config.columns, final_config.body);
    }
    // Y/N 등 boolean 컬럼 가운데 정렬
    if (typeof window.centerAlignBooleanColumns === 'function') {
        window.centerAlignBooleanColumns(final_config.columns, final_config.body);
    }
    // 숫자 컬럼 천단위 콤마 포매터 자동 적용
    if (typeof window.applyNumberCommaFormat === 'function') {
        window.applyNumberCommaFormat(final_config.columns, final_config.body);
    }
    // 합계(summary) 행 template 결과에 콤마 자동 적용
    if (typeof window.applyCommaToSummaryTemplates === 'function') {
        window.applyCommaToSummaryTemplates(final_config.footer);
    }

    const grid = create_grid(final_config); // 그리드 생성
    bind_grid_events(grid, final_config); // 그리드 이벤트 바인딩
    bind_check_change_event(grid, final_config); // 그리츠 체크 바인딩
    setup_resizing(grid, final_config); // 그리드 리사이징 이벤트
    handle_visibility(grid, final_config); // 숨김 요소 이벤트처리
    set_persistence(grid, final_config); // 개인 그리드

    // ─── 그룹핑 기능 활성화 ──────────────────────────────────
    if (final_config.grouping) {
        _setup_grid_grouping(grid, final_config);   // grouping 내부에서 useColumnReorder도 처리
    } else if (final_config.useColumnReorder) {
        // 그룹핑 없이 컬럼 이동만 사용
        _setup_column_reorder(grid, final_config);
    }

    // ─── 엑셀 다운로드 버튼 자동 바인딩 ─────────────────────
    // excelExport 옵션이 없어도 자동 바인딩
    // excelExport:false 일 때만 비활성화
    if (final_config.excelExport !== false) {
        _bindExcelExport(grid, final_config);
    }

    load_grid_column_order_from_db(grid, final_config)
        .catch(error => console.warn('[grid db order] apply failed:', error));

    // 리스트형 그리드라면 상단에 통합 검색창 자동 부착 (grid_search.js)
    // if (typeof window.autoAttachListGridFeatures === 'function') {
    //     window.autoAttachListGridFeatures(grid, final_config.selector, final_config.body);
    // }

    return grid;
}


// 행 더블 클릭 이벤트 (상세보기)
function gridDblClick(grid) {
    grid.on('dblclick', (ev) => {
        var clickedRowData = grid.getRow(ev.rowKey);
        if (!clickedRowData) return;
        var firstColumnValue = clickedRowData.id;

        if ($('.nav-link').length > 0) {
            $('.nav-link').attr('disabled', false);
            $('.nav-link').not('#pills-profile-tab').removeClass("active");
            $('.tab-pane').not('#pills-profile').removeClass("show active");
            $(".nav-link#pills-profile-tab").addClass("active");
            $(".tab-pane#pills-profile").addClass("show active");
        }
        getNewDetails('edit', firstColumnValue);
    });
}

// 행 더블 클릭 이벤트 (상세보기)
function gridDblClick2(grid, callback = null, type = 'edit', select = 'id') {
    grid.on('dblclick', (ev) => {
        var clickedRowData = grid.getRow(ev.rowKey);
        if (!clickedRowData) return;
        var firstColumnValue = clickedRowData[select]; // 동적으로 속성 값 가져오기
        if ($('.nav-link').length > 0) {
            $('.nav-link').attr('disabled', false);
            $('.nav-link').not('#pills-profile-tab').removeClass("active");
            $('.tab-pane').not('#pills-profile').removeClass("show active");
            $(".nav-link#pills-profile-tab").addClass("active");
            $(".tab-pane#pills-profile").addClass("show active");
        }

        // 콜백 함수가 있으면 실행
        if (typeof callback === 'function') {
            callback(type, firstColumnValue);
        }
    });
}

function gridDblClick3(grid) {
    grid.on('dblclick', (ev) => {
        var clickedRowData = grid.getRow(ev.rowKey); // 클릭한 행의 데이터를 가져옴
        var e1InvNumber = clickedRowData.e1_inv_number; // e1_inv_number 값 추출

        if ($('.nav-link').length > 0) {
            $('.nav-link').attr('disabled', false);
            $('.nav-link').not('#pills-profile-tab').removeClass("active");
            $('.tab-pane').not('#pills-profile').removeClass("show active");
            $(".nav-link#pills-profile-tab").addClass("active");
            $(".tab-pane#pills-profile").addClass("show active");
        }
        getNewDetails('edit', e1InvNumber); // e1_inv_number를 getNewDetails로 전달
    });
}

// 디테일 테이블 행 추가 (crud_type 'C' 처리)
function gridAddRow(grid, appendedData, check = true) {
    const lastRowIdx = grid.getRowCount() - 1;
    const lastRow = lastRowIdx >= 0 ? grid.getRowAt(lastRowIdx) : null;

    appendedData = Object.assign(appendedData, {
        id: lastRow ? (lastRow.id + 1) : 1,
        icon: crtIcon,
        crud_type: 'C'
    });
    grid.appendRow(appendedData,{focus: true});

    const appendedRow = grid.findRows(appendedData);
    // 자동 체크
    if(check) {
        grid.check(appendedRow[0].rowKey);
    }
    // 동일 행 className 추가 (tr_{idx})
    grid.addRowClassName(appendedRow[0].rowKey, 'tr_'+(lastRow ? lastRow.rowKey+1 : 0));
    return appendedRow[0];
}


/************************************************************
 * 그리드 행 추가 개선
 ************************************************************/
function grid_add_row(grid, appendedData, check = true) {
    appendedData = Object.assign(appendedData, {
        icon: crtIcon,
        crud_type: 'C'
    });
    grid.appendRow(appendedData,{focus: true});

    const lastIdx = grid.getRowCount() - 1; // 마지막 행 인덱스
    const lastRow = grid.getRowAt(lastIdx); // { rowKey, ... }
    if (check) {
        grid.check(lastRow.rowKey);
    }

    return lastRow;
}

// 디테일 테이블 행 추가 (crud_type 'C' 처리) - 다중 추가 버전
function gridAddRows(grid, appendedDataArray, check = true) {
    const lastRowIdx = grid.getRowCount() - 1;
    const lastRow = lastRowIdx >= 0 ? grid.getRowAt(lastRowIdx) : null;

    const startId = lastRow ? lastRow.id + 1 : 1;
    const startRowKey = lastRow ? lastRow.rowKey + 1 : 0;

    // id, icon, crud_type 세팅
    const processedData = appendedDataArray.map((data, idx) => {
        return Object.assign({}, data, {
            id: startId + idx,
            icon: crtIcon,
            crud_type: 'C'
        });
    });

    grid.appendRows(processedData);

    // rowKey 바로 계산
    const appendedRows = [];
    for (let i = 0; i < processedData.length; i++) {
        appendedRows.push(grid.getRowAt(startRowKey + i));
    }

    if (check) {
        appendedRows.forEach(row => {
            grid.check(row.rowKey);
        });
    }

    appendedRows.forEach((row, idx) => {
        grid.addRowClassName(row.rowKey, 'tr_' + (startRowKey + idx));
    });

    return appendedRows;
}
// 디테일 테이블 행 삭제 (crud_type 'D' 처리)
function gridDeleteRow(grid) {
    let rowKeys = grid.getCheckedRowKeys();
    // 각 체크된 행에 대해 반복
    rowKeys.forEach(rowKey => {
        const curRow = grid.getRow(rowKey)
        const crudType = curRow.crud_type;

        if (crudType != 'D') {
            if (crudType == 'C' || !crudType) { // C(create)
                grid.removeRow(rowKey);
                // $('.add_new_detail_row').trigger('change');
                // // tfoot 개수 업데이트
                // let cnt = $('#ship_detail_form tfoot td').eq(0).text() * 1;
                // $('#ship_detail_form tfoot td').eq(0).text(cnt - 1);
            } else { // U(update)/NaN
                // curRow.find('input,select').not('.non-disabled').attr('disabled', true);
                // curRow.find('.edit-icon').html(icon);
                // 현재 행의 'crud_flag' 값을 'D'로 설정
                grid.setValue(rowKey, 'crud_type', 'D', false);
                grid.setValue(rowKey, 'icon', delIcon, false);
                grid.disableRow(rowKey, false);
            }
        }
    });
}


/************************************************************
 * 그리드 행 삭제 개선
 ************************************************************/
function grid_delete_row(grid, check = true) {
    let rowKeys = [];
    if(check == true) { // 체크박스 기준
        rowKeys = grid.getCheckedRowKeys();
    } else { // 드래그된 셀 범위 기준
        const range = grid.getSelectionRange();

        if (range) {
            // 드래그된 범위 처리
            const startIndex = Math.min(range.start[0], range.end[0]);
            const endIndex = Math.max(range.start[0], range.end[0]);

            for (let i = startIndex; i <= endIndex; i++) {
                const row = grid.getRowAt(i);
                if (row && row.rowKey !== undefined) {
                    rowKeys.push(row.rowKey);
                }
            }
        } else {
            // 드래그 안 됐을 경우: 단일 셀 선택 여부 확인
            const focused = grid.getFocusedCell();
            if (focused && focused.rowKey !== null) {
                rowKeys.push(focused.rowKey);
            }
        }
    }
    // 각 체크된 행에 대해 반복
    rowKeys.forEach(rowKey => {
        const curRow = grid.getRow(rowKey)
        const crudType = curRow.crud_type;

        if (crudType != 'D') {
            if (crudType == 'C' || !crudType) { // C(create)
                grid.removeRow(rowKey);
                // $('.add_new_detail_row').trigger('change');
                // // tfoot 개수 업데이트
                // let cnt = $('#ship_detail_form tfoot td').eq(0).text() * 1;
                // $('#ship_detail_form tfoot td').eq(0).text(cnt - 1);
            } else { // U(update)/NaN
                // curRow.find('input,select').not('.non-disabled').attr('disabled', true);
                // curRow.find('.edit-icon').html(icon);
                // 현재 행의 'crud_flag' 값을 'D'로 설정
                grid.setValue(rowKey, 'crud_type', 'D', false);
                grid.setValue(rowKey, 'icon', delIcon, false);
                grid.disableRow(rowKey, false);
            }
        }
    });
}

function getGridData(grid, row_key = false) {
    return grid.getCheckedRows().map(row => {
        // getRow 메서드를 사용하여 체크된 각 행의 전체 데이터를 가져옵니다.

        const rowData = grid.getRow(row.rowKey);

        const {icon, uniqueKey,sortKey,rowKey,_attributes,_disabledPriority,rowSpanMap,_relationListItemMap, ...necessaryData} = rowData;
        // 필요시 rowKey 포함
        if (row_key) {
            necessaryData.rowKey = rowKey;
        }

        // 필드 값이 빈칸일 경우 빈 문자열로 처리
        Object.keys(necessaryData).forEach(key => {
            if (necessaryData[key] === null || necessaryData[key] === undefined || necessaryData[key] === '') {
                necessaryData[key] = '';
            }
        });
        // rowData 객체를 그대로 반환합니다.
        return necessaryData;
    });
}

function getAllGridData(grid) {
    return grid.getData().map(row => {
        // getRow 메서드를 사용하여 모든 행의 전체 데이터를 가져옵니다.
        const rowData = grid.getRow(row.rowKey);
        const {icon, uniqueKey,sortKey,rowKey,_attributes,_disabledPriority,rowSpanMap,_relationListItemMap, ...necessaryData} = rowData;

        // 필드 값이 빈칸일 경우 빈 문자열로 처리
        Object.keys(necessaryData).forEach(key => {
            if (necessaryData[key] === null || necessaryData[key] === undefined || necessaryData[key] === '') {
                necessaryData[key] = '';
            }
        });
        // rowData 객체를 그대로 반환합니다.
        return necessaryData;
    });
}


// 그리드 특정 행 비활성화
function disableEntireRow(grid, rowKey) {
    // 해당 rowKey에 해당하는 행의 모든 컬럼을 가져옵니다.
    const columns = grid.getColumns();

    // 각 컬럼에 대해 disableCell을 호출하여 셀을 비활성화합니다.
    columns.forEach(column => {
        if(column.name) {
            grid.disableCell(rowKey, column.name);
        }
    });
}

function changeGrid(header, grid, config = {}, options = {}) { // 그리드 개인화
    var columns = grid.getColumns(); // 그리드의 현재 컬럼 설정을 가져옴

    var updatedColumns = columns.map(function(column) {
        var updatedColumn = Object.assign({}, column); // 기존 컬럼 객체를 변경하지 않고 복제

        // baseWidth가 있으면 width로 변경
        if (updatedColumn.baseWidth !== undefined) {
            updatedColumn.width = updatedColumn.baseWidth;
            delete updatedColumn.baseWidth; // baseWidth 속성 삭제
        }

        // editor가 함수인 경우에만 변경
        if (updatedColumn.editor && updatedColumn.editor.type && typeof updatedColumn.editor.type === 'function') {
            const editorType = updatedColumn.editor.type;
            switch (editorType.prototype.constructor.name) {
                case 'CustomTextEditor':
                    updatedColumn.editor = Object.assign({}, updatedColumn.editor, {
                        type: 'customInput'
                    });
                    break;
                case 'SelectEditor':
                    updatedColumn.editor = Object.assign({}, updatedColumn.editor, {
                        type: 'select'
                    });
                    break;
                case 'CustomDatePickerEditor':
                    updatedColumn.editor = Object.assign({}, updatedColumn.editor, {
                        type: 'customDatePicker'
                    });
                    break;
                default:
                    updatedColumn.editor = updatedColumn.editor.options.type;
                    break;
            }
        }

        // renderer가 함수인 경우에만 변경
        if (updatedColumn.renderer && updatedColumn.renderer.type && typeof updatedColumn.renderer.type === 'function') {
            const rendererType = updatedColumn.renderer.type;
            switch (rendererType.prototype.constructor.name) {
                case 'CustomButtonRenderer':
                    updatedColumn.renderer = Object.assign({}, updatedColumn.renderer, {
                        type: 'customButton'
                    });
                    break;
                case 'CustomChkBoxRenderer':
                    updatedColumn.renderer = Object.assign({}, {}, {
                        type: 'customChkBox'
                    });
                    break;
            }
        }

        return updatedColumn;
    });

    if (!options.skipDbSync) {
        sync_grid_column_order_to_db(config || {}, grid, updatedColumns)
            .catch(error => console.warn('[grid db order] save failed:', error));
    }
}
// Validation of Required Values
function validReqVal(form, focus = true) {
    // form 매개변수로 전달받은 id 또는 class 값을 기준으로 form 요소를 선택
    const formElement = document.querySelector(form);
    if (!formElement) {
        console.log('Form element not found');
        return false;
    }

    // form 내 모든 .text-danger가 포함된 <li> 요소 선택
    const textDangerLi = formElement.querySelectorAll('li h5.text-danger');

    // .text-danger가 존재하지 않을 경우 바로 false 반환
    if (textDangerLi.length === 0) {
        console.log('No .text-danger elements found');
        return false;
    }

    // 각 .text-danger h5가 속한 <li> 안의 <input> 또는 <select> 요소를 검사
    let isEmpty = false;
    let firstEmptyInputH5 = ''; // 첫 번째로 빈 값인 input의 h5 텍스트 저장
    let firstEmptyInput = null; // 첫 번째 빈 값인 input 또는 select 요소 저장

    outerLoop:
        for (let dangerElement of textDangerLi) {
            const parentLi = dangerElement.closest('li'); // 부모 <li> 요소를 찾음
            const inputs = parentLi.querySelectorAll('input, select'); // 해당 <li> 안의 <input> 또는 <select>들 선택

            for (let input of inputs) {
                // hidden 타입의 input 요소는 제외
                if (input.type === 'hidden') {
                    continue;
                }

                // select 요소는 기본 선택값("")이 있는지 확인
                if ((input.tagName === 'SELECT' && input.value.trim() === '') ||
                    (input.tagName !== 'SELECT' && input.value.trim() === '')) {
                    isEmpty = true;
                    firstEmptyInputH5 = dangerElement.innerText; // h5 텍스트 저장
                    firstEmptyInput = input; // 빈 값인 첫 번째 input 또는 select 요소 저장
                    break outerLoop; // 빈 값이 발견되면 즉시 루프 중단
                }
            }
        }

    // focus가 true일 때, 첫 번째 빈 값인 input 또는 select 요소에 포커스
    if (focus && firstEmptyInput) {
        firstEmptyInput.focus(); // 포커스 설정
    }

    // 결과 데이터를 반환 (isEmpty 여부와 첫 번째 빈 값인 h5 텍스트)
    let data = {
        check: isEmpty,
        txt: firstEmptyInputH5, // 첫 번째 빈 값이 있는 <h5> 텍스트
        name: firstEmptyInput ? firstEmptyInput.name : '' // 첫 번째 빈 값인 input 또는 select의 name
    };

    return data;
}

// 검색 필터 필수값 검증
function validFilterReqVal(form, focus = true) {
    let formElement = document.querySelector(form);
    if (!formElement) {
        return { check: false, txt: '', name: '' };
    }

    let requiredLabels = formElement.querySelectorAll('label.text-danger');
    if (requiredLabels.length === 0) {
        return { check: false, txt: '', name: '' };
    }

    let firstEmptyEl = null;
    let firstEmptyTxt = '';
    let isEmpty = false;

    outer:
        for (let label of requiredLabels) {
            // 1) 라벨이 들어있는 input_title div
            let titleDiv = label.closest('.input_title') || label.parentElement;
            if (!titleDiv) continue;

            // 2) 상위 li
            let li = titleDiv.closest('li');
            if (!li) continue;

            // 3) li 안의 input_cont
            let cont = li.querySelector('.input_cont');
            if (!cont) continue;

            // 4) input_cont 안의 실제 입력 요소들(숨김 제외)
            let fields = cont.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');

            // (A) date가 2개 이상이면 from/to 둘 다 체크
            let dateFields = cont.querySelectorAll('input[type="date"]:not([disabled])');
            if (dateFields.length >= 2) {
                let from = dateFields[0];
                let to   = dateFields[1];

                let fromOk = (from.value ?? '').trim() !== '';
                let toOk   = (to.value ?? '').trim() !== '';

                if (!fromOk || !toOk) {
                    isEmpty = true;
                    firstEmptyTxt = (label.innerText || '').trim();
                    firstEmptyEl = !fromOk ? from : to;
                    break outer;
                }

                continue; // date쌍은 여기서 끝
            }

            // (B) 일반 필드들 체크: 하나라도 비면 실패
            for (let el of fields) {
                let val = (el.value ?? '').toString().trim();
                if (val === '') {
                    isEmpty = true;
                    firstEmptyTxt = (label.innerText || '').trim();
                    firstEmptyEl = el;
                    break outer;
                }
            }
        }

    if (focus && firstEmptyEl) {
        firstEmptyEl.focus();
    }

    return {
        check: isEmpty,
        txt: firstEmptyTxt,
        name: firstEmptyEl ? (firstEmptyEl.name || '') : ''
    };
}

// 멀티콤보 css
$(document).ready(function () {
    // 멀티 콤보 css 잡기
    $('.hidden_sel').hide();
    $('.multi_select select').multipleSelect({
        minimumCountSelected: 999
    })
});

// getCd 전역 변수 선언 및 초기화
let getCdTypeList = null;

// 유형 필터 옵션 리스트 함수
function getCd(type) {
    let url = 'get_code';
    let data = {
        type : type
    };

    getAjax(url, data, function (response) {
        getCdTypeList = response.data;
    });
}

// getCd 함수를 Promise로 정의
function gfn_code(type) {
    return new Promise((resolve, reject) => {
        let url = 'get_code';
        let data = {
            type: type
        };

        getAjax(url, data, function(response) {
            if (response && response.data) {
                resolve(response.data); // 데이터를 반환
            } else {
                reject('Failed to fetch data');
            }
        });
    });
}

//
function gfn_create_combo(selectName, codes, udfYn = null) {
    // 기존 옵션 삭제
    let select = $('select[name="' + selectName + '"]');
    select.empty();


    if (udfYn) {
        select.append('<option selected></option>'); // 기본 빈 옵션
        $.each(codes, function(index, option) {
            select.append('<option value="' + option.sub_cd + '" data-udf1="' + option.udf1 + '">' + option.converted_code + '</option>');
        });
    } else {
        // 새 옵션 추가
        select.append('<option selected></option>'); // 기본 빈 옵션
        $.each(codes, function(index, option) {
            select.append('<option value="' + option.sub_cd + '">' + option.converted_code + '</option>');
        });
    }
}

// 비활성화
function gfn_disable(inputName) {
    if (!inputName.includes('btn')) {
        // input 비활성화
        let input = $(`input[name="${inputName}"]`);
        input.attr('readonly', true); // readonly 설정
        input.css({ "background-color": "#e9ecef" }); // 스타일 비활성화
        // input.css({ "background-color": "#e9ecef" }); // 드래그가 안돼서 pointer-event 제거
        // input.on('focus', function () { $(this).blur(); }); // 포커스 차단

        // select 비활성화
        let select = $(`select[name="${inputName}"]`);
        select.attr('readonly', true); // 의미 없지만 일관성 유지
        select.css({ "pointer-events": "none", "background-color": "#e9ecef" }); // 스타일 비활성화
        select.on('focus', function () { $(this).blur(); }); // 포커스 차단
        select.on('change', function (e) { e.preventDefault(); }); // 변경 차단
    } else {
        // 버튼 숨김
        $(`#${inputName}`).css('display', 'none');
    }
}

// 활성화
function gfn_enable(inputName) {
    if (!inputName.includes('btn')) {
        // input 활성화
        let input = $(`input[name="${inputName}"]`);
        input.removeAttr('readonly'); // readonly 제거
        input.css({ "background-color": "" }); // 스타일 복원
        // input.off('focus'); // focus 이벤트 제거

        // select 활성화
        let select = $(`select[name="${inputName}"]`);
        select.removeAttr('readonly'); // readonly 제거
        select.prop('disabled', false);
        select.css({ "pointer-events": "auto", "background-color": "" }); // 스타일 복원
        select.off('focus change'); // 이벤트 제거
    } else {
        // 버튼 활성화
        $(`#${inputName}`).css('display', 'inline');
    }
}

/**
 * 컴퍼넌트 비활성 여부 체크
 * 비활성(true), 활성(false)
 */
function gfn_disableCheck(inputName) {

    // 버튼은 disableCheck 대상 아님
    if (inputName.includes('btn')) {
        return false;
    }

    // input / select 모두 조회
    let $el = $(`input[name="${inputName}"], select[name="${inputName}"]`);

    if ($el.length === 0) {
        // 대상 없으면 활성으로 간주
        return false;
    }

    // readonly 속성 체크
    if ($el.prop('readonly')) {
        return true;
    }

    // select의 disabled 체크 (enable에서 prop 사용하므로)
    if ($el.prop('disabled')) {
        return true;
    }

    // pointer-events 스타일 체크 (CSS 기반 disable 대응)
    if ($el.css('pointer-events') === 'none') {
        return true;
    }

    return false;
}

// 영역 비활성화
function gfn_disableToControl(selector) {
    // TODO::기존
    // // input 및 select 비활성화
    // $(`${selector} input, ${selector} select`).each(function () {
    //     $(this).attr('readonly', true); // readonly 설정
    //     $(this).css({ "pointer-events": "none", "background-color": "#e9ecef" }); // 스타일 비활성화
    //     // $(this).css({ "background-color": "#e9ecef" }); // 드래그가 안돼서 pointer-event 제거
    //     $(this).on('focus', function () { $(this).blur(); }); // 포커스 차단
    //     $(this).on('change', function (e) { e.preventDefault(); }); // 변경 차단
    // });

    // TODO::NEW
    // input 비활성화 (pointer-events 없음)
    $(`${selector} input`).each(function () {
        $(this).attr('readonly', true);
        $(this).css({ "background-color": "#e9ecef" });
        // $(this).on('focus', function () { $(this).blur(); });
        // $(this).on('change', function (e) { e.preventDefault(); });
    });

    // select 비활성화 (pointer-events: none 적용)
    $(`${selector} select`).each(function () {
        $(this).css({ "pointer-events": "none", "background-color": "#e9ecef" });
        $(this).on('focus', function () { $(this).blur(); });
        $(this).on('change', function (e) { e.preventDefault(); });
    });

    // 버튼 숨김
    $(`${selector} button`).each(function () {
        $(this).css('display', 'none');
    });
}

// 영역 활성화
function gfn_enableToControl(selector) {
    // TODO::기존
    // input 및 select 활성화
    // $(`${selector} input, ${selector} select`).each(function () {
    //     $(this).removeAttr('readonly'); // readonly 제거
    //     $(this).css({ "pointer-events": "auto", "background-color": "" }); // 스타일 복원
    //     $(this).off('focus change'); // 이벤트 제거
    // });
    // TODO::NEW
    // input 비활성화 (pointer-events 없음)
    $(`${selector} input`).each(function () {
        $(this).removeAttr('readonly'); // readonly 제거
        $(this).css({ "background-color": "" }); // 스타일 복원
        // $(this).off('focus change'); // 이벤트 제거
    });

    // select 비활성화 (pointer-events: none 적용)
    $(`${selector} select`).each(function () {
        $(this).removeAttr('readonly'); // readonly 제거
        $(this).css({ "pointer-events": "auto", "background-color": "" }); // 스타일 복원
        $(this).off('focus change'); // 이벤트 제거
    });

    // 버튼 활성화
    $(`${selector} button`).each(function () {
        $(this).css('display', 'inline');
    });
}

// 텍스트 색상 변경
// param1: id값
// param2: 색상 타입, default = 'normal'
function gfn_setTextStyle(param1, param2 = 'normal') {
    // jQuery로 id를 자동으로 선택
    let element = $(`#${param1}`);

    if (!element.length) {
        console.error(`Element with id "${param1}" not found.`);
        return;
    }

    // 모든 클래스 초기화
    element.removeClass();

    // 상태에 따라 클래스 추가
    if (param2 === 'danger') {
        element.addClass("text-danger");
    } else if (param2 === 'info') {
        element.addClass("text-info");
    }
}


function gfn_calc(number) {
    // 유효성 검사: 숫자가 아니거나 null 또는 undefined인 경우 0 반환
    if (isNaN(number) || number == null) {
        return 0;
    }

    // 소수점 3자리까지 반올림
    const T = Math.pow(10, 3); // 10^3 = 1000
    return Math.round(number * T) / T;
}

/*
 * NULL 체크
 */
function gfn_is_null(val) {
    if(typeof val == "number" && val == 0){
        return false;
    }

    if (val == null || val == "" || val == undefined || val == "undefined") {
        return true;
    }
    return false;
}

function gfn_focus(columnName) {
    // input이나 select 요소 중 name이 columnName인 요소를 찾아 포커스
    let target = $(`input[name="${columnName}"], select[name="${columnName}"]`);

    if (target.length > 0) {
        target.focus();
    } else {
        console.warn(`Element with name "${columnName}" not found.`);
    }
}

function gfn_focusGrd(grid, rowKey, columnName) {
    try {
        // 기본 유효성 검사
        if (!grid || rowKey === undefined || rowKey === null) {
            console.error('Invalid Grid object or Row Key');
            return;
        }

        // 선택된 컬럼이 유효한지 확인
        if (!gfn_is_null(columnName)) {
            const column = grid.getColumns().find(col => col.name === columnName);

            if (!column) {
                console.warn(`Column not found: ${columnName}`);
                return;
            }

            // 컬럼이 숨겨진 경우 경고 출력
            if (column.hidden) {
                console.warn(`Column is hidden: ${columnName}`);
                return;
            }

            // 포커싱 수행
            grid.focus(rowKey, columnName);
        } else {
            // 컬럼 이름이 없는 경우 해당 행으로 포커싱
            console.info(`Focusing on row ${rowKey}`);
            grid.focus(rowKey);
        }
    } catch (error) {
        console.error('Error in gfn_focusGrd:', error);
    }
}
/**
 *  그리드의 필수값을 체크한다. (By Necessary)
 */
function gfn_grd_necessary(grid) {
    const data = grid.getCheckedRows(); // 선택된 행 가져오기
    const col_list = grid.getColumns(); // 컬럼 정보 가져오기

    if (data.length < 1) {
        return { success: true }; // 선택된 행이 없으면 성공 반환
    }

    // 데이터 행과 컬럼 정보를 동시에 검사
    for (let i = 0; i < data.length; i++) {
        const row = data[i];
        if (row.crud_type == 'D') {
            continue;
        }
        const row_key = row.rowKey; // 각 행의 rowKey

        for (let j = 0; j < col_list.length; j++) {
            const col = col_list[j];
            const column_name = col.name;

            // 필수값 컬럼인지 확인
            if (col.validation?.required) {
                const header_text = col.header; // 컬럼 헤더 텍스트
                const value = row[column_name]; // 셀 값 가져오기

                if (gfn_is_null(value)) {
                    // 필수값 누락 시 처리
                    grid.focus(row_key, column_name);
                    grid.startEditing(row_key, column_name);
                    return { success: false, column_name: header_text };
                }
            }
        }
    }

    return { success: true }; // 모든 검증 통과
}

function gfn_setValue(inputName, inputValue, formSelector=''){
    const selector = `${formSelector} [name="${inputName}"]`;
    const $element = $(selector);

    if (!$element.length) {
        return;
    }

    $element.val(inputValue)/*.trigger('change')*/; // 값 설정
}

function gfn_setValueWithTrigger(inputName, inputValue, formSelector=''){
    const selector = `${formSelector} [name="${inputName}"]`;
    const $element = $(selector);

    if (!$element.length) {
        return;
    }

    $element.val(inputValue); // 값 설정
    $element.trigger('multi_clear');
}

function gfn_getValue(inputName, formSelector=''){
    const selector = `${formSelector} [name="${inputName}"]`;
    const $element = $(selector);
    if (!$element.length) {
        return '';
    }

    return $element.val(); // 값 반환
}

function gfn_gridValidationSwitch(grid, columnName, changeType) {
    const currentColumns = grid.getColumns();
    const updatedColumns = currentColumns.map(col => {
        if (col.name === columnName) {
            // 기본적으로 기존 속성 유지 및 너비 설정
            const baseColumn = {
                ...col,
                width: col.width || col.baseWidth || 100
            };
            // changeType에 따라 속성 변경
            switch (changeType) {
                case 'required':
                    return {
                        ...baseColumn,
                        validation: { required: true }, // 필수
                        className: (baseColumn.className || '').replace('tui-grid-cell-required', '') // 클래스 제거
                    };
                case 'normal':
                    return {
                        ...baseColumn,
                        validation: undefined, // 검증 해제
                        className: (baseColumn.className || '').replace('tui-grid-cell-required', '') // 클래스 제거
                    };
                case 'optional':
                    return {
                        ...baseColumn,
                        validation: undefined, // 검증 해제
                        className: `${baseColumn.className || ''} tui-grid-cell-required`.trim() // 클래스 추가
                    };
                default:
                    return baseColumn; // 변경 사항이 없으면 그대로 반환
            }
        }
        return {
            ...col, // 나머지 컬럼도 기존 속성 유지
            width: col.width || col.baseWidth || 100, // 너비 유지
        };// 다른 컬럼은 그대로 반환
    });
    grid.setColumns(updatedColumns);
}

// 배경 클릭 시 모든 팝업 닫기 (선택사항)
$(document).on("click", ".pop_bg", function () {
    $(".custom-popup").hide();
    $(this).remove();
});

// ESC 키 눌렀을 때 팝업 닫기
$(document).on("keydown", function (e) {
    if (e.key === "Escape") {
        $(".custom-popup").hide();
        $('.pop_bg').remove();
    }
});

function openPopup(popupId) {
    // 팝업 보여주기
    $(popupId).show();

    // 배경 블러 추가 (이미 존재하지 않을 경우만)
    if ($('.pop_bg').length === 0) {
        $('html').append('<div class="pop_bg" style="position:fixed; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1070;"></div>');
    }
}

/**
 * 팝업 닫기 공통 함수
 */
function closePopup(popupId) {
    $(popupId).hide(); // 팝업 닫기

    // 모든 팝업이 닫혔을 경우 배경 제거
    if ($('.custom-popup:visible').length === 0) {
        $('.pop_bg').remove();
    }
}


// 팝업 데이터 더블클릭 시 그리드에 데이터 set
function popupSelectedDataSet(popupGrid, rowKey, targetGrid, targetRowKey, inputSetting) {
    // inputSetting의 키-값 쌍을 순회
    targetGrid.finishEditing();
    Object.entries(inputSetting).forEach(([targetColumn, popupColumn]) => {
        // popupGrid에서 popupColumn 값을 가져와 targetGrid의 targetColumn에 설정
        const value = popupGrid.getValue(rowKey, popupColumn);
        targetGrid.setValue(Number(targetRowKey), targetColumn, value, false);
    });

    popupTargetGrid = undefined;
    popupTargetInputSetting = undefined;
    targetGrid.check(Number(targetRowKey));
}

// 팝업 데이터 더블클릭 시 그리드에 데이터 set
function popupSelectedDataSetOnForm(popupGrid, rowKey, inputSetting) {
    // inputSetting의 키-값 쌍을 순회
    Object.entries(inputSetting).forEach(([targetColumn, popupColumn]) => {
        // popupGrid에서 popupColumn 값을 가져와 targetGrid의 targetColumn에 설정
        const value = popupGrid.getValue(rowKey, popupColumn);

        gfn_setValueWithTrigger(targetColumn, value);
    });

    // popupTargetGrid = undefined;
    popupTargetInputSetting = undefined;
}

// 그리드에 데이터 세팅
function popupDataAutoSet(body, targetGrid, targetRowKey, inputSetting) {
    if (!body || !body[0]) return;

    const selectedRow = body[0]; // body의 첫 번째 항목 사용
    targetGrid.finishEditing();
    Object.entries(inputSetting).forEach(([targetColumn, popupColumn]) => {

        const value = selectedRow[popupColumn] ?? '';
        targetGrid.setValue(Number(targetRowKey), targetColumn, value, false);
    });

    popupTargetGrid = undefined;
    popupTargetInputSetting = undefined;
}

// 폼에 데이터 세팅
function popupAutoSetOnForm(body, inputSetting) {
    if (!body || !body[0]) return;

    const selectedRow = body[0]; // body의 첫 번째 항목 사용

    Object.entries(inputSetting).forEach(([targetColumn, popupColumn]) => {
        const value = selectedRow[popupColumn] ?? '';
        gfn_setValueWithTrigger(targetColumn, value);
    });

    popupTargetInputSetting = undefined;
}

// getCd 함수를 Promise로 정의
function gfn_parsingBarcode(parsingUrl, barcode, warehouseId = null, locationId = null) {
    return new Promise((resolve, reject) => {
        let url = parsingUrl;
        let data = {
            barcode: barcode,
            warehouseId: warehouseId,
            locationId: locationId,
        };

        getAjax(url, data, function (response) {
            var obj = response.data;
            if (obj) {
                resolve(obj); // 성공 시 데이터를 반환
            } else {
                // show_toastr('Error!', response.message, 'error');
                reject('Failed to fetch data'); // 실패 시 에러 반환
            }
        });
    });
}

/********************************************************
 * 날짜 계산 도움 함수
 *******************************************************/
function gfn_get_date() {
    let today           = new Date();
    let year            = today.getFullYear();
    let month           = String(today.getMonth() + 1).padStart(2, '0'); // 월은 0부터 시작하므로 +1 필요
    let day             = String(today.getDate()).padStart(2, '0'); // 일자를 2자리로 패딩\
    let formatted_date   = `${year}-${month}-${day}`;

    const date_list = {
        date: formatted_date,
        year: year,
        month: month,
        day: day,
    }

    return date_list;
};


/********************************************************
 * 날짜 포맷
 *  - send_time : 날짜 문자열 (예: "2025-10-15T04:50:53.000000Z")
 *  - format    : 옵션 객체 (없으면 전부 true)
 *      {
 *        year: true/false,
 *        month: true/false,
 *        day: true/false,
 *        hour: true/false,
 *        minute: true/false,
 *        second: true/false,
 *      }
 *
 *  예)
 *    gfn_format_date(send_time);
 *      → "2025. 10. 15. 13:50"
 *    gfn_format_date(send_time, { year: false });
 *      → "10. 15. 13:50"
 *******************************************************/
function gfn_format_date(send_time, format) {

    // 기본값: 전부 true (초는 기본 false로 해두는 것도 가능)
    const default_format = {
        year:   true,
        month:  true,
        day:    true,
        hour:   true,
        minute: true,
        second: true,
    };

    // format이 없으면 전부 true, 있으면 덮어쓰기
    const opt = Object.assign({}, default_format, format || {});

    const d       = new Date(send_time); // 인자로 받은 send_time 사용
    const year    = String(d.getFullYear());
    const month   = String(d.getMonth() + 1).padStart(2, '0');
    const day     = String(d.getDate()).padStart(2, '0');
    const hour    = String(d.getHours()).padStart(2, '0');
    const minute  = String(d.getMinutes()).padStart(2, '0');
    const second  = String(d.getSeconds()).padStart(2, '0');

    let date_parts = [];
    let time_parts = [];

    // 날짜 부분 조합
    if (opt.year)  date_parts.push(year);
    if (opt.month) date_parts.push(month);
    if (opt.day)   date_parts.push(day);

    // 시간 부분 조합
    if (opt.hour)   time_parts.push(hour);
    if (opt.minute) time_parts.push(minute);
    if (opt.second) time_parts.push(second);

    let result = "";

    if (date_parts.length > 0) {
        // "YYYY. MM. DD" 또는 "MM. DD" 이런 식
        result += date_parts.join(". ");
        if (time_parts.length > 0) {
            result += " ";
        } else {
            result += ""; // 필요하면 끝에 점 하나 더 붙이는 것도 가능
        }
    }

    if (time_parts.length > 0) {
        // "HH:MM" 또는 "HH:MM:SS"
        result += time_parts.join(":");
    }

    return result;
}


/******************************************************************************************
 * 특정 날짜에 대해 지정한 값만큼 가감(+-)한 날짜를 반환
 *
 * 입력 파라미터 ----- pInterval : "yyyy" 는 연도 가감, "m" 은 월 가감, "d" 는 일 가감 pAddVal : 가감
 * 하고자 하는 값 (정수형) pYyyymmdd : 가감의 기준이 되는 날짜 pDelimiter : pYyyymmdd 값에 사용된 구분자를
 * 설정 (없으면 "" 입력)
 *
 * 반환값 ---- yyyymmdd 또는 함수 입력시 지정된 구분자를 가지는 yyyy?mm?dd 값
 *
 * 사용예 --- 2008-01-01 에 3 일 더하기 ==> addDate("d", 3, "2008-08-01", "-"); 20080301
 * 에 8 개월 더하기 ==> addDate("m", 8, "20080301", "");
 ******************************************************************************************/
function add_date(pInterval, pAddVal, pYyyymmdd, pDelimiter = "") {
    // 구분자 제거
    if (pDelimiter !== "") {
        const regex = new RegExp(`\\${pDelimiter}`, 'g');
        pYyyymmdd = pYyyymmdd.replace(regex, '');
    }

    let yyyy = parseInt(pYyyymmdd.substr(0, 4), 10);
    let mm   = parseInt(pYyyymmdd.substr(4, 2), 10);
    let dd   = parseInt(pYyyymmdd.substr(6, 2), 10);

    // 각각 더하기
    if (pInterval === "yyyy") {
        yyyy += pAddVal;
    } else if (pInterval === "m") {
        mm += pAddVal;
    } else if (pInterval === "d") {
        dd += pAddVal;
    }

    // JS Date는 알아서 보정해줌
    const date = new Date(yyyy, mm - 1, dd);
    const cYear  = date.getFullYear();
    const cMonth = (date.getMonth() + 1).toString().padStart(2, '0');
    const cDay   = date.getDate().toString().padStart(2, '0');

    return pDelimiter !== ""
        ? `${cYear}${pDelimiter}${cMonth}${pDelimiter}${cDay}`
        : `${cYear}${cMonth}${cDay}`;
}


function gfn_setPopupConfig(config){
    const defaultConfig = {
        grid: null, // 적용할 그리드 객체 (null이면 팝업 모드로 동작)
        autoSetColumns:[],
        popupCode: "", // 팝업 구분값
        // name: "", // 대상 컬럼명
        modalId: "#commonModal", // 팝업 모달 ID
        className: "modal-lg", // 팝업 모달 ID
        popupTitle: "Popup", // 팝업 제목
        popupDataHeader: null, // 팝업 조회 컬럼
        dataMapper: (context) => ({}), // 컨텍스트에 따라 데이터 매핑
        inputSetting: {}, // 추가된 inputSetting 개념 (매핑 설정)
        multi: false,
        callback: null, // 데이터 세팅 완료 후 호출할 콜백 함수
    };

    const finalConfig = {
        ...defaultConfig,
        ...config,
        grid: config.grid ? window[config.grid] : null, // 객체로 치환
        gridName: config.grid || null // 이름은 그대로 저장
    };


    if(finalConfig.grid){
        // 그리드 팝업일 시 이벤트 설정
        bindAutoSetOnGrid(finalConfig);
    } else {
        // 폼 팝업일 시 이벤트 설정
        bindAutoSetOnForm(finalConfig);
    }
    if(finalConfig.popupCode !== "NONE"){
        // 팝업 호출
        $(document).off("click", finalConfig.triggerSelector) // 기존 핸들러 제거
            .on("click", finalConfig.triggerSelector, function (e) {
                e.stopImmediatePropagation();
                popupTargetInputSetting = finalConfig.inputSetting;
                popupTargetCallback = finalConfig.callback;

                const buttonElement = $(this); // 클릭된 버튼

                let rowKey = null;

                if (finalConfig.grid) {
                    // 버튼에서 가장 가까운 `.tui-grid-layer-editing` 영역 찾기
                    const gridEditingLayer = buttonElement.closest(".tui-grid-layer-editing");

                    if (gridEditingLayer.length > 0) {
                        // `.tui-grid-layer-editing` 안에서 `input` 태그의 `data-row-key` 속성 값 추출
                        rowKey = gridEditingLayer.find("input[data-row-key]").attr("data-row-key");
                    }

                    // `gridEditingLayer`가 없거나 `rowKey`를 못 찾았을 때, 다른 방식으로 시도
                    if (!rowKey) {
                        console.warn("gridEditingLayer에서 rowKey를 찾지 못함. 다른 방식 시도.");
                        // 버튼에서 가까운 `.tui-grid-row-*` 클래스를 포함한 `tr` 요소 찾기
                        const gridRow = buttonElement.closest("tr");

                        if (gridRow.length > 0) {
                            rowKey = gridRow.find("td[data-row-key]").attr("data-row-key");

                            if (!rowKey) {
                                console.warn("data-row-key 속성값을 못 찾음. 클래스 기반으로 rowKey 추출.");
                                // 클래스 이름에서 rowKey 추출
                                const rowClass = gridRow.attr("class") || "";
                                const match = rowClass.match(/tr_(\d+)/);
                                if (match) {
                                    rowKey = match[1];
                                }
                            }
                        } else {
                            console.warn("gridRow를 찾을 수 없습니다.");
                        }
                    }

                    popupTargetGrid = finalConfig.grid;
                }

                if (rowKey !== null && rowKey !== undefined && rowKey !== '') {
                    rowKey = Number(rowKey);
                }

                const mapped = finalConfig.dataMapper({
                    element: this,
                    grid: finalConfig.grid,
                    rowKey,
                });

                const data = {
                    ...mapped,                          // 기존 방식 그대로 유지
                    dataMapper: { ...mapped },               // data라는 키 안에 똑같이 복사
                    popupDataHeader: finalConfig.popupDataHeader,
                    inputSetting: finalConfig.inputSetting,
                    popupCode: finalConfig.popupCode,
                    triggerSelector: finalConfig.triggerSelector,
                    gridName: finalConfig.gridName,
                };

                // AJAX 호출
                getAjax(window.POPUP_COMMON_ROUTE, data, (response) => {
                    $(finalConfig.modalId + " .modal-title").html(finalConfig.popupTitle);
                    $(finalConfig.modalId + " .modal-dialog").addClass(finalConfig.className);
                    nowOpenPopupModal = finalConfig.modalId;
                    $(finalConfig.modalId + " .modal-body").html(response);
                    $(finalConfig.modalId).modal("show");
                });
            });
    }

    // 멀티 검색 세팅
    if(finalConfig.multi){
        setMultiPop(finalConfig);
    }
}


/************************
 * 그리드 오토셋 설정
 * @param popupConfig
 ************************/
function bindAutoSetOnGrid(popupConfig) {
    let originValue = "";

    /********************************************
     * 오토셋 큐
     * 기존: 컬럼 단위로 큐에 적재 → 행/컬럼 간 충돌 발생
     * 변경: rowKey 기준 행 단위로 묶어서 적재
     *       한 행 내 컬럼들은 autoset:done을 기다리며 순차 처리
     *       행이 끝나면 다음 행으로 진행
     ********************************************/

        // 큐 아이템 구조: { rowKey, cols: [{popupConfig, subValue}, ...] }
    const autoSetQueue = [];
    let isAutoSetRunning = false;

    function runNextAutoSet() {
        if (isAutoSetRunning || autoSetQueue.length === 0) return;
        isAutoSetRunning = true;

        const rowItem = autoSetQueue.shift();
        processRowCols(rowItem.cols, 0);
    }

    // 한 행 내 컬럼들을 순차 처리
    function processRowCols(cols, colIdx) {
        if (colIdx >= cols.length) {
            // 이 행의 모든 컬럼 오토셋 완료 → 다음 행으로
            isAutoSetRunning = false;
            runNextAutoSet();
            return;
        }

        const { popupConfig, subValue } = cols[colIdx];
        const modalId = popupConfig.modalId;

        // 이번 컬럼 완료 이벤트를 1회만 대기 → 다음 컬럼으로
        $(document).one('autoset:done', () => {
            processRowCols(cols, colIdx + 1);
        });

        requestAnimationFrame(() => {
            // 큐에 남은 행이 있으면 다중 붙여넣기 컨텍스트
            window.__autoSetQueueHasMore = autoSetQueue.length > 0;
            getAjax(window.POPUP_COMMON_ROUTE, subValue, (response) => {
                $(modalId + " .modal-title").html(popupConfig.popupTitle);
                $(modalId + " .modal-dialog").addClass(popupConfig.className);
                $(modalId).modal("hide");
                nowOpenPopupModal = modalId;
                $(modalId + " .modal-body").html(response);
                // ❌ 여기서 isAutoSetRunning=false 하면 안 됨
                // (진짜 오토셋 완료는 popupSearch → commonPopDataAutoSet 이후)
            }, false);
        });
    }

    popupConfig.grid.on("editingStart", (ev) => {
        originValue = ev.value;
    });

    popupConfig.grid.on("editingFinish", (ev) => {
        const rowKey = ev.rowKey;
        const colName = ev.columnName;

        if('.search_' + colName != popupConfig.triggerSelector){
            return false;
        }

        if (ev.value == "") {
            Object.entries(popupConfig.inputSetting).forEach(([targetColumn]) => {
                popupConfig.grid.setValue(rowKey, targetColumn, "", false);
            });
            return false;
        }

        if ((originValue == null && ev.value == "") || ev.value == originValue) {
            return false;
        }

        const triggerColumnName = popupConfig.triggerSelector.replace('.search_', '');
        const columnDefinition = popupConfig.grid.getColumn(colName);

        if (
            !columnDefinition ||
            !columnDefinition.editor ||
            columnDefinition.editor.type?.name !== 'CustomTextEditor' ||
            colName !== triggerColumnName
        ) {
            return false;
        }

        const mapped = popupConfig.dataMapper({
            grid: popupConfig.grid,
            rowKey,
            colName,
        });

        const subValue = {
            rowKey,
            colName,
            ...mapped,
            dataMapper: { ...mapped },
            autoSetYn: "Y",
            autoSetValue: ev.value,
            autoSetTarget: popupConfig.inputSetting[colName],
            popupCode: popupConfig.popupCode,
            popupDataHeader: popupConfig.popupDataHeader,
            inputSetting: popupConfig.inputSetting,
            gridName: popupConfig.gridName,
        };

        popupTargetGrid = popupConfig.grid;
        popupTargetInputSetting = popupConfig.inputSetting;
        popupTargetCallback = popupConfig.callback;

        // rowKey 기준으로 기존 큐 아이템에 합치거나 새 아이템 추가
        const existingRow = autoSetQueue.find(item => item.rowKey === rowKey);
        if (existingRow) {
            existingRow.cols.push({ popupConfig, subValue });
        } else {
            autoSetQueue.push({ rowKey, cols: [{ popupConfig, subValue }] });
        }

        runNextAutoSet();
    });
}


// function bindAutoSetOnForm(popupConfig) {
//     popupConfig.autoSetColumns.forEach((fieldName) => {
//         const selector = `[name="${fieldName}"]`;
//
//         if(popupConfig !== 'NONE'){
//
//             // change 이벤트 - Ajax 처리
//             $(document).on("change", selector, function () {
//                 let parentEl;
//                 const dataId = $(this).siblings("button[data-id]").data("id");
//
//                 if (dataId) {
//                     parentEl = $(dataId);
//                 } else {
//                     parentEl = $(this).parents("tr");
//                     parentEl = parentEl.length ? parentEl : $(this).parents("form");
//                 }
//
//                 if (gfn_is_null($(this).val()) || parentEl.length === 0) return;
//
//                 popupTargetGrid = popupConfig.grid;
//                 popupTargetInputSetting = popupConfig.inputSetting;
//
//
//
//                 const rawMapped = popupConfig.dataMapper({ element: this });
//
//                 const subValue = {
//                     ...rawMapped,  // 기존 구조 유지
//                     dataMapper: { ...rawMapped },  // ✅ 중첩 구조 추가
//                     autoSetYn: "Y",
//                     autoSetValue: $(this).val(),
//                     autoSetTarget: popupConfig.inputSetting[$(this).attr('name')],
//                     popupCode: popupConfig.popupCode,
//                     popupDataHeader: popupConfig.popupDataHeader,
//                     inputSetting: popupConfig.inputSetting,
//                     gridName: popupConfig.gridName,
//                 };
//
//                 getAjax(window.POPUP_COMMON_ROUTE, subValue, function (response) {
//                     $(popupConfig.modalId + " .modal-title").html(popupConfig.popupTitle);
//                     $(popupConfig.modalId + " .modal-dialog").addClass(popupConfig.className);
//                     $(popupConfig.modalId).modal("hide");
//                     nowOpenPopupModal = popupConfig.modalId;
//                     $(popupConfig.modalId + " .modal-body").html(response);
//                 }, false);
//             });
//         }
//
//         // input 이벤트 - 관련 필드 초기화
//         $(document).on("input", selector, function () {
//             Object.keys(popupConfig.inputSetting).forEach((name) => {
//                 if (name !== fieldName) {
//                     gfn_setValue(name, '');
//                 }
//             });
//
//             delete window.MULTI_SEARCH_PARAM[popupConfig.name];
//         });
//         $(document).on('multi_clear', selector, function () {
//             delete window.MULTI_SEARCH_PARAM[popupConfig.name];
//         });
//
//     });
// }

function bindAutoSetOnForm(popupConfig) {
    popupConfig.autoSetColumns.forEach((fieldName, idx) => {
        const selector = `[name="${fieldName}"]`;

        // 오토셋 컬럼 2번째만 Tab 여부 플래그 기록
        if (idx === 1) {
            $(document).on("keydown", selector, function (e) {
                // Tab 키면 blur 직전에 표시
                if (e.key === "Tab" || e.keyCode === 9) {
                    $(this).data("__tab_out__", true);
                } else {
                    $(this).data("__tab_out__", false);
                }
            });
        }

        // 공통: Ajax 트리거 함수로 분리
        const triggerAjax = function (el) {
            let parentEl;
            const dataId = $(el).siblings("button[data-id]").data("id");

            if (dataId) {
                parentEl = $(dataId);
            } else {
                parentEl = $(el).parents("tr");
                parentEl = parentEl.length ? parentEl : $(el).parents("form");
            }

            if (gfn_is_null($(el).val()) || parentEl.length === 0) return;

            popupTargetGrid = popupConfig.grid;
            popupTargetInputSetting = popupConfig.inputSetting;
            popupTargetCallback = popupConfig.callback;

            const rawMapped = popupConfig.dataMapper({ element: el });

            const subValue = {
                ...rawMapped,
                dataMapper: { ...rawMapped },
                autoSetYn: "Y",
                autoSetValue: $(el).val(),
                autoSetTarget: popupConfig.inputSetting[$(el).attr("name")],
                popupCode: popupConfig.popupCode,
                popupDataHeader: popupConfig.popupDataHeader,
                inputSetting: popupConfig.inputSetting,
                gridName: popupConfig.gridName,
            };

            getAjax(window.POPUP_COMMON_ROUTE, subValue, function (response) {
                $(popupConfig.modalId + " .modal-title").html(popupConfig.popupTitle);
                $(popupConfig.modalId + " .modal-dialog").addClass(popupConfig.className);
                $(popupConfig.modalId).modal("hide");
                nowOpenPopupModal = popupConfig.modalId;
                $(popupConfig.modalId + " .modal-body").html(response);
            }, false);
        };

        // 2번째는 change 대신 "Tab으로 빠져나갈 때(blur)"만
        if (popupConfig !== "NONE") {
            if (idx === 1) {
                $(document).on("blur", selector, function () {
                    const isTabOut = !!$(this).data("__tab_out__");
                    $(this).data("__tab_out__", false); // 사용 후 초기화

                    if (!isTabOut) return; // Tab 아닐 땐 실행 안 함
                    triggerAjax(this);
                });
            } else {
                // 나머지는 기존대로 change
                $(document).on("change", selector, function () {
                    triggerAjax(this);
                });
            }
        }

        // input 이벤트 - 관련 필드 초기화
        $(document).on("input", selector, function () {
            Object.keys(popupConfig.inputSetting).forEach((name) => {
                if (name !== fieldName) {
                    gfn_setValue(name, "");
                }
            });
            delete window.MULTI_SEARCH_PARAM[popupConfig.name];
        });

        $(document).on("multi_clear", selector, function () {
            delete window.MULTI_SEARCH_PARAM[popupConfig.name];
        });
    });
}


function setMultiPop(popupConfig) {
    $(document).off("click", '#multi_' + popupConfig.name + '_btn')
        .on("click", '#multi_' + popupConfig.name + '_btn', function () {

            $("#multiModal .modal-dialog").addClass('modal-lg');
            $('#multiModal').modal("show");

            $('#multiModal').one('shown.bs.modal', function () {
                let multiPasteHandler = null;
                const multiGridEl = document.getElementById('multiSearchGrid');
                let multiGrid = handle_draw_grid({
                    selector: 'multiSearchGrid',
                    header: window.mainCommonMultiHeader,
                    body: [],
                    footer: {},
                    paste_row: true,
                    check_box: false,
                    wrap_selector: '.inner-multi-popup',
                });

                const getMultiDeleteHeader = function () {
                    return window.mainCommonMultiHeader.find(col => col.name === 'delete').header;
                };

                const parseMultiPasteValues = function (text) {
                    return String(text || '')
                        .split(/\r\n|\n|\r|\t/)
                        .map(value => value.trim())
                        .filter(value => value !== '');
                };

                const buildMultiRows = function (values) {
                    const deleteHeader = getMultiDeleteHeader();
                    return values.map(value => ({
                        value,
                        delete: deleteHeader
                    }));
                };

                if (multiGridEl) {
                    multiPasteHandler = function (e) {
                        const clipboard = e.clipboardData || window.clipboardData;
                        if (!clipboard) {
                            return;
                        }

                        const values = parseMultiPasteValues(clipboard.getData('text'));
                        if (values.length === 0) {
                            return;
                        }

                        e.preventDefault();
                        e.stopPropagation();
                        if (typeof e.stopImmediatePropagation === 'function') {
                            e.stopImmediatePropagation();
                        }

                        const currentRows = getGridData(multiGrid)
                            .filter(row => row.value !== undefined && row.value !== null && String(row.value).trim() !== '')
                            .map(row => ({
                                value: String(row.value).trim(),
                                delete: getMultiDeleteHeader()
                            }));

                        multiGrid.resetData(currentRows.concat(buildMultiRows(values)));
                        multiGrid.checkAll();
                        requestAnimationFrame(() => multiGrid.refreshLayout());
                    };

                    multiGridEl.addEventListener('paste', multiPasteHandler, true);
                }

                // 모달 닫힘 시 초기화
                $('#multiModal').off('hidden.bs.modal.multiSearch')
                    .on('hidden.bs.modal.multiSearch', function () {
                    if (multiGridEl && multiPasteHandler) {
                        multiGridEl.removeEventListener('paste', multiPasteHandler, true);
                    }

                    if (multiGrid && typeof multiGrid.destroy === 'function') {
                        multiGrid.destroy();
                    }

                    $(document).off('click', '.multi_search_delete_btn');
                    $(document).off('click', '#multi_new_btn');
                    $(document).off('click', '#multi_clear');
                    $(document).off('click', '#multi_confirm');
                    $(document).off('blur', '#multiSearchGrid input');
                    $(document).off('blur', '#multiSearchGrid select');
                    $(document).off('change', 'input.tui-grid-content-text-custom');

                    $('#multiSearchGrid').html('');
                    $('#multi_clear').data('id', '#multi_pop_form');
                    $('#multi_confirm').data('id', '#multi_pop_form');

                    multiGrid = undefined;
                    });

                // 버튼 이벤트
                $('#multi_new_btn').off('click').on('click', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    const appendedData = {
                        delete: window.mainCommonMultiHeader.find(col => col.name === 'delete').header
                    };

                    // for (let i = 0; i < 500; i++) {
                    gridAddRow(multiGrid,appendedData,false);
                    // appendedDatas.push({...appendedData});
                    // }
                });

                $('#multi_clear').off('click').on('click', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    multiGrid.resetData([]);
                });

                $('#multi_confirm').off('click').on('click', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    let multiValues = getGridData(multiGrid);
                    if (multiValues.length === 0) {
                        multiValues = getAllGridData(multiGrid);
                    }
                    let multiParamKey = popupConfig.name;

                    const plainValues = multiValues
                        .map(item => item.value)
                        .filter(value => value !== undefined && value !== null && value !== '');

                    window.MULTI_SEARCH_PARAM[multiParamKey] = plainValues;

                    const count = plainValues.length;
                    const columns = popupConfig.autoSetColumns;

                    if (columns && columns.length > 0) {
                        if (columns.length >= 2) {
                            gfn_setValue(columns[0], 'multi');
                            gfn_setValue(columns[1], `${count}건`);
                        } else {
                            gfn_setValue(columns[0], `multi ${count}건`);
                        }
                    }

                    multiGrid.resetData([]);
                    $('#multiModal').modal("hide");
                });

                $(document).off('click', '.multi_search_delete_btn')
                    .on('click', '.multi_search_delete_btn', function () {
                        const rowKey = $(this).parent().attr('data-row-key');
                        multiGrid.removeRow(rowKey);
                    });

                // 그리드 데이터 세팅 + 강제 렌더링
                const bulkRows = [];
                const appendedData = {
                    delete: window.mainCommonMultiHeader.find(col => col.name === 'delete').header
                };
                // for (let i = 0; i < 500; i++) {
                //     gridAddRow(multiGrid,appendedData,false);
                //     // appendedDatas.push({...appendedData});
                // }

                // multiGrid.resetData(bulkRows);
                multiGrid.refreshLayout();

                // multiGrid.focusAt(0, 0); // 포커스 초기화
            });
        });
}

function storeMultiSearchPayload(multi) {
    let multiToken = null;

    $.ajax({
        type: 'POST',
        url: window.MULTI_SEARCH_STORE_ROUTE || '/multi-search/store',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            multi: JSON.stringify(multi)
        },
        async: false,
        success: function (response) {
            if (response && response.is_success) {
                multiToken = response.multi_token || response.multi_key;
            }
        },
        error: function (e) {
            console.log(e);
        }
    });

    return multiToken;
}

function addMultiSearchParamsToData(data) {
    const multi = {};

    for (const key in window.MULTI_SEARCH_PARAM) {
        const valueArray = window.MULTI_SEARCH_PARAM[key];

        if (Array.isArray(valueArray) && valueArray.length > 0) {
            multi[key] = valueArray;
        }
    }

    if (Object.keys(multi).length > 0) {
        const multiToken = storeMultiSearchPayload(multi);

        if (!multiToken) {
            show_toastr('Error!', 'Multi search condition could not be saved.', 'error');
            return data;
        }

        data.push({
            name: 'multi_token',
            value: multiToken
        });
    }

    return data;
}

function addMultiSearchParamsToForm(form) {
    const multi = {};

    for (const key in window.MULTI_SEARCH_PARAM) {
        const valueArray = window.MULTI_SEARCH_PARAM[key];

        if (Array.isArray(valueArray) && valueArray.length > 0) {
            multi[key] = valueArray;
        }
    }

    if (Object.keys(multi).length > 0) {
        const multiToken = storeMultiSearchPayload(multi);

        if (!multiToken) {
            show_toastr('Error!', 'Multi search condition could not be saved.', 'error');
            return;
        }

        const input = document.createElement("input");
        input.type  = "hidden";
        input.name  = "multi_token";
        input.value = multiToken;
        form.appendChild(input);
    }
}


/*****************************************************************
 * 북마크 목록 생성
 *****************************************************************/
function renderBookmarkList(bookmarks, check) {
    const $list = $("#my-list .sidenav_wrap");
    $list.empty(); // 기존 초기화

    // menu-list 내 모든 a를 캐싱
    const menuAnchors = $('#menu-list a');

    bookmarks.forEach(menu => {
        // menu.url과 href가 일치하는 a 태그를 찾음
        const matched = menuAnchors.filter(function () {
            return $(this).attr('href') === menu.url;
        });

        const title = matched.length > 0
            ? matched.text().trim()
            : menu.route_name; // 없으면 route_name fallback

        const targetAttr = check ? 'target="_blank"' : '';

        $list.append(`
            <li class="first_item">
                <a href="${menu.url}" ${targetAttr} style="padding: 12px 0 13px 23px">
                    ${title}
                </a>
            </li>
        `);
    });

    if (bookmarks.length > 0) {
        $('button.menu-tab[data-target="my-list"]').trigger('click');
    }
}

/*****************************************************************
 * 엑셀 다운로드시 콤마 제거 + 숫자 변환
 *****************************************************************/
function toNumber(v) {
    if (v == null || v === '') return v;
    if (typeof v === 'number') return v;
    const n = Number(String(v).replace(/,/g, ''));
    return Number.isNaN(n) ? v : n;
}

/**
 * TUI Grid 엑셀 다운로드 공통 바인딩
 *
 * @param {string} btnSelector - 예: '.btn-excel1'
 * @param {object} grid - TUI Grid 인스턴스 (예: grid1)
 * @param {object} opts
 * @param {string} opts.fileName - 엑셀 파일명
 * @param {string[]} [opts.numberCols] - 숫자 변환할 컬럼명 배열
 * @param {object} [opts.format] - xlsxOptions.format 에 넣을 객체 (예: { qty:'0.###', ... })
 * @param {boolean} [opts.includeHiddenColumns=false]
 * @param {boolean} [opts.useFormattedValue=false]
 * @param {boolean} [opts.onlySelected=false]
 * @param {function(row:object):void} [opts.beforeExportRow] - 행 단위 커스텀 가공 훅
 */
function bindGridExcelExport(btnSelector, grid, opts = {}) {
    const {
        fileName = 'export',
        numberCols = [],
        format = null,
        includeHiddenColumns = false,
        useFormattedValue = false,
        onlySelected = false,
        beforeExportRow = null,
    } = opts;

    // 중복 바인딩 방지(같은 selector에 여러번 bind되는 경우 방지)
    $(document).off('click.bindGridExcelExport', btnSelector);

    $(document).on('click.bindGridExcelExport', btnSelector, function () {
        const rows = grid.getData();

        rows.forEach(r => {
            // 1) 숫자 컬럼 변환
            numberCols.forEach(c => {
                if (r[c] != null) r[c] = toNumber(r[c]);
            });

            // 2) 추가 가공이 필요하면 훅 실행
            if (typeof beforeExportRow === 'function') {
                beforeExportRow(r);
            }
        });

        // 화면 formatter 유지하려면 resetData(네가 쓰던 방식)
        grid.resetData(rows);

        const exportOptions = {
            fileName,
            includeHiddenColumns,
            useFormattedValue,
            onlySelected,
        };

        // format 전달된 경우에만 xlsxOptions 구성
        if (format && typeof format === 'object') {
            exportOptions.xlsxOptions = { format };
        }

        grid.export('xlsx', exportOptions);
    });
}


/* =========================================================
 * Excel Export Common Constants
 * ========================================================= */

// 아래 Set에 지정한 컬럼만 Excel "숫자가 텍스트로 저장됨" 오류 표시 허용
// - 정확한 컬럼명 매칭 지원
// - normalize 컬럼명 매칭 지원
// - 토큰 분해 매칭 지원
//
// 예:
//   'erp_code'  -> erp_code / erpCode / erp-code 모두 매칭 가능
//   'item_code' -> item_code / itemCode / item-code 모두 매칭 가능
const AUTO_TEXT_WARNING_NAME_TOKENS = new Set([
    'erp_code',
    'lot5',
    'e1_code'
]);

/* =========================================================
 * Excel Export Binding
 * ========================================================= */
function _bindExcelExport(grid, config) {
    const sel = config.selector || '';
    let btnBase = config.excelBtn || null;

    if (!btnBase) {
        const numMatch = sel.match(/\d+$/);
        btnBase = numMatch ? 'btn-excel' + numMatch[0] : 'btn-excel-' + sel;
    }

    const btnSelectors = [];
    if (btnBase) {
        if (btnBase[0] === '.' || btnBase[0] === '#') {
            btnSelectors.push(btnBase);
        } else {
            btnSelectors.push('.' + btnBase);
            btnSelectors.push('#' + btnBase);
        }
    }

    const _colMetaMap = (function () {
        const sourceCols = Array.isArray(config.columns)
            ? config.columns
            : (Array.isArray(config.header) ? config.header : []);
        const map = new Map();

        sourceCols.forEach(function (col) {
            if (!col || !col.name) return;
            map.set(col.name, col);
        });

        return map;
    })();

    const _warningTokenColCache = new Map();

    function _getColMeta(name) {
        return _colMetaMap.get(name) || null;
    }

    /* =========================================================
     * 컬럼명 normalize / tokenize
     * ========================================================= */

    // ex)
    //   erpCode   -> erp_code
    //   erp-code  -> erp_code
    //   ERP Code  -> erp_code
    //   item.code -> item_code
    function _normalizeDetectKey(v) {
        return String(v || '')
            .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
            .replace(/[\s\-.]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_+|_+$/g, '')
            .trim()
            .toLowerCase();
    }

    function _tokenizeDetectText(v) {
        const normalized = _normalizeDetectKey(v);
        return normalized ? normalized.split('_') : [];
    }

    /* =========================================================
     * 컬럼 타입 판정
     * ========================================================= */

    // 오른쪽 정렬 / number_comma = 숫자 컬럼
    function _isNumCol(name) {
        const col = _getColMeta(name);
        if (!col) return false;

        if (typeof col.format === 'string' && col.format.startsWith('number_comma')) {
            return true;
        }

        if ((col.align || '').toLowerCase() === 'right') {
            return true;
        }

        return false;
    }

    function _getAlign(name) {
        const col = _getColMeta(name);
        return ((col && col.align) || '').toLowerCase();
    }

    function _isLeftAlignCol(name) {
        return _getAlign(name) === 'left';
    }

    /* =========================================================
     * 오류 표시 허용 컬럼 판정
     * ========================================================= */

    // 매칭 규칙:
    // 1) 원본 컬럼명 그대로 매칭
    // 2) normalize된 전체 컬럼명 매칭
    // 3) 토큰 단위 매칭
    //
    // 예:
    //   AUTO_TEXT_WARNING_NAME_TOKENS = ['erp_code']
    //
    //   erp_code  -> 원본/normalize 매칭
    //   erpCode   -> normalize 매칭
    //   erp-code  -> normalize 매칭
    //
    //   AUTO_TEXT_WARNING_NAME_TOKENS = ['code']
    //   erp_code  -> token 매칭
    function _isWarningTokenCol(name) {
        if (!name) return false;

        if (_warningTokenColCache.has(name)) {
            return _warningTokenColCache.get(name);
        }

        const rawName = String(name);
        const normalizedName = _normalizeDetectKey(rawName);
        const tokens = _tokenizeDetectText(rawName);

        const result =
            AUTO_TEXT_WARNING_NAME_TOKENS.has(rawName) ||
            AUTO_TEXT_WARNING_NAME_TOKENS.has(normalizedName) ||
            tokens.some(function (token) {
                return AUTO_TEXT_WARNING_NAME_TOKENS.has(token);
            });

        _warningTokenColCache.set(name, result);
        return result;
    }

    /* =========================================================
     * 값 변환
     * ========================================================= */

    function _toNum(v) {
        if (v == null || v === '') return 0;
        const n = Number(String(v).replace(/,/g, ''));
        return Number.isNaN(n) ? 0 : n;
    }

    function _toText(v) {
        if (v === null || v === undefined) return '';
        return String(v);
    }

    function _getFormattedValue(row, colName, fallback) {
        var value = fallback;

        try {
            if (
                row &&
                row.rowKey !== undefined &&
                row.rowKey !== null &&
                typeof grid.getFormattedValue === 'function'
            ) {
                var formatted = grid.getFormattedValue(row.rowKey, colName);
                if (formatted !== undefined && formatted !== null && formatted !== '') {
                    value = String(formatted);
                }
            }
        } catch (e) {}

        return value;
    }

    /* =========================================================
     * 컬럼 / 행 수집
     * ========================================================= */

    function _getExportCols() {
        const sourceCols = Array.isArray(config.columns)
            ? config.columns
            : (Array.isArray(config.header) ? config.header : []);

        let gridCols = [];

        try {
            gridCols = (grid.getColumns() || []).map(function (c) {
                return c.name;
            });
        } catch (e) {}

        const nameList = gridCols.length
            ? gridCols
            : sourceCols
                .filter(function (h) { return !h.hidden; })
                .map(function (h) { return h.name; });

        const result = [];

        result.push({
            name: '_number',
            header: 'No.',
            width: 60
        });

        nameList.forEach(function (name) {
            if (name === '_checked') return;

            const meta = sourceCols.find(function (h) {
                return h.name === name;
            });

            if (meta && !meta.hidden) {
                result.push(meta);
            }
        });

        return result;
    }

    function _collectRows(rows, out) {
        (rows || []).forEach(function (row) {
            out.push(row);
            if (Array.isArray(row._children) && row._children.length) {
                _collectRows(row._children, out);
            }
        });
        return out;
    }

    function _isGrouping() {
        const ctrl = window['__grpCtrl_' + sel];
        return ctrl && typeof ctrl.getGroupColumns === 'function' && ctrl.getGroupColumns().length > 0;
    }

    function _getOriginalBody() {
        const stateKey = '__grpState_' + sel;
        if (window[stateKey] && Array.isArray(window[stateKey].originalBody)) {
            return window[stateKey].originalBody;
        }

        try {
            return grid.getData();
        } catch (e) {
            return [];
        }
    }

    /* =========================================================
     * 셀 생성
     * ========================================================= */

    function _makeHeaderCell(text) {
        return {
            t: 's',
            v: String(text),
            z: '@',
            s: {
                numFmt: '@',
                alignment: {
                    horizontal: 'center',
                    vertical: 'center',
                    wrapText: true
                },
                font: {
                    bold: true,
                    sz: 10,
                    name: '맑은 고딕',
                    color: { rgb: '1F497D' }
                },
                fill: {
                    patternType: 'solid',
                    fgColor: { rgb: 'D9D9D9' }
                },
                border: {
                    top:    { style: 'thin', color: { rgb: 'C9C9C9' } },
                    bottom: { style: 'thin', color: { rgb: 'C9C9C9' } },
                    left:   { style: 'thin', color: { rgb: 'D0D0D0' } },
                    right:  { style: 'thin', color: { rgb: 'D0D0D0' } }
                }
            }
        };
    }

    function _makeTextCell(value, align) {
        const text = _toText(value);

        return {
            t: 's',
            v: text,
            z: '@',
            w: text,
            s: {
                numFmt: '@',
                alignment: {
                    horizontal: align || 'left',
                    vertical: 'center'
                }
            }
        };
    }

    function _makeNumberCell(value) {
        const num = _toNum(value);
        return {
            t: 'n',
            v: num,
            z: Number.isInteger(num) ? '#,##0' : '#,##0.############'
        };
    }

    /* =========================================================
     * ignoredErrors 처리
     *
     * 규칙:
     * - 오른쪽 정렬 숫자 컬럼은 ignoredErrors 대상 제외
     * - AUTO_TEXT_WARNING_NAME_TOKENS 컬럼은 ignoredErrors 대상 제외
     *   => 오류 표시 허용
     * - 그 외 문자열 컬럼은 ignoredErrors로 오류 숨김
     * ========================================================= */

    function _buildIgnoredErrorSqref(exportCols, totalExcelRows, headerRowCount = 1) {
        // 1행 = header
        // 2행부터 데이터/합계
        if (!totalExcelRows || totalExcelRows < 2) return '';

        if (!totalExcelRows || totalExcelRows < (headerRowCount + 1)) return '';
        const startRow = headerRowCount + 1;
        const endRow = totalExcelRows;
        const ranges = [];

        exportCols.forEach(function (col, idx) {
            if (!col || !col.name) return;
            if (col.name === '_number') return;

            // 숫자 컬럼은 오류 숨김 범위에서 제외
            if (_isNumCol(col.name)) return;

            // 경고 허용 컬럼도 오류 숨김 범위에서 제외
            if (_isWarningTokenCol(col.name)) return;

            const colRef = XLSX.utils.encode_col(idx);
            ranges.push(colRef + startRow + ':' + colRef + endRow);
        });

        return ranges.join(' ');
    }

    function _escapeXmlAttr(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function _upsertIgnoredErrorsXml(sheetXml, sqref) {
        if (!sheetXml) return sheetXml;

        sheetXml = sheetXml.replace(/<ignoredErrors[\s\S]*?<\/ignoredErrors>/g, '');

        if (!sqref) {
            return sheetXml;
        }

        const ignoredErrorsXml =
            '<ignoredErrors>' +
            '<ignoredError numberStoredAsText="1" sqref="' + _escapeXmlAttr(sqref) + '"/>' +
            '</ignoredErrors>';

        // extLst가 있으면 그 앞에 넣는 게 더 안전
        if (sheetXml.indexOf('<extLst') > -1) {
            return sheetXml.replace('<extLst', ignoredErrorsXml + '<extLst');
        }

        return sheetXml.replace('</worksheet>', ignoredErrorsXml + '</worksheet>');
    }

    function _downloadBlob(blob, fileName) {
        if (window.saveAs) {
            window.saveAs(blob, fileName);
            return;
        }

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();

        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 1000);
    }

    async function _writeWorkbookWithSelectiveIgnoredErrors(workbook, fileName, exportCols, totalExcelRows, headerRowCount = 1) {
        if (typeof JSZip === 'undefined') {
            throw new Error('JSZip is required for selective ignoredErrors handling.');
        }

        // 전역 ignoreEC 자동 숨김 비활성화
        const arrayBuffer = XLSX.write(workbook, {
            bookType: 'xlsx',
            type: 'array',
            ignoreEC: false
        });

        const zip = await JSZip.loadAsync(arrayBuffer);

        // 현재 함수는 단일 시트 생성 기준
        const sheetPath = 'xl/worksheets/sheet1.xml';
        const sheetFile = zip.file(sheetPath);

        if (!sheetFile) {
            throw new Error('worksheet xml not found: ' + sheetPath);
        }

        const sheetXml = await sheetFile.async('string');
        const sqref = _buildIgnoredErrorSqref(exportCols, totalExcelRows, headerRowCount);
        const updatedXml = _upsertIgnoredErrorsXml(sheetXml, sqref);

        zip.file(sheetPath, updatedXml);

        const blob = await zip.generateAsync({
            type: 'blob',
            compression: 'DEFLATE',
            compressionOptions: { level: 1 },
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        });

        _downloadBlob(blob, fileName);
    }

    /* =========================================================
     * Export
     * ========================================================= */

    /* =========================================================
     * Excel Progress Overlay
     * ========================================================= */
    var _EXCEL_CHUNK_SIZE    = 500;   // 청크당 처리 행 수 (브라우저 블록 방지)
    var _EXCEL_CSV_THRESHOLD = 10000; // 이 이상이면 XLSX 대신 CSV로 자동 전환

    function _ensureOverlayStyle() {
        if (document.getElementById('_excel_pg_style')) return;
        var s = document.createElement('style');
        s.id = '_excel_pg_style';
        s.textContent =
            '#_excel_pg_overlay{position:fixed;top:0;left:0;width:100%;height:100%;' +
                'background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center;}' +
            '._excel_pg_box{background:#fff;border-radius:12px;padding:40px 52px;min-width:420px;max-width:520px;' +
                'box-shadow:0 12px 40px rgba(0,0,0,.35);text-align:center;}' +
            '._excel_pg_title{font-size:17px;font-weight:700;color:#1a1a2e;margin-bottom:10px;letter-spacing:-.3px;}' +
            '._excel_pg_msg{font-size:13px;color:#666;margin-bottom:6px;min-height:18px;}' +
            '._excel_pg_count{font-size:14px;font-weight:700;color:#217346;margin-bottom:16px;min-height:20px;}' +
            '._excel_pg_track{background:#e9ecef;border-radius:100px;height:14px;overflow:hidden;margin-bottom:10px;}' +
            '._excel_pg_bar{height:100%;border-radius:100px;' +
                'background:linear-gradient(90deg,#217346 0%,#34a853 100%);transition:width .15s linear;}' +
            '._excel_pg_pct{font-size:13px;font-weight:600;color:#217346;}';
        document.head.appendChild(s);
    }

    // processed / total 은 선택적 — 행 처리 단계에서만 전달
    function _showExcelProgress(msg, pct, processed, total) {
        _ensureOverlayStyle();
        var ov = document.getElementById('_excel_pg_overlay');
        if (!ov) {
            ov = document.createElement('div');
            ov.id = '_excel_pg_overlay';
            ov.innerHTML =
                '<div class="_excel_pg_box">' +
                '<div class="_excel_pg_title">Excel 다운로드 중...</div>' +
                '<div class="_excel_pg_msg"></div>' +
                '<div class="_excel_pg_count"></div>' +
                '<div class="_excel_pg_track"><div class="_excel_pg_bar" style="width:0%"></div></div>' +
                '<div class="_excel_pg_pct">0%</div>' +
                '</div>';
            document.body.appendChild(ov);
        }
        var p = Math.min(100, Math.max(0, pct || 0));
        ov.querySelector('._excel_pg_msg').textContent = msg || '';
        ov.querySelector('._excel_pg_bar').style.width = p + '%';
        ov.querySelector('._excel_pg_pct').textContent = p + '%';

        var countEl = ov.querySelector('._excel_pg_count');
        if (processed != null && total != null) {
            countEl.textContent =
                processed.toLocaleString() + ' / ' + total.toLocaleString() + ' 건';
        } else if (total != null) {
            countEl.textContent = '전체 ' + total.toLocaleString() + ' 건';
        } else {
            countEl.textContent = '';
        }
    }

    function _hideExcelProgress() {
        var ov = document.getElementById('_excel_pg_overlay');
        if (ov && ov.parentNode) ov.parentNode.removeChild(ov);
    }

    /* =========================================================
     * Export
     * ========================================================= */

    // CSV 셀 이스케이프 (RFC 4180)
    function _toCsvCell(v) {
        var s = (v == null) ? '' : String(v);
        if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 ||
            s.indexOf('\r') !== -1 || s.indexOf('\n') !== -1) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    // 대용량 CSV 내보내기
    // getData() 를 전혀 호출하지 않고 getRowAt(j) 로 1행씩 접근
    // → 피크 JS 힙 = 청크 1개(500행) 분량만 유지
    async function _exportAsCsv(exportCols, grouping, total) {
        var fileName = withworksGetPetExcelFileName(sel, '.csv') ||
            ((config.excelFileName || sel).replace(/\.xlsx$/i, '') + '.csv');

        // BOM 을 독립 파트로 분리 — JS 문자열 연결 없음
        var blobParts = ['﻿'];

        // 헤더
        blobParts.push(
            exportCols.map(function (col) { return _toCsvCell(col.header || col.name); }).join(',')
            + '\r\n'
        );

        _showExcelProgress('CSV 변환 중...', 18, null, total);
        await new Promise(function (r) { setTimeout(r, 0); });

        var leafIndex = 0;
        // 합계 누산 — 별도 originalBody 루프 없음
        var sumAcc = {};
        exportCols.forEach(function (col) {
            if (_isNumCol(col.name)) sumAcc[col.name] = 0;
        });

        for (var i = 0; i < total; i += _EXCEL_CHUNK_SIZE) {
            var chunkEnd = Math.min(i + _EXCEL_CHUNK_SIZE, total);
            var chunkLines = [];

            for (var j = i; j < chunkEnd; j++) {
                var row = grid.getRowAt(j); // 1행씩 — getData() 50k 복사 없음
                if (!row) continue;
                var isGrpRow = row.grp_row_type === 'group';
                var rowVals = [];

                exportCols.forEach(function (col) {
                    var value = row[col.name];

                    if (col.name === '_number') {
                        if (grouping && isGrpRow) {
                            rowVals.push('');
                        } else {
                            leafIndex += 1;
                            rowVals.push(String(leafIndex));
                        }
                        return;
                    }

                    // 그룹 헤더 행이 아닌 경우에만 합계 누산
                    if (!isGrpRow && sumAcc.hasOwnProperty(col.name)) {
                        sumAcc[col.name] += _toNum(value);
                    }

                    value = _toText(value);
                    value = _getFormattedValue(row, col.name, value);
                    rowVals.push(_toCsvCell(value));
                });

                chunkLines.push(rowVals.join(','));
                row = null; // GC 힌트
            }

            // 500행씩 join → blobParts 추가 → 즉시 해제
            blobParts.push(chunkLines.join('\r\n') + '\r\n');
            chunkLines = null; // GC 힌트

            var pct = 18 + Math.floor((chunkEnd / total) * 68);
            _showExcelProgress('CSV 변환 중...', pct, chunkEnd, total);
            await new Promise(function (r) { setTimeout(r, 0); });
        }

        // 합계 행 (루프 중 누산한 값 사용 — 추가 reduce 없음)
        var firstCol = exportCols[0];
        var sumVals = exportCols.map(function (col) {
            if (col.name === (firstCol && firstCol.name)) return '합계';
            if (sumAcc.hasOwnProperty(col.name)) return String(sumAcc[col.name]);
            return '';
        });
        blobParts.push(sumVals.join(','));

        _showExcelProgress('파일 생성 중...', 92, total, total);
        await new Promise(function (r) { setTimeout(r, 0); });

        // blobParts 배열을 Blob 에 직접 전달 — 브라우저가 네이티브로 합침
        var blob = new Blob(blobParts, { type: 'text/csv;charset=utf-8;' });
        blobParts = null; // GC 힌트
        _downloadBlob(blob, fileName);

        _showExcelProgress('완료 (CSV)', 100, total, total);
        await new Promise(function (r) { setTimeout(r, 500); });
    }

    function _doExport(btnEl) {
        if (!grid) {
            if (typeof show_toastr === 'function') {
                show_toastr('Error!', '그리드가 아직 생성되지 않았습니다.', 'error');
            }
            return;
        }

        var $btn = (typeof window.jQuery === 'function' && btnEl) ? window.jQuery(btnEl) : null;
        var origHtml = $btn ? $btn.html() : null;
        if ($btn) {
            $btn.prop('disabled', true);
        }

        _showExcelProgress('데이터 준비 중...', 5);

        // setTimeout(0) 으로 현재 실행 컨텍스트를 끊어 브라우저가 오버레이를 먼저 렌더링하게 함
        setTimeout(function () { _runExportAsync($btn, origHtml); }, 0);
    }

    async function _runExportAsync($btn, origHtml) {
        try {
            _showExcelProgress('데이터 수집 중...', 10);
            await new Promise(function (r) { setTimeout(r, 0); });

            var exportCols = _getExportCols();
            var complexCols = Array.isArray(config.complex_columns) ? config.complex_columns : [];
            var grouping = _isGrouping();

            // getData() 를 호출하기 전에 행 수 먼저 확인
            var total = grid.getRowCount();

            // 대용량: CSV 자동 전환 — getData() 자체를 아예 호출하지 않음
            // getRowAt(j) 로 1행씩 꺼내므로 피크 메모리 = 청크 1개 분량
            if (total > _EXCEL_CSV_THRESHOLD) {
                await _exportAsCsv(exportCols, grouping, total);
                return;
            }

            // 소용량 XLSX: getData() 한 번만 호출
            var _rawData = grid.getData();
            var allRows  = _collectRows(_rawData, []);
            var _grpState = window['__grpState_' + sel];
            var originalBody = (_grpState && Array.isArray(_grpState.originalBody))
                ? _grpState.originalBody
                : _rawData;
            _rawData = null; // GC 힌트
            total = allRows.length; // tree 구조 포함 실제 행 수로 재설정

            _showExcelProgress('헤더 구성 중...', 18, null, total);
            await new Promise(function (r) { setTimeout(r, 0); });

            // 헤더만 먼저 worksheet 생성 — 전체 aoa 배열을 만들지 않아 메모리 절약
            var headerRows = null;
            var headerAoa = [];

            if (complexCols.length > 0) {
                headerRows = _buildComplexHeaderRows(exportCols, complexCols);
                headerAoa.push(headerRows.row1);
                headerAoa.push(headerRows.row2);
            } else {
                headerAoa.push(exportCols.map(function (col) {
                    return _makeHeaderCell(col.header || col.name);
                }));
            }

            var worksheet = XLSX.utils.aoa_to_sheet(headerAoa);
            var totalExcelRows = headerAoa.length;
            headerAoa = null; // GC 힌트

            // ── 청크 단위 행 처리 (브라우저 UI 블록 방지) ──────────────────
            // 전체 진행률에서 행 처리 구간: 20% ~ 65%
            var leafIndex = 0;

            for (var i = 0; i < total; i += _EXCEL_CHUNK_SIZE) {
                var chunkEnd = Math.min(i + _EXCEL_CHUNK_SIZE, total);
                var chunkAoa = [];

                for (var j = i; j < chunkEnd; j++) {
                    var row = allRows[j];
                    var isGrpRow = row.grp_row_type === 'group';
                    var rowArr = [];

                    exportCols.forEach(function (col) {
                        var value = row[col.name];

                        // 행번호
                        if (col.name === '_number') {
                            if (grouping && isGrpRow) {
                                rowArr.push(_makeTextCell('', 'center'));
                            } else {
                                leafIndex += 1;
                                rowArr.push({ t: 'n', v: leafIndex, z: '#,##0' });
                            }
                            return;
                        }

                        // 그룹행에서 "실제 그룹 기준 컬럼"은 무조건 문자열 유지
                        if (grouping && isGrpRow && row.grp_group_column === col.name) {
                            rowArr.push(_makeTextCell(_toText(value), 'left'));
                            return;
                        }

                        // 그룹행 숫자 컬럼(합계/평균)은 숫자로 유지
                        if (grouping && isGrpRow && _isNumCol(col.name)) {
                            rowArr.push(_makeNumberCell(value));
                            return;
                        }

                        // 그룹행 비숫자 컬럼은 문자열
                        if (grouping && isGrpRow) {
                            rowArr.push(_makeTextCell(_toText(value), 'left'));
                            return;
                        }

                        // 오른쪽 정렬 숫자 컬럼
                        if (_isNumCol(col.name)) {
                            var rawVal = _toText(value);
                            if (rawVal.indexOf('%') !== -1) {
                                rowArr.push(_makeTextCell(rawVal, 'right'));
                                return;
                            }
                            rowArr.push(_makeNumberCell(value));
                            return;
                        }

                        // 나머지는 문자열
                        value = _toText(value);
                        value = _getFormattedValue(row, col.name, value);
                        rowArr.push(_makeTextCell(value, _isLeftAlignCol(col.name) ? 'left' : 'center'));
                    });

                    chunkAoa.push(rowArr);
                }

                // 청크를 바로 worksheet에 추가하고 즉시 해제 (메모리 절약)
                XLSX.utils.sheet_add_aoa(worksheet, chunkAoa, { origin: -1 });
                totalExcelRows += chunkAoa.length;
                chunkAoa = null; // GC 힌트

                var rowPct = 20 + Math.floor((chunkEnd / total) * 45);
                _showExcelProgress('행 데이터 변환 중...', rowPct, chunkEnd, total);
                await new Promise(function (r) { setTimeout(r, 0); });
            }
            // ────────────────────────────────────────────────────────────────

            _showExcelProgress('합계 행 계산 중...', 68, total, total);
            await new Promise(function (r) { setTimeout(r, 0); });

            // 합계 행
            var sumRow = [];
            var firstCol = exportCols[0];

            exportCols.forEach(function (col) {
                if (col.name === (firstCol && firstCol.name)) {
                    sumRow.push(_makeTextCell('합계', 'left'));
                } else if (_isNumCol(col.name)) {
                    var sumVal = (originalBody || []).reduce(function (s, r) {
                        return s + _toNum(r[col.name]);
                    }, 0);
                    sumRow.push({
                        t: 'n',
                        v: sumVal,
                        z: Number.isInteger(sumVal) ? '#,##0' : '#,##0.############'
                    });
                } else {
                    sumRow.push(_makeTextCell('', 'left'));
                }
            });

            XLSX.utils.sheet_add_aoa(worksheet, [sumRow], { origin: -1 });
            totalExcelRows += 1;

            _showExcelProgress('워크시트 생성 중...', 76, total, total);
            await new Promise(function (r) { setTimeout(r, 0); });

            if (headerRows && Array.isArray(headerRows.merges)) {
                worksheet['!merges'] = headerRows.merges;
            }
            worksheet['!cols'] = exportCols.map(function (col) {
                return { wch: Math.max(12, Math.floor((col.width || 120) / 8)) };
            });
            worksheet['!rows'] = worksheet['!rows'] || [];
            if (headerRows) {
                worksheet['!rows'][0] = { hpx: 26 };
                worksheet['!rows'][1] = { hpx: 26 };
            } else {
                worksheet['!rows'][0] = { hpx: 26 };
            }

            var workbook = XLSX.utils.book_new();
            var sheetName = config.excelSheet || 'Sheet1';
            var fileName = withworksGetPetExcelFileName(sel, '.xlsx') ||
                config.excelFileName ||
                (sel + '.xlsx');

            XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);

            _showExcelProgress('파일 압축 및 다운로드 중...', 88, total, total);
            await new Promise(function (r) { setTimeout(r, 0); });

            await _writeWorkbookWithSelectiveIgnoredErrors(
                workbook,
                fileName,
                exportCols,
                totalExcelRows,
                complexCols.length > 0 ? 2 : 1
            );

            _showExcelProgress('완료', 100, total, total);
            await new Promise(function (r) { setTimeout(r, 500); });

        } catch (err) {
            console.error('[_bindExcelExport]', err);
            if (typeof show_toastr === 'function') {
                show_toastr('Error!', 'Excel 다운로드 중 오류가 발생했습니다.', 'error');
            }
        } finally {
            _hideExcelProgress();
            if ($btn) {
                $btn.prop('disabled', false).html(origHtml);
            }
        }
    }

    function _buildComplexHeaderRows(exportCols, complexColumns) {
        const row1 = [];
        const row2 = [];
        const merges = [];

        const exportColNames = exportCols.map(col => col.name);

        // child -> group 매핑
        const childToGroupMap = new Map();
        (complexColumns || []).forEach(group => {
            const visibleChildren = (group.childNames || []).filter(name => exportColNames.includes(name));

            if (!visibleChildren.length) return;

            visibleChildren.forEach(child => {
                childToGroupMap.set(child, {
                    header: group.header,
                    visibleChildren
                });
            });
        });

        exportCols.forEach((col, colIndex) => {
            const groupInfo = childToGroupMap.get(col.name);

            // 일반 컬럼
            if (!groupInfo) {
                row1.push(_makeHeaderCell(col.header || col.name));
                row2.push(_makeHeaderCell(''));

                merges.push({
                    s: { r: 0, c: colIndex },
                    e: { r: 1, c: colIndex }
                });
                return;
            }

            const firstChild = groupInfo.visibleChildren[0];
            const lastChild = groupInfo.visibleChildren[groupInfo.visibleChildren.length - 1];
            const firstIdx = exportCols.findIndex(c => c.name === firstChild);
            const lastIdx = exportCols.findIndex(c => c.name === lastChild);

            // 안전장치
            if (firstIdx < 0 || lastIdx < 0 || firstIdx > lastIdx) {
                row1.push(_makeHeaderCell(col.header || col.name));
                row2.push(_makeHeaderCell(''));

                merges.push({
                    s: { r: 0, c: colIndex },
                    e: { r: 1, c: colIndex }
                });
                return;
            }

            if (col.name === firstChild) {
                row1.push(_makeHeaderCell(groupInfo.header));

                if (firstIdx !== lastIdx) {
                    merges.push({
                        s: { r: 0, c: firstIdx },
                        e: { r: 0, c: lastIdx }
                    });
                }
            } else {
                row1.push(_makeHeaderCell(''));
            }

            row2.push(_makeHeaderCell(col.header || col.name));
        });

        return { row1, row2, merges };
    }

    /* =========================================================
     * Button Binding
     * ========================================================= */

    function _bindButtons() {
        if (!btnSelectors.length) return;

        if (typeof window.jQuery === 'function') {
            var $doc = window.jQuery(document);
            var eventNs = '.excelExport_' + String(sel || 'grid').replace(/[^a-zA-Z0-9_-]/g, '_');

            btnSelectors.forEach(function (btnSel) {
                $doc.off('click' + eventNs, btnSel);
                $doc.on('click' + eventNs, btnSel, function (e) {
                    e.preventDefault();
                    _doExport(this);
                });
            });
            return;
        }

        btnSelectors.forEach(function (btnSel) {
            var nodes = document.querySelectorAll(btnSel);

            nodes.forEach(function (node) {
                if (node.__excelExportBound) return;
                node.__excelExportBound = true;

                node.addEventListener('click', function (e) {
                    e.preventDefault();
                    _doExport(this);
                });
            });
        });
    }

    _bindButtons();

    return {
        export: _doExport
    };
}



// ================================================================
// ▼▼▼  그리드 그룹핑 확장 기능  ▼▼▼
// ----------------------------------------------------------------
// handle_draw_grid() 의 grouping 옵션으로 활성화
//
// 사용 예:
//   var grid1 = handle_draw_grid({
//       selector        : 'grid1',
//       header          : header,
//       body            : body,
//       footer          : {},
//       grouping        : true,          // ← 그룹핑 활성화
//       groupSortColumn : 'total_amt',   // ← 그룹 정렬 기준 컬럼 (선택)
//   });
//
// ★ v3 변경점 (OOM 완전 수정)
//   · 그리드 재생성(destroy/create) 완전 제거
//   · grid.resetData(flatData) 방식으로 데이터만 교체
//   · document 레벨 이벤트 누적 없음 (bind_grid_events 미호출)
//   · .tui-grid-container 에 pointer-events:none 으로 TUI row-drag 차단
//   · zone mouseenter/mouseleave 1회만 등록
// ================================================================
(function () {

    /* ── CSS 동적 주입 (최초 1회) ─────────────────────────── */
    function _inject_grouping_css() {
        if (document.getElementById('__tui_grp_css')) return;
        const css = `
.tui-grp-zone {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #f8f9fa;
    border: 1.5px dashed #ced4da;
    border-radius: 6px;
    margin-bottom: 5px;
    min-height: 38px;
    flex-wrap: wrap;
    box-sizing: border-box;
}
.tui-grp-label {
    font-size: 12px;
    font-weight: 600;
    color: #6c757d;
    white-space: nowrap;
    padding-right: 4px;
    border-right: 1px solid #dee2e6;
    margin-right: 2px;
}
.tui-grp-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    flex: 1;
    align-items: center;
}
.tui-grp-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    background: #0d6efd;
    color: #fff;
    border-radius: 12px;
    font-size: 12px;
    user-select: none;
}
.tui-grp-chip button {
    background: none;
    border: none;
    color: rgba(255,255,255,.85);
    cursor: pointer;
    padding: 0 0 1px;
    font-size: 14px;
    line-height: 1;
    transition: color .15s;
}
.tui-grp-chip button:hover { color: #fff; }
.tui-grp-empty {
    font-size: 12px;
    color: #adb5bd;
}
.tui-grp-clear {
    font-size: 12px !important;
    padding: 2px 10px !important;
    white-space: nowrap;
    margin-left: auto;
}
.tui-grp-drag-ghost {
    position: fixed;
    z-index: 9999;
    background: #0d6efd;
    color: #fff;
    padding: 3px 12px;
    border-radius: 4px;
    font-size: 12px;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,.25);
}
.tui-grp-drag-over {
    border-color: #0d6efd !important;
    background: #e8f0fe !important;
}
body.tui-grp-dragging,
body.tui-grp-dragging * { cursor: grabbing !important; user-select: none !important; }
th.tui-grp-header-draggable { cursor: grab; }
/* ── 컬럼 이동 (useColumnReorder) ───────────────── */
th.tui-grp-col-drop-target {
    background-color: rgba(13,110,253,.12) !important;
    box-shadow: inset 0 0 0 2px #0d6efd;
}
.tui-grp-col-indicator {
    position: fixed;
    width: 3px;
    background: #0d6efd;
    border-radius: 2px;
    z-index: 9998;
    pointer-events: none;
    box-shadow: 0 0 6px rgba(13,110,253,.55);
    top: 0; bottom: 0;
    display: none;
}
.tui-grp-col-move-ghost {
    position: fixed;
    z-index: 9999;
    background: #198754;
    color: #fff;
    padding: 3px 12px;
    border-radius: 4px;
    font-size: 12px;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,.25);
    white-space: nowrap;
}
/* ── 그룹 행 스타일 (depth별) ──────────────────────────────
   TUI Grid의 addRowClassName()은 tr이 아닌 td에 클래스를 붙임.
   따라서 selector는 td.tui-grp-row-dN 으로 작성해야 함.
   ─────────────────────────────────────────────────────────── */
td.tui-grp-row-d1,
td.tui-grp-row-d1 .tui-grid-cell-content {
    background-color: #d0e4ff !important;
    font-weight: 700 !important;
}
td.tui-grp-row-d2,
td.tui-grp-row-d2 .tui-grid-cell-content {
    background-color: #e4eefc !important;
    font-weight: 700 !important;
}
td.tui-grp-row-d3,
td.tui-grp-row-d3 .tui-grid-cell-content {
    background-color: #eff4fc !important;
    font-weight: 700 !important;
}
/* ── 일반(leaf) 데이터 행: bold 번짐 차단 ── */
td.tui-grid-cell:not(.tui-grp-row-d1):not(.tui-grp-row-d2):not(.tui-grp-row-d3) .tui-grid-cell-content {
    font-weight: normal !important;
}
`;
        const el = document.createElement('style');
        el.id = '__tui_grp_css';
        el.textContent = css;
        document.head.appendChild(el);
    }

    /* ── 유틸리티 ───────────────────────────────────────────── */
    function _grp_toNum(v) {
        if (v === null || v === undefined || v === '') return 0;
        return Number(String(v).replace(/,/g, '')) || 0;
    }
    function _grp_avg(items, colName, config) {
        const nums = items
            .map(r => _grp_toNum(r[colName]))
            .filter(v => !Number.isNaN(v));

        if (!nums.length) return 0;

        const avg = nums.reduce((s, v) => s + v, 0) / nums.length;
        const scale = Number.isInteger(config?.groupingAvgScale) ? config.groupingAvgScale : 2;

        return Number(avg.toFixed(scale));
    }
    function _grp_isSummable(name, config) {
        const manualCols = Array.isArray(config?.groupingSummaryColumns)
            ? config.groupingSummaryColumns
            : [];

        if (manualCols.includes(name)) {
            return true;
        }

        return /amt|qty|price|cost|cnt/i.test(name || '');
    }
    function _grp_isAverageable(name, config) {
        const avgCols = Array.isArray(config?.groupingAvgColumns)
            ? config.groupingAvgColumns
            : [];

        return avgCols.includes(name);
    }
    function _grp_getHeaderText(name, header) {
        const col = header.find(c => c.name === name);
        return col ? (col.header || col.name) : name;
    }

    function _grp_compareValues(a, b, dir = 'desc') {
        const aNum = _grp_toNum(a);
        const bNum = _grp_toNum(b);

        const aIsNum = !(a === null || a === undefined || a === '') && !Number.isNaN(aNum);
        const bIsNum = !(b === null || b === undefined || b === '') && !Number.isNaN(bNum);

        if (aIsNum && bIsNum) {
            return dir === 'asc' ? aNum - bNum : bNum - aNum;
        }

        const aStr = (a ?? '').toString().trim();
        const bStr = (b ?? '').toString().trim();

        if (dir === 'asc') {
            return aStr.localeCompare(bStr, 'ko', { numeric: true, sensitivity: 'base' });
        }
        return bStr.localeCompare(aStr, 'ko', { numeric: true, sensitivity: 'base' });
    }

    function _grp_sortDetailRows(rows, sortCol, dir = 'desc') {
        return (rows || []).slice().sort((r1, r2) => {
            return _grp_compareValues(r1?.[sortCol], r2?.[sortCol], dir);
        });
    }

    /* ── 그룹 데이터 빌드 ─────────────────────────────────── */
    function _grp_groupRows(rows, cols, depth, header, config) {
        const col  = cols[0];
        const rest = cols.slice(1);
        const map  = new Map();

        (rows || []).forEach(r => {
            const k = (r[col] !== undefined && r[col] !== null) ? String(r[col]) : '(빈값)';
            if (!map.has(k)) map.set(k, []);
            map.get(k).push(r);
        });

        let entries = Array.from(map.entries());

        // 그룹 자체 정렬
        if (config && config.groupSortColumn) {
            const sortCol = config.groupSortColumn;

            entries.sort((a, b) => {
                const aItems = a[1] || [];
                const bItems = b[1] || [];

                const aSum = aItems.reduce((s, r) => s + _grp_toNum(r[sortCol]), 0);
                const bSum = bItems.reduce((s, r) => s + _grp_toNum(r[sortCol]), 0);

                return bSum - aSum; // 내림차순
            });
        }

        const result = [];

        entries.forEach(([k, rawItems]) => {
            // 그룹 내부 자식 row도 정렬
            const items = (config && config.groupSortColumn)
                ? _grp_sortDetailRows(rawItems, config.groupSortColumn, 'desc')
                : rawItems.slice();

            const groupRow = {};
            header.forEach(c => { groupRow[c.name] = ''; });

            groupRow[col]        = '▶ ' + _grp_getHeaderText(col, header) + ' : ' + k + ' (' + items.length + '건)';
            groupRow._rowType    = 'group';
            groupRow._groupDepth = depth;

            // export에서도 살아남는 메타
            groupRow.grp_row_type      = 'group';
            groupRow.grp_group_column  = col;
            groupRow.grp_group_value   = k;

            header.forEach(c => {
                if (_grp_isAverageable(c.name, config)) {
                    groupRow[c.name] = _grp_avg(items, c.name, config);
                    return;
                }

                if (_grp_isSummable(c.name, config)) {
                    groupRow[c.name] = items.reduce((s, r) => s + _grp_toNum(r[c.name]), 0);
                }
            });

            groupRow._children = rest.length
                ? _grp_groupRows(items, rest, depth + 1, header, config)
                : items;

            result.push(groupRow);
        });

        return result;
    }

    /* ── tree → flat 변환 (그리드 재생성 없이 resetData 사용) */
    function _grp_flattenRows(rows, depth) {
        const out = [];
        (rows || []).forEach(r => {
            if (r._rowType === 'group') {
                const gr = Object.assign({}, r);
                gr._flatDepth = depth;
                const children = gr._children;
                delete gr._children;
                out.push(gr);
                if (children && children.length) {
                    _grp_flattenRows(children, depth + 1).forEach(c => out.push(c));
                }
            } else {
                // leaf 행: 그룹 내부 속성 모두 제거 (_flatDepth 포함)
                const lr = Object.assign({}, r);
                ['_rowType','_groupDepth','_flatDepth','_children',
                    '_groupColumn','_groupValue'].forEach(k => {
                    if (k in lr) delete lr[k];
                });
                out.push(lr);
            }
        });
        return out;
    }

    /* ── 메인: 그룹핑 기능 설정 ────────────────────────────── */
    window._setup_grid_grouping = function (currentGrid, config) {
        _inject_grouping_css();

        const selector  = config.selector;
        const header = Array.isArray(config.columns)
            ? config.columns.filter(c => c && !c.childNames)
            : [];
        const stateKey  = '__grpState_' + selector;

        /* ── 상태 초기화 ──────────────────────────────────────────────
           [v7 NEW] 재진입 시 originalBody = null 리셋
           · 다음 드래그 때 현재 그리드에서 직접 캡처하기 위함
           · groupColumns는 유지 (검색 결과 후에도 그룹 칩 유지)
           ──────────────────────────────────────────────────────────── */
        if (!window[stateKey]) {
            window[stateKey] = {
                groupColumns: Array.isArray(config.groupingColumns) ? config.groupingColumns.slice() : [],
                originalBody: null
            };
        } else {
            // 기존 값이 없을 때만 블레이드 기본값으로 초기화
            if (
                (!Array.isArray(window[stateKey].groupColumns) || window[stateKey].groupColumns.length === 0) &&
                Array.isArray(config.groupingColumns) &&
                config.groupingColumns.length > 0
            ) {
                window[stateKey].groupColumns = config.groupingColumns.slice();
            }
        }

        window[stateKey].originalBody = null;

        /* ── 내부 속성 제거 헬퍼 ── */
        const _GRP_INTERNAL_KEYS = ['_rowType', '_groupDepth', '_flatDepth', '_children',
            '_groupColumn', '_groupValue', '_attributes',
            'rowKey', '__rowNum'];
        function cleanRow(r) {
            const out = Object.assign({}, r);
            _GRP_INTERNAL_KEYS.forEach(k => { if (k in out) delete out[k]; });
            return out;
        }

        function getOriginalBody() { return window[stateKey].originalBody || []; }
        function getGC()     { return window[stateKey].groupColumns || []; }
        function setGC(cols) { window[stateKey].groupColumns = Array.isArray(cols) ? cols : []; }

        /* ── [v7 NEW] 최초 그룹핑 직전에 그리드에서 직접 원본 캡처 ──
           config.body 의존을 제거: 라이브 그리드 데이터를 사용하므로
           검색/필터 후에도 올바른 원본을 저장함.
           originalBody !== null 이면 이미 캡처된 것이므로 스킵.       */
        function captureOriginalBody() {
            if (window[stateKey].originalBody !== null) return;
            try {
                const rows = currentGrid.getData();
                window[stateKey].originalBody = JSON.parse(JSON.stringify(
                    rows.map(r => cleanRow(r))
                ));
            } catch (e) {
                /* 폴백: config.body 사용 */
                window[stateKey].originalBody = Array.isArray(config.body)
                    ? JSON.parse(JSON.stringify(config.body.map(r => cleanRow(r))))
                    : [];
            }
        }

        /* ── 데이터 빌드 ── */
        function buildFlatData() {
            const gc   = getGC();
            const body = getOriginalBody();
            if (!gc.length) {
                return body.map(r => cleanRow(r));   // 원본 복원
            }
            const grouped = _grp_groupRows(body, gc, 1, header, config);
            return _grp_flattenRows(grouped, 0);
        }

        /* ── ★ 핵심: 그리드 재생성 없이 데이터만 교체 ──
           [v7 NEW] resetData([]) 이중 호출 제거 → 단일 resetData(cleanFlat)
           [v7 NEW] addRowClassName → requestAnimationFrame 지연 처리
           [v7 NEW] 완전 해제 시 originalBody = null 리셋               */
        /* ── summary(footer) 고정: originalBody 기준으로 재설정 ──────
           그룹핑 후 resetData(cleanFlat)를 호출하면 TUI Grid가
           그룹행까지 포함해 summary를 재계산 → 합계가 부풀려짐.
           해결: resetData 직후 setSummaryColumnContent 로
           originalBody 기반 고정 template을 덮어씌워 재계산 차단. */
        function _fixSummary(gc) {
            if (!config.footer || typeof currentGrid.setSummaryColumnContent !== 'function') return;

            const origBody = getOriginalBody();   // 원본 데이터
            const footerCfg = config.footer;      // { cc_amt: { template: fn }, … }

            Object.keys(footerCfg).forEach(function (colName) {
                const origTpl = footerCfg[colName] && footerCfg[colName].template;
                if (typeof origTpl !== 'function') return;

                if (gc.length === 0) {
                    /* 그룹핑 해제 → originalBody 기반 합계 계산 후 template 복원 */
                    const relSum = origBody.reduce(function (s, row) {
                        const v = Number(String(row[colName] || '0').replace(/,/g, ''));
                        return s + (Number.isNaN(v) ? 0 : v);
                    }, 0);
                    let relText;
                    try { relText = origTpl({ sum: relSum, avg: 0, min: relSum, max: relSum, cnt: origBody.length }); }
                    catch(_) { relText = String(relSum); }
                    const relFrozen = relText;
                    currentGrid.setSummaryColumnContent(colName, { template: function() { return relFrozen; }, useAutoSummary: false });
                } else {
                    /* 그룹핑 활성 → originalBody 기준으로 합계 직접 계산 후 고정 */
                    const colSum = origBody.reduce(function (s, row) {
                        const v = Number(String(row[colName] || '0').replace(/,/g, ''));
                        return s + (Number.isNaN(v) ? 0 : v);
                    }, 0);

                    /* 원본 template에 고정 valueMap을 넘겨 포맷을 유지 */
                    let fixedText;
                    try { fixedText = origTpl({ sum: colSum, avg: 0, min: colSum, max: colSum, cnt: origBody.length }); }
                    catch(_) { fixedText = String(colSum); }

                    const frozen = fixedText; // 클로저 캡처
                    currentGrid.setSummaryColumnContent(colName, { template: function() { return frozen; }, useAutoSummary: false });
                }
            });
        }

        function applyGroupData() {
            const gc   = getGC();
            const flat = buildFlatData();

            const groupMeta = [];
            const cleanFlat = flat.map((row, idx) => {
                const out = {};
                Object.keys(row).forEach(k => {
                    if (!k.startsWith('_')) out[k] = row[k];
                });
                if (row._rowType === 'group') {
                    groupMeta.push({ idx, depth: Math.min(row._groupDepth || 1, 3) });
                }
                return out;
            });

            try {
                currentGrid.resetData(cleanFlat);
            } catch (e) {
                console.warn('[grp] resetData 오류:', e);
                return;
            }

            /* summary 고정: resetData 직후 원본 기준으로 덮어씌움 */
            _fixSummary(gc);

            /* CSS 클래스 적용: DOM 렌더링 완료 후 처리 */
            if (groupMeta.length) {
                requestAnimationFrame(() => {
                    groupMeta.forEach(({ idx, depth }) => {
                        try { currentGrid.addRowClassName(idx, 'tui-grp-row-d' + depth); } catch (_) {}
                    });
                });
            }

            /* 완전 해제 시 originalBody 리셋 → 다음 드래그 때 새로 캡처 */
            if (!gc.length) {
                window[stateKey].originalBody = null;
            }
        }

        /* ── 드롭존 UI ── */
        function ensureDropZone() {
            const gridEl = document.getElementById(selector);
            if (!gridEl) return null;
            let zone = document.querySelector('.tui-grp-zone[data-for="' + selector + '"]');
            if (!zone) {
                zone = document.createElement('div');
                zone.className = 'tui-grp-zone';
                zone.setAttribute('data-for', selector);
                zone.innerHTML = `
                    <span class="tui-grp-label">그룹핑</span>
                    <div class="tui-grp-chips"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary tui-grp-clear">초기화</button>
                `;
                gridEl.parentNode.insertBefore(zone, gridEl);

                /* zone hover - 최초 1회만 등록 */
                zone.addEventListener('mouseenter', function () {
                    if (document.body.classList.contains('tui-grp-dragging'))
                        zone.classList.add('tui-grp-drag-over');
                });
                zone.addEventListener('mouseleave', function () {
                    zone.classList.remove('tui-grp-drag-over');
                });
            }
            return zone;
        }

        /* ── 칩 렌더링 ── */
        function renderChips(zone) {
            if (!zone) return;
            const chipsEl = zone.querySelector('.tui-grp-chips');
            if (!chipsEl) return;
            const gc = getGC();
            chipsEl.innerHTML = '';
            if (!gc.length) {
                chipsEl.innerHTML = '<span class="tui-grp-empty">헤더를 여기로 드래그하면 그룹핑됩니다.</span>';
                return;
            }
            gc.forEach((colName, idx) => {
                const chip = document.createElement('div');
                chip.className = 'tui-grp-chip';
                chip.innerHTML = `<span>${idx + 1}차: ${_grp_getHeaderText(colName, header)}</span>
                                  <button type="button" title="그룹 해제">✕</button>`;
                chip.querySelector('button').addEventListener('click', () => {
                    setGC(getGC().filter(c => c !== colName));
                    renderChips(zone);
                    applyGroupData();
                });
                chipsEl.appendChild(chip);
            });
        }

        /* ── 초기화 버튼 ── */
        function bindClearBtn(zone) {
            if (!zone) return;
            const btn = zone.querySelector('.tui-grp-clear');
            if (!btn) return;
            /* [FIX-4] _grpClearBound 가드 제거: 재진입 시 새 applyGroupData 클로저로 재바인딩
               이전: 첫 번째 호출의 applyGroupData(구 currentGrid)를 계속 사용 → 오류
               이후: _setup_grid_grouping 재호출마다 최신 currentGrid로 갱신 */
            $(btn).off('click.grp').on('click.grp', function () {
                setGC([]);
                renderChips(zone);
                applyGroupData();
            });
        }

        /* ── 헤더 커서 ── */
        function syncHeaderCursor() {
            const gridEl = document.getElementById(selector);
            if (!gridEl) return;
            const tip = config.useColumnReorder
                ? '드래그 → 그룹존: 그룹핑 | 다른 헤더: 컬럼 이동'
                : '드래그: 그룹핑';
            gridEl.querySelectorAll('th[data-column-name].tui-grid-cell-header').forEach(cell => {
                cell.classList.add('tui-grp-header-draggable');
                cell.title = tip;
            });
        }

        /* ── ★ 핵심: 헤더 드래그 → 드롭존(그룹핑) 또는 다른 헤더(컬럼 이동) ── */
        function bindHeaderDrag(zone) {
            if (!zone) return;
            const gridEl = document.getElementById(selector);
            if (!gridEl) return;
            const canColMove = !!config.useColumnReorder; // 컬럼 이동 옵션

            gridEl.querySelectorAll('th[data-column-name].tui-grid-cell-header').forEach(cell => {
                const colName = cell.getAttribute('data-column-name');
                if (!colName || cell.dataset.grpBound === 'Y') return;
                cell.dataset.grpBound = 'Y';

                /* native HTML5 drag 완전 차단 */
                cell.addEventListener('dragstart', e => e.preventDefault());

                /* capture 모드로 mousedown 등록 → TUI Grid보다 먼저 처리 */
                cell.addEventListener('mousedown', function (downEv) {
                    if (downEv.button !== 0) return;
                    if (downEv.target.closest('.tui-grid-btn-sorting'))          return;
                    if (downEv.target.closest('.tui-grid-column-resize-handle')) return;

                    const startX = downEv.clientX, startY = downEv.clientY;
                    let moved = false, dragging = false, ghost = null, bodyBlocked = false;
                    let colIndicator  = null;   // 컬럼 이동 수직 인디케이터
                    let dropTargetCell = null;  // 현재 드롭 대상 헤더
                    let dropInsertBefore = true;
                    const label = _grp_getHeaderText(colName, header);

                    /* grid container 차단 */
                    function blockGridContainer() {
                        if (bodyBlocked) return;
                        bodyBlocked = true;
                        const c = gridEl.querySelector('.tui-grid-container');
                        if (c) c.style.pointerEvents = 'none';
                    }
                    function restoreGridContainer() {
                        const c = gridEl.querySelector('.tui-grid-container');
                        if (c) c.style.pointerEvents = '';
                    }

                    /* 고스트 생성/이동/타입 전환 */
                    function mkGhost(x, y, isColMove) {
                        ghost = document.createElement('div');
                        ghost.className = isColMove ? 'tui-grp-col-move-ghost' : 'tui-grp-drag-ghost';
                        ghost.textContent = isColMove ? ('↔ ' + label) : label;
                        document.body.appendChild(ghost);
                        mvGhost(x, y);
                    }
                    function mvGhost(x, y) {
                        if (!ghost) return;
                        ghost.style.left = (x + 14) + 'px';
                        ghost.style.top  = (y + 14) + 'px';
                    }
                    function setGhostType(isColMove) {
                        if (!ghost) return;
                        ghost.className    = isColMove ? 'tui-grp-col-move-ghost' : 'tui-grp-drag-ghost';
                        ghost.textContent  = isColMove ? ('↔ ' + label) : label;
                    }

                    /* 컬럼 이동 수직 인디케이터 */
                    function showColIndicator(targetTh, before) {
                        if (!colIndicator) {
                            colIndicator = document.createElement('div');
                            colIndicator.className = 'tui-grp-col-indicator';
                            document.body.appendChild(colIndicator);
                        }
                        const r  = targetTh.getBoundingClientRect();
                        const tr = (targetTh.closest('tr') || targetTh).getBoundingClientRect();
                        colIndicator.style.left    = (before ? r.left - 1 : r.right - 1) + 'px';
                        colIndicator.style.top     = tr.top + 'px';
                        colIndicator.style.height  = tr.height + 'px';
                        colIndicator.style.display = 'block';
                    }
                    function hideColIndicator() {
                        if (colIndicator) colIndicator.style.display = 'none';
                    }

                    /* 헤더 셀 위치 탐색 (pointerEvents:none 회피) */
                    function findHeaderCell(x, y) {
                        const headers = gridEl.querySelectorAll('th[data-column-name].tui-grid-cell-header');
                        for (const th of headers) {
                            const r = th.getBoundingClientRect();
                            if (x >= r.left && x <= r.right && y >= r.top && y <= r.bottom) return th;
                        }
                        return null;
                    }

                    /* 헤더 하이라이트 */
                    function clearHeaderHighlight() {
                        gridEl.querySelectorAll('th.tui-grp-col-drop-target').forEach(th =>
                            th.classList.remove('tui-grp-col-drop-target'));
                    }

                    function cleanup() {
                        document.removeEventListener('mousemove', onMove, true);
                        document.removeEventListener('mouseup',   onUp,   true);
                        if (ghost && ghost.parentNode) ghost.parentNode.removeChild(ghost);
                        ghost = null;
                        if (colIndicator && colIndicator.parentNode) colIndicator.parentNode.removeChild(colIndicator);
                        colIndicator = null;
                        document.body.classList.remove('tui-grp-dragging');
                        zone.classList.remove('tui-grp-drag-over');
                        clearHeaderHighlight();
                        dropTargetCell = null;
                        restoreGridContainer();
                    }

                    function onMove(ev) {
                        if (!ev.isTrusted) return;
                        const dx = Math.abs(ev.clientX - startX);
                        const dy = Math.abs(ev.clientY - startY);
                        if (!moved && (dx > 4 || dy > 4)) {
                            moved = true; dragging = true;
                            document.body.classList.add('tui-grp-dragging');
                            blockGridContainer();
                        }
                        if (!dragging) return;
                        ev.stopImmediatePropagation();
                        ev.preventDefault();

                        /* 그룹존 체크 */
                        const zr = zone.getBoundingClientRect();
                        const inZone = ev.clientX >= zr.left && ev.clientX <= zr.right &&
                            ev.clientY >= zr.top  && ev.clientY <= zr.bottom;
                        zone.classList.toggle('tui-grp-drag-over', inZone);

                        if (inZone) {
                            /* 그룹존 위 */
                            hideColIndicator();
                            clearHeaderHighlight();
                            dropTargetCell = null;
                            if (!ghost) mkGhost(ev.clientX, ev.clientY, false);
                            else        { setGhostType(false); mvGhost(ev.clientX, ev.clientY); }
                        } else if (canColMove) {
                            /* 컬럼 이동 모드: 다른 헤더 탐색 */
                            const targetTh  = findHeaderCell(ev.clientX, ev.clientY);
                            const targetCol = targetTh ? targetTh.getAttribute('data-column-name') : null;
                            if (targetTh && targetCol && targetCol !== colName) {
                                const r = targetTh.getBoundingClientRect();
                                dropInsertBefore = ev.clientX < r.left + r.width * 0.5;
                                dropTargetCell   = targetTh;
                                clearHeaderHighlight();
                                targetTh.classList.add('tui-grp-col-drop-target');
                                showColIndicator(targetTh, dropInsertBefore);
                                if (!ghost) mkGhost(ev.clientX, ev.clientY, true);
                                else        { setGhostType(true); mvGhost(ev.clientX, ev.clientY); }
                            } else {
                                clearHeaderHighlight();
                                hideColIndicator();
                                dropTargetCell = null;
                                if (!ghost) mkGhost(ev.clientX, ev.clientY, false);
                                else        { setGhostType(false); mvGhost(ev.clientX, ev.clientY); }
                            }
                        } else {
                            /* useColumnReorder: false → 고스트만 이동 */
                            if (!ghost) mkGhost(ev.clientX, ev.clientY, false);
                            else        { setGhostType(false); mvGhost(ev.clientX, ev.clientY); }
                        }
                    }

                    function onUp(ev) {
                        if (!ev.isTrusted) { cleanup(); return; }
                        if (!moved) {
                            // 드래그 없이 클릭만 한 경우: sortable 컬럼이면 정렬 트리거
                            const sortBtn = cell.querySelector('.tui-grid-btn-sorting');
                            if (sortBtn) {
                                sortBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
                            }
                        }
                        if (dragging) {
                            ev.stopImmediatePropagation();
                            const zr = zone.getBoundingClientRect();
                            const inZone = ev.clientX >= zr.left && ev.clientX <= zr.right &&
                                ev.clientY >= zr.top  && ev.clientY <= zr.bottom;

                            if (inZone) {
                                /* ── 그룹존 드롭 → 그룹핑 ── */
                                const gc = getGC();
                                if (gc.indexOf(colName) === -1) {
                                    captureOriginalBody();
                                    setGC(gc.concat(colName));
                                    renderChips(zone);
                                    applyGroupData();
                                }
                            } else if (canColMove && dropTargetCell) {
                                /* ── 헤더 드롭 → 컬럼 이동 ── */
                                const targetColN = dropTargetCell.getAttribute('data-column-name');
                                if (targetColN && targetColN !== colName) {
                                    try {
                                        /* allColumns 기준으로 인덱스 계산 (row header 포함) */
                                        const allC    = (currentGrid.store && currentGrid.store.column && currentGrid.store.column.allColumns) || currentGrid.getColumns();
                                        const fromIdx = allC.findIndex(c => c.name === colName);
                                        let   toIdx   = allC.findIndex(c => c.name === targetColN);
                                        if (fromIdx !== -1 && toIdx !== -1) {
                                            if (!dropInsertBefore) toIdx++;
                                            const adjTo = Math.max(0, Math.min(allC.length - 1,
                                                toIdx > fromIdx ? toIdx - 1 : toIdx));
                                            currentGrid.moveColumn(colName, adjTo);
                                            /* persistence 저장 */
                                            _saveColOrder(currentGrid, config);
                                            /* 그룹핑 중이면 동기화 */
                                            if (getGC().length > 0) {
                                                renderChips(zone);
                                                applyGroupData();
                                            }
                                        }
                                    } catch(e) {
                                        console.warn('[colMove] moveColumn error:', e);
                                    }
                                }
                            }
                        }
                        cleanup();
                    }

                    document.addEventListener('mousemove', onMove, true);
                    document.addEventListener('mouseup',   onUp,   true);
                    downEv.preventDefault();
                    downEv.stopImmediatePropagation();

                }, true); /* capture 모드 */
            });
        }

        /* ─── 초기 실행 ─── */
        const zone = ensureDropZone();
        renderChips(zone);
        bindClearBtn(zone);

        requestAnimationFrame(() => {
            try {
                const body_height = calculate_body_height(config);
                const complex_height = config.complex_columns ? 30 : 0;

                currentGrid.setBodyHeight(
                    config.wrap_selector.includes('PopGrid')
                        ? 435
                        : $(config.wrap_selector).innerHeight() - body_height - complex_height
                );
            } catch (e) {
                console.warn('[grp] bodyHeight recalc error:', e);
            }

            bindHeaderDrag(zone);
            syncHeaderCursor();

            try {
                const gc = getGC();
                const rows = currentGrid.getData ? currentGrid.getData() : [];

                if (gc.length > 0 && Array.isArray(rows) && rows.length > 0) {
                    captureOriginalBody();
                    applyGroupData();
                }
            } catch (e) {
                console.warn('[grp] initial applyGroupData error:', e);
            }
        });

        /* ─── 외부 API ─── */
        /* [FIX-B] _setup_grid_grouping 재호출 시 currentGrid 레퍼런스 갱신
           handle_draw_grid 등이 동일 selector로 재호출하면
           기존 __grpCtrl을 통해 currentGrid를 최신 인스턴스로 교체한다 */
        /* [v7] __grpCtrl API */
        window['__grpCtrl_' + selector] = {
            getGrid         : () => currentGrid,
            redraw          : () => { renderChips(ensureDropZone()); applyGroupData(); },
            clearGrouping   : () => { setGC([]); renderChips(ensureDropZone()); applyGroupData(); },
            getGroupColumns : () => getGC().slice(),
            setGroupColumns : (cols) => {
                captureOriginalBody();
                setGC(cols);
                renderChips(ensureDropZone());
                applyGroupData();
            },
            /* 외부에서 데이터를 직접 교체할 때 (서버 리로드 등) */
            resetOriginalData: (newBody) => {
                if (Array.isArray(newBody)) {
                    window[stateKey].originalBody = JSON.parse(JSON.stringify(
                        newBody.map(r => cleanRow(r))
                    ));
                    if (getGC().length > 0) applyGroupData();
                }
            },
            /* 외부에서 grid 인스턴스 교체 시 사용 */
            _updateGrid     : (newGrid) => { currentGrid = newGrid; },
        };
    };
    /* ── 컬럼 순서 저장 헬퍼 (change_grid 래퍼) ── */
    function _saveColOrder(grid, config) {
        if (typeof change_grid !== 'function') return;
        try {
            const segment = (window.location.pathname.split('/').pop() || '');
            if (!segment) return;
            const key = segment + '_' + config.selector +
                (config.header_flag ? '_' + config.header_flag : '');
            change_grid(key, grid, config);
        } catch(e) {
            console.warn('[colMove] _saveColOrder error:', e);
        }
    }

})();
// ================================================================
// ▲▲▲  그리드 그룹핑 확장 기능 끝  ▲▲▲
// ================================================================

// ================================================================
// ▼▼▼  컬럼 순서 변경 독립 기능 (grouping:false, useColumnReorder:true)  ▼▼▼
// ================================================================
(function () {
    /**
     * _setup_column_reorder(grid, config)
     * ─────────────────────────────────────────────────────────────
     * grouping 없이 컬럼 이동만 사용할 때 호출됩니다.
     * (grouping:false, useColumnReorder:true)
     *
     * 동작:
     *  - 헤더 셀에 mousedown(capture) 바인딩
     *  - 드래그 후 다른 헤더 위에 드롭 → moveColumn(colName, adjIdx)
     *  - 시각 피드백: 수직 인디케이터 선 + 초록 고스트 + 하이라이트
     *  - 드롭 후 change_grid()로 localStorage에 순서 저장
     */
    window._setup_column_reorder = function (currentGrid, config) {
        /* CSS는 _inject_grouping_css와 공유 – __tui_grp_css 없으면 최소 CSS 주입 */
        if (!document.getElementById('__tui_col_reorder_css')) {
            const css = `
th.tui-col-reorder-draggable { cursor: grab; }
th.tui-col-reorder-draggable:hover { background: rgba(13,110,253,.06); }
th.tui-grp-col-drop-target {
    background-color: rgba(13,110,253,.12) !important;
    box-shadow: inset 0 0 0 2px #0d6efd;
}
.tui-grp-col-indicator {
    position: fixed;
    width: 3px;
    background: #0d6efd;
    border-radius: 2px;
    z-index: 9998;
    pointer-events: none;
    box-shadow: 0 0 6px rgba(13,110,253,.55);
    top: 0; bottom: 0;
    display: none;
}
.tui-grp-col-move-ghost {
    position: fixed;
    z-index: 9999;
    background: #198754;
    color: #fff;
    padding: 3px 12px;
    border-radius: 4px;
    font-size: 12px;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0,0,0,.25);
    white-space: nowrap;
}
body.tui-col-reorder-dragging,
body.tui-col-reorder-dragging * { cursor: grabbing !important; user-select: none !important; }
`;
            const el = document.createElement('style');
            el.id = '__tui_col_reorder_css';
            el.textContent = css;
            document.head.appendChild(el);
        }

        const selector = config.selector;
        const header   = Array.isArray(config.header)
            ? config.header.filter(c => !c.childNames)
            : [];

        function getLabel(name) {
            const col = header.find(c => c.name === name);
            return col ? (col.header || col.name) : name;
        }

        function bindColReorder() {
            const gridEl = document.getElementById(selector);
            if (!gridEl) return;

            gridEl.querySelectorAll('th[data-column-name].tui-grid-cell-header').forEach(cell => {
                const colName = cell.getAttribute('data-column-name');
                if (!colName || cell.dataset.colReorderBound === 'Y') return;
                cell.dataset.colReorderBound = 'Y';
                cell.classList.add('tui-col-reorder-draggable');
                cell.title = '드래그: 컬럼 순서 변경';

                cell.addEventListener('dragstart', e => e.preventDefault());

                cell.addEventListener('mousedown', function (downEv) {
                    if (downEv.button !== 0) return;
                    if (downEv.target.closest('.tui-grid-btn-sorting'))          return;
                    if (downEv.target.closest('.tui-grid-column-resize-handle')) return;

                    const startX = downEv.clientX, startY = downEv.clientY;
                    let moved = false, dragging = false;
                    let ghost = null, colIndicator = null;
                    let dropTargetCell = null, dropInsertBefore = true;
                    let bodyBlocked = false;
                    const label = getLabel(colName);

                    function blockGrid() {
                        if (bodyBlocked) return; bodyBlocked = true;
                        const c = gridEl.querySelector('.tui-grid-container');
                        if (c) c.style.pointerEvents = 'none';
                    }
                    function unblockGrid() {
                        const c = gridEl.querySelector('.tui-grid-container');
                        if (c) c.style.pointerEvents = '';
                    }

                    function mkGhost(x, y) {
                        ghost = document.createElement('div');
                        ghost.className = 'tui-grp-col-move-ghost';
                        ghost.textContent = '↔ ' + label;
                        document.body.appendChild(ghost);
                        mvGhost(x, y);
                    }
                    function mvGhost(x, y) {
                        if (!ghost) return;
                        ghost.style.left = (x + 14) + 'px';
                        ghost.style.top  = (y + 14) + 'px';
                    }

                    function showIndicator(th, before) {
                        if (!colIndicator) {
                            colIndicator = document.createElement('div');
                            colIndicator.className = 'tui-grp-col-indicator';
                            document.body.appendChild(colIndicator);
                        }
                        const r  = th.getBoundingClientRect();
                        const tr = (th.closest('tr') || th).getBoundingClientRect();
                        colIndicator.style.left    = (before ? r.left - 1 : r.right - 1) + 'px';
                        colIndicator.style.top     = tr.top + 'px';
                        colIndicator.style.height  = tr.height + 'px';
                        colIndicator.style.display = 'block';
                    }
                    function hideIndicator() {
                        if (colIndicator) colIndicator.style.display = 'none';
                    }

                    function findHeaderCell(x, y) {
                        const ths = gridEl.querySelectorAll('th[data-column-name].tui-grid-cell-header');
                        for (const th of ths) {
                            const r = th.getBoundingClientRect();
                            if (x >= r.left && x <= r.right && y >= r.top && y <= r.bottom) return th;
                        }
                        return null;
                    }

                    function clearHighlight() {
                        gridEl.querySelectorAll('th.tui-grp-col-drop-target').forEach(th =>
                            th.classList.remove('tui-grp-col-drop-target'));
                    }

                    function cleanup() {
                        document.removeEventListener('mousemove', onMove, true);
                        document.removeEventListener('mouseup',   onUp,   true);
                        if (ghost && ghost.parentNode) ghost.parentNode.removeChild(ghost);
                        ghost = null;
                        if (colIndicator && colIndicator.parentNode) colIndicator.parentNode.removeChild(colIndicator);
                        colIndicator = null;
                        document.body.classList.remove('tui-col-reorder-dragging');
                        clearHighlight();
                        dropTargetCell = null;
                        unblockGrid();
                    }

                    function onMove(ev) {
                        if (!ev.isTrusted) return;
                        const dx = Math.abs(ev.clientX - startX);
                        const dy = Math.abs(ev.clientY - startY);
                        if (!moved && (dx > 4 || dy > 4)) {
                            moved = true; dragging = true;
                            mkGhost(ev.clientX, ev.clientY);
                            document.body.classList.add('tui-col-reorder-dragging');
                            blockGrid();
                        }
                        if (!dragging) return;
                        ev.stopImmediatePropagation();
                        ev.preventDefault();
                        mvGhost(ev.clientX, ev.clientY);

                        const targetTh  = findHeaderCell(ev.clientX, ev.clientY);
                        const targetCol = targetTh ? targetTh.getAttribute('data-column-name') : null;
                        if (targetTh && targetCol && targetCol !== colName) {
                            const r = targetTh.getBoundingClientRect();
                            dropInsertBefore = ev.clientX < r.left + r.width * 0.5;
                            dropTargetCell   = targetTh;
                            clearHighlight();
                            targetTh.classList.add('tui-grp-col-drop-target');
                            showIndicator(targetTh, dropInsertBefore);
                        } else {
                            clearHighlight();
                            hideIndicator();
                            dropTargetCell = null;
                        }
                    }

                    function onUp(ev) {
                        if (!ev.isTrusted) { cleanup(); return; }
                        if (!moved) {
                            // 드래그 없이 클릭만 한 경우: sortable 컬럼이면 정렬 트리거
                            const sortBtn = cell.querySelector('.tui-grid-btn-sorting');
                            if (sortBtn) {
                                sortBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
                            }
                        }
                        if (dragging && dropTargetCell) {
                            ev.stopImmediatePropagation();
                            const targetColN = dropTargetCell.getAttribute('data-column-name');
                            if (targetColN && targetColN !== colName) {
                                try {
                                    /* allColumns 기준으로 인덱스 계산 (row header 포함) */
                                    const allC    = (currentGrid.store && currentGrid.store.column && currentGrid.store.column.allColumns) || currentGrid.getColumns();
                                    const fromIdx = allC.findIndex(c => c.name === colName);
                                    let   toIdx   = allC.findIndex(c => c.name === targetColN);
                                    if (fromIdx !== -1 && toIdx !== -1) {
                                        if (!dropInsertBefore) toIdx++;
                                        const adjTo = Math.max(0, Math.min(allC.length - 1,
                                            toIdx > fromIdx ? toIdx - 1 : toIdx));
                                        currentGrid.moveColumn(colName, adjTo);
                                        /* persistence 저장 */
                                        if (typeof change_grid === 'function') {
                                            try {
                                                const seg = (window.location.pathname.split('/').pop() || '');
                                                if (seg) {
                                                    const k = seg + '_' + config.selector +
                                                        (config.header_flag ? '_' + config.header_flag : '');
                                                    change_grid(k, currentGrid, config);
                                                }
                                            } catch(e2) { console.warn('[colMove] save error:', e2); }
                                        }
                                        /* rebind (새 헤더 DOM) */
                                        requestAnimationFrame(() => {
                                            gridEl.querySelectorAll('th[data-column-name].tui-grid-cell-header')
                                                .forEach(th => { delete th.dataset.colReorderBound; });
                                            bindColReorder();
                                        });
                                    }
                                } catch(e) {
                                    console.warn('[colMove] moveColumn error:', e);
                                }
                            }
                        }
                        cleanup();
                    }

                    document.addEventListener('mousemove', onMove, true);
                    document.addEventListener('mouseup',   onUp,   true);
                    downEv.preventDefault();
                    downEv.stopImmediatePropagation();
                }, true);
            });
        }

        /* 최초 바인딩 */
        requestAnimationFrame(() => bindColReorder());

        /* 외부 API */
        window['__colReorder_' + selector] = {
            rebind : () => {
                const gridEl = document.getElementById(selector);
                if (gridEl) gridEl.querySelectorAll('th[data-column-name].tui-grid-cell-header')
                    .forEach(th => { delete th.dataset.colReorderBound; });
                bindColReorder();
            },
            _updateGrid: (newGrid) => { currentGrid = newGrid; },
        };
    };
})();
// ================================================================
// ▲▲▲  컬럼 순서 변경 독립 기능 끝  ▲▲▲
// ================================================================

// 그룹핑 체크 함수
function isGridGroupingActive(gridOrSelector) {
    let selector = '';

    if (typeof gridOrSelector === 'string') {
        selector = gridOrSelector;
    } else if (gridOrSelector && gridOrSelector.el && gridOrSelector.el.id) {
        selector = gridOrSelector.el.id;
    }

    if (!selector) return false;

    const state = window['__grpState_' + selector];

    return !!(
        state &&
        Array.isArray(state.groupColumns) &&
        state.groupColumns.length > 0
    );
}
