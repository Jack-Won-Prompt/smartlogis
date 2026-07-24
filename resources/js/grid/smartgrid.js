/*
 * SmartGrid — Tabulator 래퍼 (브랜드 테마 + 인라인 편집/추가/선택/일괄삭제).
 *
 * 서버 계약:
 *  - data URL(GET): Tabulator remote pagination. 응답 { last_page, data:[...] }
 *  - create URL(POST): 새 행 저장 → { id, ... } 반환
 *  - update URL(PATCH, {id}): 인라인 셀 저장 { field, value } → 200/422
 *  - delete URL(DELETE): { ids:[...] } 일괄 삭제
 *
 * 사용:
 *  const grid = SmartGrid.create('#grid', { dataUrl, createUrl, updateUrl:(id)=>`...`, deleteUrl, columns, params:()=>({...}), defaults:{...} });
 *  grid.addBlankRow(); grid.deleteSelected(); grid.refresh(update params);
 */

import { TabulatorFull as Tabulator } from 'tabulator-tables';

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
}

async function apiFetch(url, method, body) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    let data = null;
    try {
        data = await res.json();
    } catch (e) {
        /* no body */
    }
    return { ok: res.ok, status: res.status, data };
}

function toast(message, tone = 'info', title = null) {
    if (window.toast) window.toast(message, tone, title);
}

const SmartGrid = {
    /**
     * @param {string} selector
     * @param {object} opts
     */
    create(selector, opts) {
        const {
            dataUrl,
            createUrl,
            updateUrl, // (id) => url
            deleteUrl,
            columns,
            params = () => ({}),
            defaults = {},
            pageSize = 10,
            onAfter = null,
            readonly = false,
        } = opts;

        // 읽기전용: 체크박스/액션 컬럼 없이 사용자 컬럼만
        const cols = readonly
            ? columns.map((c) => ({ ...c, editor: undefined }))
            : [
                {
                    formatter: 'rowSelection',
                    titleFormatter: 'rowSelection',
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerSort: false,
                    width: 44,
                    frozen: true,
                    cellClick: (e, cell) => cell.getRow().toggleSelect(),
                },
                ...columns,
                {
                    title: '',
                    field: '_actions',
                    width: 96,
                    headerSort: false,
                    hozAlign: 'right',
                    frozen: true,
                    formatter: (cell) => {
                        const row = cell.getRow().getData();
                        if (row._new) {
                            return `<span class="sg-act sg-save" title="저장">✓</span><span class="sg-act sg-cancel" title="취소">✕</span>`;
                        }
                        return `<span class="sg-act sg-del" title="삭제">🗑</span>`;
                    },
                    cellClick: (e, cell) => {
                        const t = e.target.closest('.sg-act');
                        if (!t) return;
                        const row = cell.getRow();
                        if (t.classList.contains('sg-save')) grid._saveNewRow(row);
                        else if (t.classList.contains('sg-cancel')) row.delete();
                        else if (t.classList.contains('sg-del')) grid._deleteRow(row);
                    },
                },
            ];

        const table = new Tabulator(selector, {
            layout: 'fitColumns',
            height: 'auto',
            placeholder: '데이터가 없습니다.',
            columns: cols,
            reactiveData: false,
            movableColumns: true,
            resizableColumns: true,
            selectableRows: !readonly,

            // 원격 페이지네이션
            pagination: true,
            paginationMode: 'remote',
            paginationSize: pageSize,
            paginationSizeSelector: [10, 30, 50, 100],
            paginationCounter: 'rows',
            sortMode: 'remote',
            ajaxURL: dataUrl,
            ajaxConfig: 'GET',
            ajaxParams: () => params(),
            ajaxResponse: (url, p, response) => response, // { last_page, data }

            langs: {
                default: {
                    pagination: { first: '«', last: '»', prev: '‹', next: '›', page_size: '페이지당', counter: { showing: '', of: '/', rows: '건' } },
                },
            },
        });

        const grid = {
            table,

            refresh() {
                table.setData();
            },

            addBlankRow() {
                table.addRow({ ...defaults, id: null, _new: true }, true).then((row) => {
                    // 첫 편집 가능한 셀에 포커스
                    const first = row.getCells().find((c) => c.getColumn().getDefinition().editor);
                    if (first) first.edit(true);
                });
            },

            async deleteSelected() {
                const rows = table.getSelectedData().filter((r) => !r._new && r.id);
                if (rows.length === 0) {
                    toast('선택된 항목이 없습니다.', 'warn');
                    return;
                }
                const ok = window.confirmDialog
                    ? await window.confirmDialog({ title: '일괄 삭제', message: `${rows.length}건을 삭제할까요?`, tone: 'crit', confirmText: '삭제' })
                    : confirm(`${rows.length}건 삭제?`);
                if (!ok) return;

                const { ok: success, data } = await apiFetch(deleteUrl, 'DELETE', { ids: rows.map((r) => r.id) });
                if (success) {
                    toast(`${data?.deleted ?? rows.length}건이 삭제되었습니다.`, 'ok');
                    table.setData();
                } else {
                    toast(data?.message || '삭제에 실패했습니다.', 'crit');
                }
            },

            async _saveNewRow(row) {
                const d = { ...row.getData() };
                delete d._new;
                delete d.id;
                const { ok, status, data } = await apiFetch(createUrl, 'POST', d);
                if (ok) {
                    row.update({ ...data, _new: false });
                    row.reformat();
                    if (data.temp_password) {
                        toast(`임시 비밀번호: ${data.temp_password} (안전하게 전달하세요)`, 'info', '등록 완료');
                    } else {
                        toast(data.message || '등록되었습니다.', 'ok');
                    }
                    table.setData();
                } else if (status === 422) {
                    toast(firstError(data), 'crit');
                } else {
                    toast(data?.message || '등록에 실패했습니다.', 'crit');
                }
            },

            async _deleteRow(row) {
                const d = row.getData();
                const ok = window.confirmDialog
                    ? await window.confirmDialog({ title: '삭제', message: '이 항목을 삭제할까요?', tone: 'crit', confirmText: '삭제' })
                    : confirm('삭제?');
                if (!ok) return;
                const { ok: success, data } = await apiFetch(deleteUrl, 'DELETE', { ids: [d.id] });
                if (success) {
                    toast('삭제되었습니다.', 'ok');
                    table.setData();
                } else {
                    toast(data?.message || '삭제에 실패했습니다.', 'crit');
                }
            },
        };

        // 인라인 셀 편집 저장(기존 행)
        table.on('cellEdited', async (cell) => {
            const row = cell.getRow().getData();
            const field = cell.getField();
            if (row._new || !row.id) return; // 새 행은 저장 버튼에서 일괄 전송

            const { ok, status, data } = await apiFetch(updateUrl(row.id), 'PATCH', { field, value: cell.getValue() });
            if (ok) {
                toast('수정되었습니다.', 'ok');
                if (data && typeof data === 'object') cell.getRow().update(data);
            } else {
                cell.restoreOldValue();
                toast(status === 422 ? firstError(data) : data?.message || '수정에 실패했습니다.', 'crit');
            }
        });

        if (onAfter) table.on('dataProcessed', onAfter);

        return grid;
    },
};

function firstError(data) {
    if (data?.errors) {
        const k = Object.keys(data.errors)[0];
        return data.errors[k][0];
    }
    return data?.message || '입력값을 확인하세요.';
}

// 공통 포매터/에디터 헬퍼
SmartGrid.money = (cell) => '₩' + Number(cell.getValue() || 0).toLocaleString('ko-KR');
SmartGrid.mono = (cell) => `<span class="sg-mono">${cell.getValue() ?? ''}</span>`;
SmartGrid.badge = (map) => (cell) => {
    const v = cell.getValue();
    const conf = map[v] || { label: v, tone: 'hold' };
    return `<span class="sg-badge sg-${conf.tone}">${conf.label}</span>`;
};

window.SmartGrid = SmartGrid;
export default SmartGrid;
