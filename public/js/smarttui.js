/*
 * SmartTUI — Toast UI Grid(TUI Grid 4.21) 래퍼. fulfillment mv2 방식의 그리드.
 * SmartGrid/SmartDT 와 동일한 서버 계약(data/store/update/bulkDestroy)을 사용한다.
 *   create(selector, { dataUrl, createUrl, updateUrl:(id)=>url, deleteUrl, columns, params, pageSize, defaults, readonly, onRowClick })
 *   columns 항목: { title, field, editor?('text'|'number'|'list'|'checkbox'), values?, html?(v,row)=>str, align?, width?, sortable? }
 */
(function (global) {
    'use strict';

    function csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; }
    function toast(m, t, title) { if (global.toast) global.toast(m, t || 'info', title); }
    async function api(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined,
        });
        let data = null; try { data = await res.json(); } catch (e) { /* */ }
        return { ok: res.ok, status: res.status, data };
    }
    function cleanParams(p) { const o = {}; Object.entries(p || {}).forEach(([k, v]) => { if (v !== '' && v != null) o[k] = v; }); return o; }
    function firstErr(d) { if (d?.errors) { const k = Object.keys(d.errors)[0]; return d.errors[k][0]; } return d?.message || '입력값을 확인하세요.'; }

    // HTML 셀 렌더러(뱃지/모노/버튼 등 임의 HTML)
    function makeHtmlRenderer() {
        return class {
            constructor(props) { this.el = document.createElement('div'); this.el.className = 'stui-cell'; this.render(props); }
            getElement() { return this.el; }
            render(props) {
                const opt = props.columnInfo.renderer.options || {};
                const row = props.grid.getRow(props.rowKey) || {};
                this.el.innerHTML = opt.html ? opt.html(props.value, row) : (props.value ?? '');
            }
        };
    }

    const SmartTUI = {
        mono: (v) => `<span class="stui-mono">${v ?? ''}</span>`,
        money: (v) => `<span class="stui-mono" style="font-weight:600">₩${Number(v || 0).toLocaleString('ko-KR')}</span>`,
        // 상태 뱃지: map = { VALUE: {label, tone}, _labelKey? }. row[_labelKey] 로 라벨 대체 가능.
        badge: (map) => (v, row) => { const c = map[v] || { label: (map._labelKey && row) ? row[map._labelKey] : v, tone: 'hold' }; return `<span class="stui-badge stui-${c.tone}">${c.label ?? v}</span>`; },

        create(selector, opts) {
            const {
                dataUrl, createUrl, updateUrl, deleteUrl, columns,
                params = () => ({}), pageSize = 10, defaults = {}, readonly = false, onRowClick,
            } = opts;
            const host = document.querySelector(selector);
            const HtmlRenderer = makeHtmlRenderer();

            // ── 컬럼 구성 ────────────────────────────────────────
            const tuiCols = columns.map((c) => {
                const col = { name: c.field, header: c.title, sortable: c.sortable !== false, align: c.align || 'left' };
                if (c.width) col.width = c.width;
                // 편집기
                if (!readonly && c.editor) {
                    if (c.editor === 'list') {
                        col.editor = { type: 'select', options: { listItems: Object.entries(c.values || {}).map(([value, text]) => ({ text: String(text), value: isNaN(value) ? value : Number(value) })) } };
                    } else if (c.editor === 'checkbox') {
                        col.editor = { type: 'select', options: { listItems: [{ text: '사용', value: 1 }, { text: '중지', value: 0 }] } };
                    } else if (c.editor === 'date') {
                        col.editor = { type: 'datePicker', options: { format: 'yyyy-MM-dd' } }; // 날짜 편집(tui-date-picker)
                    } else {
                        col.editor = 'text';
                    }
                }
                // 표시(HTML 렌더러 우선, 없으면 list 라벨 매핑)
                if (c.html) {
                    col.renderer = { type: HtmlRenderer, options: { html: c.html } };
                } else if (c.editor === 'list' && c.values) {
                    col.formatter = ({ value, row }) => c.values[value] ?? row[c.field + '_label'] ?? value ?? '';
                } else if (c.editor === 'checkbox') {
                    col.renderer = { type: HtmlRenderer, options: { html: (v) => v ? '<span class="stui-badge stui-ok">사용</span>' : '<span class="stui-badge stui-hold">중지</span>' } };
                }
                return col;
            });

            // 액션 컬럼(삭제 / 신규행 저장·취소)
            if (!readonly) {
                tuiCols.push({
                    name: '_act', header: ' ', width: 76, align: 'center', sortable: false,
                    renderer: {
                        type: HtmlRenderer,
                        options: { html: (v, row) => row._new
                            ? '<span class="stui-act stui-save" title="저장" style="color:#1e8a5b">✓</span> <span class="stui-act stui-cancel" title="취소" style="color:#6b7a88">✕</span>'
                            : '<span class="stui-act stui-del" title="삭제" style="color:#c2362b">🗑</span>' },
                    },
                });
            }

            const grid = new global.tui.Grid({
                el: host,
                data: [],
                columns: tuiCols,
                rowHeaders: readonly ? [] : ['checkbox'],
                bodyHeight: opts.height || 'auto',
                minBodyHeight: 200,
                scrollX: true,
                columnOptions: { resizable: true },            // 열 너비 드래그
                draggable: !!opts.rowDraggable,                 // 행 드래그(옵션)
                copyOptions: { useFormattedValue: false },      // 셀 범위 선택·복사(드래그)
                pageOptions: { useClient: true, perPage: pageSize },
                contextMenu: () => [[
                    { name: 'copy', label: '복사' },
                    { name: 'copyColumns', label: '열 복사' },
                    { name: 'copyRows', label: '행 복사' },
                ], [
                    { name: 'excel', label: '엑셀 다운로드', action: () => wrap.exportExcel() },
                    { name: 'refresh', label: '새로고침', action: () => load() },
                ]],
            });

            const wrap = {
                grid,
                refresh() { load(); },
                addBlankRow() {
                    const row = Object.assign({}, defaults, { id: null, _new: true });
                    grid.prependRow(row, { focus: true });
                },
                async deleteSelected() {
                    const rows = grid.getCheckedRows().filter((r) => r.id);
                    if (!rows.length) { toast('선택된 항목이 없습니다.', 'warn'); return; }
                    const ok = global.confirmDialog
                        ? await global.confirmDialog({ title: '일괄 삭제', message: `${rows.length}건을 삭제할까요?`, tone: 'crit', confirmText: '삭제' })
                        : confirm(`${rows.length}건 삭제?`);
                    if (!ok) return;
                    const { ok: success, data } = await api(deleteUrl, 'DELETE', { ids: rows.map((r) => r.id) });
                    if (success) { toast(`${data?.deleted ?? rows.length}건이 삭제되었습니다.`, 'ok'); load(); }
                    else toast(data?.message || '삭제에 실패했습니다.', 'crit');
                },
                exportExcel() {
                    try {
                        grid.export('xlsx', { fileName: (opts.screenName || 'grid') + '-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') });
                    } catch (e) {
                        try { grid.export('csv'); } catch (e2) { toast('내보내기를 사용할 수 없습니다.', 'warn'); }
                    }
                },
            };

            function load() {
                global.jQuery
                    ? global.jQuery.getJSON(dataUrl + '?' + global.jQuery.param(Object.assign({ size: 100 }, cleanParams(params()))), (res) => grid.resetData(res.data || []))
                    : fetch(dataUrl + '?' + new URLSearchParams(Object.assign({ size: 100 }, cleanParams(params())))).then((r) => r.json()).then((res) => grid.resetData(res.data || []));
            }

            // ── 인라인 편집 → 서버 반영 ───────────────────────────
            if (!readonly) {
                grid.on('afterChange', (ev) => {
                    (ev.changes || []).forEach(async (ch) => {
                        const row = grid.getRow(ch.rowKey);
                        if (!row || row._new || !row.id) return; // 신규행은 저장 버튼에서 일괄 전송
                        const { ok, status, data } = await api(updateUrl(row.id), 'PATCH', { field: ch.columnName, value: ch.value });
                        if (ok) { toast('수정되었습니다.', 'ok'); if (data && typeof data === 'object') grid.setRow(ch.rowKey, Object.assign({}, row, data)); }
                        else { grid.setValue(ch.rowKey, ch.columnName, ch.prevValue); toast(status === 422 ? firstErr(data) : (data?.message || '수정 실패'), 'crit'); }
                    });
                });

                // 액션 클릭(저장/취소/삭제)
                grid.on('click', async (ev) => {
                    const t = ev.nativeEvent?.target;
                    if (!t || !t.classList) return;
                    const row = grid.getRow(ev.rowKey);
                    if (t.classList.contains('stui-save')) {
                        const d = Object.assign({}, row); delete d._new; delete d.id; delete d.rowKey;
                        const { ok, status, data } = await api(createUrl, 'POST', d);
                        if (ok) { toast(data.temp_password ? `임시 비밀번호: ${data.temp_password}` : (data.message || '등록되었습니다.'), data.temp_password ? 'info' : 'ok', data.temp_password ? '등록 완료' : null); load(); }
                        else if (status === 422) toast(firstErr(data), 'crit');
                        else toast(data?.message || '등록에 실패했습니다.', 'crit');
                    } else if (t.classList.contains('stui-cancel')) {
                        grid.removeRow(ev.rowKey);
                    } else if (t.classList.contains('stui-del')) {
                        const ok = global.confirmDialog ? await global.confirmDialog({ title: '삭제', message: '이 항목을 삭제할까요?', tone: 'crit', confirmText: '삭제' }) : confirm('삭제?');
                        if (!ok) return;
                        const { ok: success, data } = await api(deleteUrl, 'DELETE', { ids: [row.id] });
                        if (success) { toast('삭제되었습니다.', 'ok'); load(); } else toast(data?.message || '삭제 실패', 'crit');
                    }
                });
            }

            // 행 클릭(문서 화면 모달)
            if (typeof onRowClick === 'function') {
                grid.on('click', (ev) => { if (ev.targetType === 'cell' && ev.rowKey != null) onRowClick(grid.getRow(ev.rowKey), ev); });
            }

            load();

            // ── 열 재정렬(TUI 미지원 → 헤더 HTML5 드래그로 구현) ──
            function enableColumnReorder() {
                host.querySelectorAll('.tui-grid-cell-header[data-column-name]').forEach((th) => {
                    const name = th.getAttribute('data-column-name');
                    if (!name || name.charAt(0) === '_' || th.__reorder) return;
                    th.__reorder = true;
                    th.setAttribute('draggable', 'true');
                    th.style.cursor = 'grab';
                    th.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/col', name); });
                    th.addEventListener('dragover', (e) => e.preventDefault());
                    th.addEventListener('drop', (e) => {
                        e.preventDefault();
                        const from = e.dataTransfer.getData('text/col');
                        if (from && from !== name) reorderColumns(from, name);
                    });
                });
            }
            function reorderColumns(from, to) {
                const names = tuiCols.map((c) => c.name);
                const fi = names.indexOf(from);
                const ti = names.indexOf(to);
                if (fi < 0 || ti < 0) return;
                const [moved] = tuiCols.splice(fi, 1);
                tuiCols.splice(ti, 0, moved);
                grid.setColumns(tuiCols);
                setTimeout(enableColumnReorder, 120);
            }
            setTimeout(enableColumnReorder, 400);

            global.__smartGridRefresh = () => load();
            host.__smartTui = wrap; // 디버깅/테스트용 핸들
            return wrap;
        },
    };

    global.SmartTUI = SmartTUI;
})(window);
