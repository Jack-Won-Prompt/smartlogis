// Grid Indirectly Related Source (checked)
// Range: custom.js lines 1067-2148
// Note: syntax checked after split

function autoSetNmByCd(route, name, sub_param=null, sub_param2=null,sub_param3=null, sub_param4) {
    // param1 = route
    // param2 = 적용 태그
    // param3 = 부모태그
    // param4 = 검색 조건
    let columnList = [];

    $(document).on("change", `.popup_${name}_code`, function(e) {
        let inputVal = $(this).val(); // 현재 입력된 값 가져오기

        let parentEl = $(this).parents('tr');
        parentEl = parentEl.length ? parentEl : $(this).parents('form');


        // TODO: 선행되어야 하는 필수값 검사 로직 추가 필요
        if (parentEl.length == 0) {
            console.warn("⚠️ 부모 요소를 찾을 수 없음. 이벤트 종료.");
            return;
        }

        if (sub_param4) {
            parentEl = $(sub_param4);
        }

        let subValue = '';
        if (sub_param) {
            if ($(sub_param).length > 1) {
                subValue = parentEl.find(sub_param).val();
            } else {
                subValue = $(sub_param).val();
            }
        }

        let url = `${route}/getvalue`;
        let data = {
            value: $(this).val(),
            sub_value: subValue,
            sub_value2: sub_param2,
            sub_value3: sub_param3
        };


        let input_code = $(this).val();
        let input = $(this);
        let button = input.parents().children('button');

        getAjax(url, data, function(response) {

            let data = response.data;
            if (response.is_success) {

                if (response.tot_cnt > 1) {
                    input.val('');
                    autoset_input_code = input_code;
                    button.click();
                } else {
                    parentEl.find(`.popup_${name}_id`).val(data.id).trigger('change');
                    parentEl.find(`.popup_${name}_name`).val(data.name);

                    $.each(data, function(column, value) {
                        if (parentEl.find(`.input_${column}`).length) {
                            parentEl.find(`.input_${column}`).val(value);
                        }
                    });
                }
            } else {
                columnList = data; // 자동 세팅되어야 하는 컬럼 input 값 리스트

                if (!parentEl.hasClass('filter_form')) {
                    parentEl.find(`.popup_${name}_code`).val('');
                }

                parentEl.find(`.popup_${name}_id`).val('').trigger('change');
                parentEl.find(`.popup_${name}_name`).val('');

                $.each(data, function(idx, column) {
                    if (parentEl.find(`.input_${column}`).length) {
                        parentEl.find(`.input_${column}`).val('');
                    }
                });
            }
        });
    });

    $(document).on("change", `.popup_${name}_name`, function(e) {
        let parentEl = $(this).parents('tr');
        parentEl = parentEl.length ? parentEl : $(this).parents('form');

        if (!parentEl.hasClass('filter_form')) {
            parentEl.find(`.popup_${name}_name`).val('');
        }
        parentEl.find(`.popup_${name}_id`).val('').trigger('change');
        parentEl.find(`.popup_${name}_code`).val('');
        // 관련 데이터 input 값 자동 세팅
        $.each(columnList, function(idx, column){
            if(parentEl.find(`.input_${column}`).length) {
                parentEl.find(`.input_${column}`).val('');
            }
        });
    });
}

// todo: Form용 autoSet이랑 Table용 autoSet 따로 만들어야 할 듯
function autoSetNmByCdTable(grid, route, name, param1=null, param2=null, param3=null) {
    // param1 = 적용 그리드
    // param2 = route
    // param3 = 이름
    // param4 = 검색 조건
    let orginValue = '';
    grid.on('editingStart', (ev) => {
        orginValue = ev.value;
    });
    grid.on('editingFinish', (ev) => {
        console.log('editingFinish >> input.~~_code');
        if ((orginValue == null && ev.value == '') || ev.value == orginValue) {
            return false;
        }

        const rowKey = ev.rowKey;
        const colName = ev.columnName;
        if (!colName.includes(name+'_code')) {
            return false;
        }
        const rowClass = '.tr_'+rowKey; // 동일 행 구분 클래스명

        let subValue = null;
        if (param1) {
            if (param1.includes('.') || param1.includes('name=')) { // selector로 보낸 경우
                if ($(param1).length == 1) { // 헤더의 상속 값
                    subValue = $(param1).val();
                } else if ($(param1).length > 1) { // 동일 행의 상속 값
                    subValue = $(`${rowClass}${param1} > .tui-grid-cell-content`).text();
                } else {
                    console.log($(param1));
                }
            } else { // grid 플러그인의 헤더 name값으로 보낸 경우
                subValue = grid.getValue(rowKey, param1);
            }
        }
        console.log('subValue === ' + subValue); // null

        var url = `${route}/getvalue`;

        var data = {
            value: ev.value,
            sub_value: subValue,
            sub_value2: param2,
            sub_value3: param3,
        };
        var input_code = ev.value;
        // console.log('input_code : ', input_code);
        var rowButton = $(`${rowClass}`).children('button');
        // console.log('button : ', rowButton);
        getAjax(url, data, function(response) {
            let data = response.data;
            if (response.is_success) {
                if (response.tot_cnt > 1) {
                    // tot_cnt가 1보다 클 경우 search_location_btn 버튼 클릭 이벤트 추가
                    // console.log('도착');
                    console.log('tot_cnt2 > 1');
                    show_toastr('Error!', 'Data Duplicated', 'error');
                    // todo 추후 개발 팝업열기
                    // autoset_input_code = input_code;
                    // console.log('autoset_input_code : ', autoset_input_code);
                    // rowButton.click(); // 클릭 이벤트 발생
                } else {
                    // console.log('tot_cnt2 == 1');
                    grid.setValue(rowKey, colName.replace('_code', '_id'), data.id, false);
                    grid.setValue(rowKey, colName.replace('_code', '_name'), data.name, false);

                    // 관련 데이터 값 자동 세팅
                    $.each(data, function(column, value){
                        let gridClassName = grid.getCellClassName(rowKey, column);

                        var filteredClassNames = gridClassName.filter(function(className) {
                            return className.includes("input_");
                        });

                        if(filteredClassNames == 'input_'+column) {
                            grid.setValue(rowKey, column, value, false);
                        }
                    });
                }

            } else {
                grid.setValue(rowKey, colName, '', false);
                grid.setValue(rowKey, colName.replace('_code', '_id'), '', false);
                grid.setValue(rowKey, colName.replace('_code', '_name'), '', false);

                // 관련 데이터 값 자동 세팅
                $.each(data, function(idx, column){
                    let gridClassName = grid.getCellClassName(rowKey, column);

                    var filteredClassNames = gridClassName.filter(function(className) {
                        return className.includes("input_");
                    });

                    if(filteredClassNames == 'input_'+column) {
                        grid.setValue(rowKey, column, '', false);
                    }
                });
            }
        });
    });
}

function handlePopupOrAutoSet(config) {
    const defaultConfig = {
        grid: null, // 적용할 그리드 객체 (null이면 팝업 모드로 동작)
        route: "", // API 경로
        name: "", // 대상 컬럼명
        modalId: "#commonModal", // 팝업 모달 ID
        className: "modal-lg", // 팝업 모달 ID
        popupTitle: "Popup", // 팝업 제목
        popupDataHeader: null, // 팝업 조회 컬럼
        dataMapper: (context) => ({}), // 컨텍스트에 따라 데이터 매핑
        inputSetting: null, // 추가된 inputSetting 개념 (매핑 설정)
    };

    const finalConfig = { ...defaultConfig, ...config };

    if (finalConfig.grid) {
        // **그리드 자동 완성**
        let originValue = "";

        finalConfig.grid.on("editingStart", (ev) => {
            originValue = ev.value;
        });

        finalConfig.grid.on("editingFinish", (ev) => {
            if ((originValue == null && ev.value == "") || ev.value == originValue) {
                return false;
            }

            const rowKey = ev.rowKey;
            const colName = ev.columnName;

            // `triggerSelector`에서 `.search_`를 제외한 컬럼 이름 추출
            const triggerColumnName = finalConfig.triggerSelector.replace('.search_', '');
            const columnDefinition = finalConfig.grid.getColumn(colName); // 현재 컬럼 정의 가져오기
            if (
                !columnDefinition || // 컬럼 정의가 없으면 종료
                !columnDefinition.editor || // editor가 정의되지 않았으면 종료
                columnDefinition.editor.type?.name !== 'CustomTextEditor' || // editor.type이 CustomTextEditor가 아니면 종료
                colName !== triggerColumnName // 컬럼 이름이 triggerSelector에서 추출한 이름과 다르면 종료
            ) {
                return false;
            }

            const subValue = {
                ...finalConfig.dataMapper({
                    grid: finalConfig.grid, // 현재 그리드 객체
                    rowKey, // 현재 행의 키
                    colName, // 현재 컬럼 이름
                }),
                autoSetYn: "Y", // 자동 세팅 여부
                autoSetValue: ev.value, // 현재 수정된 값
                popupDataHeader: finalConfig.popupDataHeader, // 팝업 컬럼
            };
            getAjax(`${finalConfig.route}`, subValue, (response) => {
                let data = response.data;
                if (response.is_success) {
                    if (response.tot_cnt > 1) {
                        // tot_cnt가 1보다 클 경우 search_location_btn 버튼 클릭 이벤트 추가
                        console.log('tot_cnt2 > 1');
                        show_toastr('Error!', 'Data Duplicated', 'error');
                        // todo 추후 개발 팝업열기
                        // autoset_input_code = input_code;
                        // console.log('autoset_input_code : ', autoset_input_code);
                        // rowButton.click(); // 클릭 이벤트 발생
                    } else {
                        finalConfig.grid.setValue(rowKey, colName.replace('_code', '_id'), data.id, false);
                        finalConfig.grid.setValue(rowKey, colName.replace('_code', '_name'), data.name, false);

                        // 관련 데이터 값 자동 세팅
                        $.each(data, function(column, value) {
                            // 추가 데이터 세팅 (inputSetting 사용)
                            if (finalConfig.inputSetting) {
                                // inputSetting에 따라 값을 매핑
                                for (const [gridColumn, dataKey] of Object.entries(finalConfig.inputSetting)) {
                                    if (data[dataKey] !== undefined) {
                                        finalConfig.grid.setValue(rowKey, gridColumn, data[dataKey], false);
                                    }
                                }
                            } else {
                                // TUI Grid의 실제 컬럼 정보 가져오기
                                const gridColumns = finalConfig.grid.getColumns();
                                // TUI Grid에서 컬럼명 찾기
                                const matchingColumn = gridColumns.find((gridColumn) => {
                                    return gridColumn.className === "input_" + column;
                                });
                                if (!matchingColumn) {
                                    return;
                                }
                                const gridColumnName = matchingColumn.name; // TUI Grid의 컬럼명
                                // 해당 셀의 클래스명 가져오기
                                const gridClassName = finalConfig.grid.getCellClassName(rowKey, gridColumnName);
                                if (!gridClassName) {
                                    return;
                                }

                                // 클래스명 필터링: "input_"으로 시작하는 클래스만 선택
                                const filteredClassNames = gridClassName.filter(function(className) {
                                    return className.includes("input_");
                                });

                                // 컬럼명과 일치하는 경우 값 설정
                                if (filteredClassNames.includes("input_" + column)) {
                                    // 그리드 값 설정
                                    finalConfig.grid.setValue(rowKey, gridColumnName, value, false);
                                } else {
                                    console.warn(`No matching filtered class found for column ${gridColumnName}`);
                                }
                            }
                        });
                    }

                } else {
                    finalConfig.grid.setValue(rowKey, colName, '', false);
                    finalConfig.grid.setValue(rowKey, colName.replace('_code', '_id'), '', false);
                    finalConfig.grid.setValue(rowKey, colName.replace('_code', '_name'), '', false);

                    // 관련 데이터 값 자동 세팅
                    $.each(data, function(idx, column){
                        if (finalConfig.inputSetting) {
                            // inputSetting에 따라 초기화
                            for (const gridColumn of Object.keys(finalConfig.inputSetting)) {
                                finalConfig.grid.setValue(rowKey, gridColumn, "", false);
                            }
                        } else {
                            // TUI Grid의 실제 컬럼 정보 가져오기
                            const gridColumns = finalConfig.grid.getColumns();
                            // TUI Grid에서 컬럼명 찾기
                            const matchingColumn = gridColumns.find((gridColumn) => {
                                return gridColumn.className === "input_" + column;
                            });
                            if (!matchingColumn) {
                                return;
                            }
                            const gridColumnName = matchingColumn.name; // TUI Grid의 컬럼명
                            // 해당 셀의 클래스명 가져오기
                            const gridClassName = finalConfig.grid.getCellClassName(rowKey, gridColumnName);
                            if (!gridClassName) {
                                return;
                            }

                            // 클래스명 필터링: "input_"으로 시작하는 클래스만 선택
                            const filteredClassNames = gridClassName.filter(function(className) {
                                return className.includes("input_");
                            });
                            // 컬럼명과 일치하는 경우 값 설정
                            if (filteredClassNames.includes("input_" + column)) {
                                // 그리드 값 설정
                                finalConfig.grid.setValue(rowKey, gridColumnName, '', false);
                            } else {
                                console.warn(`No matching filtered class found for column ${gridColumnName}`);
                            }
                        }
                    });
                }
            });
        });
    } else {
        $(document).on("change", `.popup_${finalConfig.name}_code`, function (e) {
            let parentEl;
            // data-id 속성 값 가져오기
            let dataId = $(this).siblings("button[data-id]").data("id");

            if (dataId) {
                // data-id 값을 기반으로 해당 요소를 찾음
                parentEl = $(dataId);
            } else {
                // 기존 방식으로 parents 탐색
                parentEl = $(this).parents("tr");
                parentEl = parentEl.length ? parentEl : $(this).parents("form");
            }

            if (parentEl.length === 0) {
                return;
            }

            const subValue = {
                ...finalConfig.dataMapper({ element: this }), // context로 element(this)만 전달
                autoSetYn: "Y", // 추가된 autoSetYn 값
                autoSetValue: $(this).val(), // 입력 값
                popupDataHeader: finalConfig.popupDataHeader, // 팝업 컬럼
            };

            const dynamicRoute =
                typeof finalConfig.route === "function"
                    ? finalConfig.route({ element: this }) // 함수 호출
                    : finalConfig.route; // 문자열 그대로 사용

            const input_code = $(this).val();
            const input = $(this);
            const button = input.parents().children("button");
            getAjax(dynamicRoute, subValue, function (response) {
                let data = response.data;
                if (response.is_success) {
                    if (response.tot_cnt > 1) {
                        input.val("");
                        autoset_input_code = input_code;
                        button.click();
                    } else {
                        parentEl.find(`.popup_${finalConfig.name}_id`).val(data.id).trigger("change");
                        parentEl.find(`.popup_${finalConfig.name}_name`).val(data.name);

                        // 추가 데이터 세팅
                        if (finalConfig.inputSetting) {
                            for (const [inputSelector, dataKey] of Object.entries(finalConfig.inputSetting)) {
                                if (data[dataKey] !== undefined) {
                                    parentEl.find(`.input_${inputSelector}`).val(data[dataKey]);
                                }
                            }
                        } else {
                            // 관련 데이터 input 값 자동 세팅
                            $.each(data, function (column, value) {
                                if (parentEl.find(`.input_${column}`).length) {
                                    parentEl.find(`.input_${column}`).val(value);
                                }
                            });
                        }
                    }
                } else {
                    if (!parentEl.hasClass("filter_form")) {
                        parentEl.find(`.popup_${finalConfig.name}_code`).val("");
                    }
                    parentEl.find(`.popup_${finalConfig.name}_id`).val("").trigger("change");
                    parentEl.find(`.popup_${finalConfig.name}_name`).val("");


                    if (finalConfig.inputSetting) {
                        for (const inputSelector of Object.keys(finalConfig.inputSetting)) {
                            parentEl.find(`.input_${inputSelector}`).val("");
                        }
                    } else {
                        // 관련 데이터 input 값 자동 세팅
                        $.each(data, function (idx, column) {
                            if (parentEl.find(`.input_${column}`).length) {
                                parentEl.find(`.input_${column}`).val("");
                            }
                        });
                    }
                }
            });
        });
    }
    // 팝업 호출
    $(document).off("click", finalConfig.triggerSelector) // 기존 핸들러 제거
        .on("click", finalConfig.triggerSelector, function (e) {
            e.stopImmediatePropagation();

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
                popupTargetInputSetting = finalConfig.inputSetting;
            }

            // `route`가 함수인지 확인하여 호출
            // 팝업 호출 데이터 생성
            const dynamicRoute =
                typeof finalConfig.route === "function"
                    ? finalConfig.route({ element: this, rowKey }) // rowKey를 전달
                    : finalConfig.route;

            const data = {
                ...finalConfig.dataMapper({
                    element: this,
                    grid: finalConfig.grid, // 그리드 객체
                    rowKey, // 클릭된 행의 rowKey 전달
                }), // context로 element(this)만 전달
                popupDataHeader: finalConfig.popupDataHeader, // 팝업 컬럼
            };

            $(finalConfig.modalId + " .modal-title").html(finalConfig.popupTitle);
            $(finalConfig.modalId + " .modal-dialog").addClass(finalConfig.className);
            $(finalConfig.modalId).modal("show");
            // AJAX 호출
            getAjax(dynamicRoute, data, (response) => {
                nowOpenPopupModal = finalConfig.modalId;
                $(finalConfig.modalId + " .modal-body").html(response);
            });
        });
}


// function customer_part_number_table_scripts() {
//     console.log('test datatable');

//         var table = $('#manager-pc-dt-simple').DataTable({
//             scrollCollapse: false,
//             scrollX: true,
//             autoFill: true,
//             'dom': 'BRlfrtip',
//             language: {
//                     paginate: {
//                         previous: "<i class='fas fa-angle-left'>",
//                         next: "<i class='fas fa-angle-right'>"
//                     },
//                 },
//                 'buttons': [
//                     { extend: 'csvHtml5', className: 'btn btn-light-primary btn-sm m-3 float-end' },
//                     {
//                         text: 'Add',
//                         className: 'btn btn-light-info btn-sm  my-3 new_customer_part_number_add',
//                         action: function ( e, dt, node, config ) {
//                             addCustomerPartNumberRow();
//                         }
//                     },
//                     {
//                         text: 'Delete',
//                         className: 'btn btn-light-danger btn-sm  my-3 customer_part_number_delete',
//                         action: function ( e, dt, node, config ) {
//                             //alert( 'Button activated' );
//                         }
//                     }

//                 ],
//     });

//         $('#checkAll').click(function () {
//              $('input:checkbox').prop('checked', this.checked);
//         });



// }


function formDisable($name) {
    $($name).find('input, textarea').attr("readonly",true);
    $($name).find('select, input[type=file]').attr("disabled",true);
    $($name).find('button:not(.btn-excel), label.btn-file').hide();
}

function tableToExcel(id, name = null) {
    const wb = XLSX.utils.table_to_book(document.getElementById(id), { raw:true });
    const today = new Date();
    let fileNm = `${today.getFullYear()}${today.getMonth()+1}${today.getDate()}.xlsx`;
    if(name){
        fileNm = name+'.xlsx';
    }
    XLSX.writeFile(wb, fileNm);
    if (curBtn) curBtn.blur();
}

function downloadExcel(menu, tab='', id='', formSelector='.filter_form', validation = true, gridId = 'grid1') { // third = 세번째 탭 여부
    const formData = $(formSelector).serializeArray();
    const form = document.createElement('form');
    form.action = menu + '/excel_download/' + tab + '/' + id;
    form.method = 'get';
    formData.forEach(function(data) {
        var input = document.createElement("input");
        input.setAttribute("type", "hidden");
        input.setAttribute("name", data.name);
        input.setAttribute("value", data.value);
        form.appendChild(input);
    })

    const pathname = window.location.pathname;
    const segments = pathname.split('/');
    const segment = segments.pop() || "No Segment";
    const gridKey = `grid_settings_${segment}_${gridId}`;
    const savedGrid = localStorage.getItem(gridKey);

    if (savedGrid) {
        const parsedGrid = JSON.parse(savedGrid);
        const columnOrder = parsedGrid.map(col => col.name);

        const colInput = document.createElement("input");
        colInput.type = "hidden";
        colInput.name = "column_order";
        colInput.value = JSON.stringify(columnOrder);
        form.appendChild(colInput);
    }

    if (typeof withworksGetPetExcelFileName === 'function') {
        const petFileName = withworksGetPetExcelFileName(gridId, '.xlsx');
        if (petFileName) {
            const fileNameInput = document.createElement("input");
            fileNameInput.type = "hidden";
            fileNameInput.name = "excel_file_name";
            fileNameInput.value = petFileName;
            form.appendChild(fileNameInput);
        }
    }
    document.body.appendChild(form);

    // 엑셀 다운로드 관련 validation
    if (validation && !id) {
        formData.push({name: 'validation', value: validation});
        $.get(form.action, formData, function (resp) {
            if (resp.status) {
                form.submit();
            } else {
                show_toastr('Error!', resp.message, 'error');
            }
        });
    } else {
        form.submit();
    }
}

function downloadExcelConfig(config) {
    const defaultConfig = {
        menu: "",
        tab: "",
        id: "",
        formSelector: ".filter_form",
        validation: true,
        gridId: "grid1"
    };

    const finalConfig = {
        ...defaultConfig,
        ...config
    };

    const formData = $(finalConfig.formSelector).serializeArray();
    const form = document.createElement("form");
    form.action = finalConfig.menu + "/excel_download/" + finalConfig.tab + "/" + finalConfig.id;
    form.method = "get";
    formData.forEach(function(data) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = data.name;
        input.value = data.value;
        form.appendChild(input);
    });
    addMultiSearchParamsToForm(form);


    const pathname = window.location.pathname;
    const segments = pathname.split("/");
    const segment = segments.pop() || "No Segment";
    const gridKey = `grid_settings_${segment}_${finalConfig.gridId}`;
    const savedGrid = localStorage.getItem(gridKey);

    if (savedGrid) {
        const parsedGrid = JSON.parse(savedGrid);
        const columnOrder = parsedGrid.map(col => col.name);

        const colInput = document.createElement("input");
        colInput.type = "hidden";
        colInput.name = "column_order";
        colInput.value = JSON.stringify(columnOrder);
        form.appendChild(colInput);
    }

    if (typeof withworksGetPetExcelFileName === "function") {
        const petFileName = withworksGetPetExcelFileName(finalConfig.gridId, ".xlsx");
        if (petFileName) {
            const fileNameInput = document.createElement("input");
            fileNameInput.type = "hidden";
            fileNameInput.name = "excel_file_name";
            fileNameInput.value = petFileName;
            form.appendChild(fileNameInput);
        }
    }

    document.body.appendChild(form);

    // 엑셀 다운로드 관련 validation
    if (finalConfig.validation && !finalConfig.id) {
        formData.push({ name: "validation", value: finalConfig.validation });
        $.get(form.action, formData, function (resp) {
            if (resp.status) {
                form.submit();
            } else {
                show_toastr("Error!", resp.message, "error");
            }
        });
    } else {
        form.submit();
    }
}

// 정산서 엑셀 다운로드
function submitFormWithParams(data) {
    var baseUrl = window.location.origin;
    var pathSegments = window.location.pathname.split('/').filter(segment => segment.length > 0);
    var baseInvoicePath = '/' + pathSegments.slice(0, pathSegments.indexOf(data.menu) + 1).join('/');

    // form 생성
    const form = document.createElement('form');
    form.action = `${baseUrl}${baseInvoicePath}/excel_download`;
    form.method = 'get';

    // 'data' 객체 내용을 사용하여 input 요소를 생성
    Object.keys(data).forEach(function(key) {
        var input = document.createElement("input");
        input.setAttribute("type", "hidden");
        input.setAttribute("name", key);
        input.setAttribute("value", data[key]);
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

// 엑셀업로드 엑셀 다운로드
function excelUploadExcelDownload(data) {
    // form 생성
    const form = document.createElement('form');
    form.action = `excel_download/master_setup/`;
    form.method = 'get';

    // 'data' 객체 내용을 사용하여 input 요소를 생성
    Object.keys(data).forEach(function(key) {
        var input = document.createElement("input");
        input.setAttribute("type", "hidden");
        input.setAttribute("name", key);
        input.setAttribute("value", data[key]);
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

/*
* 공통 코드 변환 함수
* */
function formatCommonCode(mainCd, classNm) {
    $.ajax({
        type: 'get',
        url: 'getCodeList',
        data: {
            mainCd: mainCd
        },
        success: function(resp) {
            console.log(resp)
            $('.'+classNm).each(function() {
                var subCd = $(this).text();
                console.log(subCd)
                if(resp[subCd]) {
                    $(this).text(resp[subCd]['descr']);
                }
            });
        }
    })
}

// 시퀀스 넘버 조회
function getUniqueNum(data) {
    let send_data = null;
    $.ajax({
        type: "GET",
        url: 'getSeqeunceNum',
        async: false,
        data: {
            division: data
        },
        success: function (data) {
            console.log(data)
            send_data = data;
        },
        error: function (e) {
            console.log('error')
            console.log(e);
            alert('error');
        }
    });

    return send_data;
}

var igSplitter = null;
function setSplitter(selector, ratio='1:1',collapsible = true, resizable = true, preserveSize = true) { // ratio = 1:1 / 1:2 / 2:1
    const splitter = $(selector);

    // hidden element에 splitter를 적용하기 위해
    if (!splitter.is(':visible')) {
        const tabId = splitter.parents('.tab-pane').attr('aria-labelledby');
        $(`#${tabId}`).one('shown.bs.tab', function (e) {
            setSplitter(selector, ratio, collapsible, resizable, preserveSize);
        });
        return false;
    }

    const totalRatio = ratio.split(':').reduce((a,b) => parseInt(a) + parseInt(b));
    const leftOrTopRatio = ratio.split(':')[0];
    let splitterHeight = Math.floor(splitter.height());
    let splitterWidth = Math.floor(splitter.width());

    let orientation = "";
    let panelSize = Math.ceil((splitterHeight - 11) / 2);
    if (splitter.hasClass('splitter-hrz')) {
        orientation = "horizontal";
        panelSize = Math.ceil((splitterHeight - 11) / totalRatio) * leftOrTopRatio; // 스플릿바 11px
    } else if (splitter.hasClass('splitter-vtc')) {
        orientation = "vertical";
        panelSize = Math.ceil((splitterWidth - 11) / totalRatio) * leftOrTopRatio; // 스플릿바 11px
    } else {
        console.log('스플리터 클래스 지정 필요 (splitter-hrz/splitter-vtc)');
        return false;
    }

    try {
        if (!splitter.data('igSplitter')) { // 이미 초기화된 상태인지 확인
            splitter.igSplitter({
                orientation: orientation,
                panels: [
                    { size: panelSize, collapsible: collapsible, resizable: resizable },
                    { collapsible: collapsible }
                ]
            });
            splitter.igSplitter("setFirstPanelSize", panelSize);
            splitter.igSplitter("refreshLayout");
        } else {
            if (!preserveSize) {
                splitter.igSplitter("setFirstPanelSize", panelSize);
            }
            splitter.igSplitter("refreshLayout");
            console.log('Splitter already initialized.');
        }
    } catch (error) {
        console.log('Error setting option:', error);
    }
}

/***********************************************************************
 * 스플리터 함수
 ***********************************************************************/
function setActiveTabSplitter(tabSelector) {
    console.log('setActiveTabSplitter');
    const map = window.SPLITTER_MAP || {};
    console.log('map : ' ,map);
    const splitters = map[tabSelector];
    console.log('splitters : ' ,splitters);
    if (!splitters) return;

    afterPaint(() => {
        splitters.forEach(sel => setSplitter(sel));
    });
}

function afterPaint(cb) {
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            cb();
        });
    });
}

// function setDataTable(selector, extendBtnList = []) {
//     // 다국어
//     let colvisTxt = sessionStorage.getItem('colvis_btn_text');
//     let tbInitTxt = sessionStorage.getItem('tb_init_btn_text');
//     if (!colvisTxt) {
//         colvisTxt = 'Displayed Columns';
//         getAjax('tran', {str: colvisTxt}, function(resp) {
//             colvisTxt = resp;
//             sessionStorage.setItem('colvis_btn_text', resp);
//         });
//     }
//     if (!tbInitTxt) {
//         tbInitTxt = "Init Table Setting";
//         getAjax('tran', {str: tbInitTxt}, function(resp) {
//             tbInitTxt = resp;
//             sessionStorage.setItem('tb_init_btn_text', resp);
//         });
//     }
//     // ---------
//
//     let buttons = [
//         {
//             extend: 'collection',
//             className: 'not-arrow',
//             text: '<i class="fa-solid fa-refresh"></i>',
//             buttons: [
//                 {
//                     text: tbInitTxt,
//                     action: function(e, dt, node, config) {
//                         dt.state.clear();
//                         dt.destroy();
//                         setDataTable(selector, extendBtnList = []);
//                     }
//                 }
//             ],
//             background: false,
//             autoClose: true
//         },
//         {
//             extend: 'colvis',
//             text: colvisTxt
//         },
//         {
//             extend: 'excelHtml5',
//             className: 'float-end',
//             text: '<i class="fa fa-download text-primary me-1"></i>Excel'
//         }
//     ];
//     // extendBtnList ~
//
//     const height = $(selector).parent().height();
//     const theadHeight = $(selector).find('thead').height();
//     const fixedCols = $(selector).find('th.ui-state-disabled');
//
//     $(selector).DataTable({
//         dom: 'Bfrtip',
//         paging: true,
//         pageLength: 20,
//         lengthChange: false, // 페이징 길이 수 변경 막기
//         searching: false,
//         select: true,
//         autoWidth: false,
//         scrollX: true,
//         scrollY: height - (theadHeight + 110), // tableHeight - (headerHeight + footerHeight + btnWrapHeight)
//         colReorder: { // 컬럼 순서 변경
//             fixedColumnsLeft: fixedCols.length
//         },
//         // fixedColumns: {
//         //     left: fixedCols.length
//         // },
//         // fixedHeader: {
//         //     header: true
//         // },
//         stateSave: true, // 표시 컬럼, 컬럼 순서 등의 상태 저장
//         stateSaveParams: function (settings, data) { // 상태 저장 정보 커스텀
//             data.start = 0; // 페이징 저장 X
//             data.order = []; // 정렬 저장 X
//         },
//         language: {
//             paginate: {
//                 previous: "<i class='fas fa-angle-left'></i>",
//                 next: "<i class='fas fa-angle-right'></i>"
//             },
//         },
//         buttons: buttons
//     });
// }


// 이미지 프리뷰 팝업
$(document).on('click', 'img.img-alt', function() {
    var popupWidth = 600;
    var popupHeight = 400;
    var popupX = Math.ceil((window.screen.width - popupWidth) / 2);
    var popupY= Math.ceil((window.screen.height - popupHeight) / 2 - 50);
    window.open($(this).data('src'), 'popup', `width=${popupWidth},height=${popupHeight},left=${popupX},top=${popupY}`);
})

function getNewRowId(rows) {
    let ids = rows.map(function () {
        return $(this).data('id');
    }).get();
    let lastId = Math.max(...ids);
    return lastId + 1;
}

// 숫자 형태 포맷 (수량이나 금액의 경우, 0으로 시작하지 않게)
$(document).on('change', 'input[type=number].countable', function() {
    const num = $(this).val() * 1;
    $(this).val(num);
});

function showGoogleMap() {
    let title = sessionStorage.getItem('tran_map_title');
    if (!title) {
        title = 'Select address';
        // getAjax('tran', {str: title}, function(resp) {
        //     title = resp;
        //     sessionStorage.setItem('tran_map_title', resp);
        // });
    }
    console.log(title);
    $("#commonModal2 .modal-title").html(title);
    $("#commonModal2 .modal-dialog").addClass('modal-xl');
    getAjax('common.google_map', {}, function (resp) {
        $('#commonModal2 .modal-body').html(resp);
        $("#commonModal2").modal('show');
    });
}


// 메뉴버튼
$(document).on('click', '.menu_open', function () {
    $(this).toggleClass('active');
    $('nav.dash-sidebar').toggleClass('left');
    $('.dash-container').toggleClass('left');
})

// 공통 코드 가져오기
function getCode(route, name, sub_param=null) {
    let columnList = [];

    $(document).on("change", `.popup_${name}_code`, function(e) {
        let parentEl = $(this).parents('tr');
        // tempParentEl = $(this).parents('tr');
        parentEl = parentEl.length ? parentEl : $(this).parents('form');
        // todo 선행되어야 하는 필수값 검사 어떻게 하지?

        let subValue = '';
        if (sub_param) {
            if ($(sub_param).length > 1) {
                subValue = parentEl.find(sub_param).val();
            } else {
                subValue = $(sub_param).val();
            }
        }

        var url = `${route}/getvalue`;
        var data = {
            value: $(this).val(),
            sub_value: subValue,
            sub_value2: sub_param,
        };
        getAjax(url, data, function(response) {
            let data = response.data;
            if (response.is_success) {
                parentEl.find(`.popup_${name}_id`).val(data.id).trigger('change');
                parentEl.find(`.popup_${name}_name`).val(data.name);
                // 관련 데이터 input 값 자동 세팅
                $.each(data, function(column, value){
                    if(parentEl.find(`.input_${column}`).length) {
                        parentEl.find(`.input_${column}`).val(value);
                    }
                });
            } else {
                columnList = data; // 자동 세팅되어야 하는 컬럼 input 값 리스트

                if (!parentEl.hasClass('filter_form')) {
                    parentEl.find(`.popup_${name}_code`).val('');
                }
                parentEl.find(`.popup_${name}_id`).val('').trigger('change');
                parentEl.find(`.popup_${name}_name`).val('');
                // 관련 데이터 input 값 자동 세팅
                $.each(data, function(idx, column){
                    if(parentEl.find(`.input_${column}`).length) {
                        parentEl.find(`.input_${column}`).val('');
                    }
                });
            }
        });
    });

    $(document).on("change", `.popup_${name}_name`, function(e) {
        let parentEl = $(this).parents('tr');
        parentEl = parentEl.length ? parentEl : $(this).parents('form');

        if (!parentEl.hasClass('filter_form')) {
            parentEl.find(`.popup_${name}_name`).val('');
        }
        parentEl.find(`.popup_${name}_id`).val('').trigger('change');
        parentEl.find(`.popup_${name}_code`).val('');
        // 관련 데이터 input 값 자동 세팅
        $.each(columnList, function(idx, column){
            if(parentEl.find(`.input_${column}`).length) {
                parentEl.find(`.input_${column}`).val('');
            }
        });
    });
}

function getDate(left, mid, right, sep='') {
    const date = new Date();
    const year = date.getFullYear();
    const month = date.getMonth()+1;
    const day = date.getDate();

    const yyyy = String(year);
    const yy = yyyy.substring(2,4);
    const m = String(month);  //month 두자리로 저장
    const mm = month >= 10 ? m : '0'+m;
    const d = String(day);
    const dd = day >= 10 ? d : '0'+d;

    return eval(left) + sep + eval(mid) + sep + eval(right);
}
