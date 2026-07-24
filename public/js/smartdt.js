/*
 * SmartDT — jQuery DataTables 래퍼 (withworks 방식). SmartGrid(Tabulator)와 동일한 서버 계약을 사용한다.
 *  create(selector, { dataUrl, createUrl, updateUrl:(id)=>url, deleteUrl, columns, params, pageSize, defaults, readonly })
 *  columns 항목: { title, field, render?, editor?('input'|'number'|'list'|'tickCross'), values?, width?, align? }
 *  API: refresh(), addBlankRow(), deleteSelected()
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

    const SmartDT = {
        mono: (v) => `<span class="sdt-mono">${v ?? ''}</span>`,
        money: (v) => `<span class="sdt-mono" style="font-weight:600">₩${Number(v || 0).toLocaleString('ko-KR')}</span>`,
        badge: (map) => (v, row) => { const c = map[v] || { label: (row && row[map._labelKey]) || v, tone: 'hold' }; return `<span class="sdt-badge sdt-${c.tone}">${c.label}</span>`; },

        create(selector, opts) {
            const {
                dataUrl, createUrl, updateUrl, deleteUrl, columns,
                params = () => ({}), pageSize = 10, defaults = {}, readonly = false,
            } = opts;
            const $el = global.jQuery(selector);
            const editableFields = columns.filter(c => c.editor).map(c => c.field);

            // ── DataTables 컬럼 구성 ─────────────────────────────
            const dtCols = [];
            if (!readonly) {
                dtCols.push({
                    data: null, orderable: false, className: 'sdt-sel', width: '32px',
                    title: '<input type="checkbox" class="sdt-check-all">',
                    render: (d, type, row) => row._new ? '' : '<input type="checkbox" class="sdt-check">',
                });
            }
            columns.forEach(c => dtCols.push({
                data: c.field, title: c.title,
                className: (c.align === 'right' ? 'dt-right ' : '') + (c.editor ? 'sdt-editable' : '') + ' sdt-col-' + c.field,
                orderable: c.orderable !== false,
                render: (val, type, row) => {
                    if (type !== 'display') return val;
                    if (c.render) return c.render(val, row);
                    return (val ?? '');
                },
            }));
            if (!readonly) {
                dtCols.push({
                    data: null, orderable: false, className: 'dt-right sdt-actcol', width: '70px', title: '',
                    render: (d, type, row) => row._new
                        ? '<span class="sdt-act sdt-save" title="저장" style="color:#1e8a5b">✓</span> <span class="sdt-act sdt-cancel" title="취소" style="color:#6b7a88">✕</span>'
                        : '<span class="sdt-act sdt-del" title="삭제" style="color:#c2362b">🗑</span>',
                });
            }

            const dt = new global.DataTable($el.get(0), {
                ajax: (data, cb) => {
                    global.jQuery.getJSON(dataUrl + '?' + global.jQuery.param(Object.assign({ size: 100 }, cleanParams(params()))), (res) => cb({ data: res.data || [] }));
                },
                columns: dtCols,
                order: [],
                pageLength: pageSize,
                lengthMenu: [10, 30, 50, 100],
                fixedHeader: true,
                deferRender: true,
                language: {
                    search: '검색:', searchPlaceholder: '표 안에서 검색',
                    lengthMenu: '_MENU_ 개씩', info: '_START_–_END_ / 총 _TOTAL_건',
                    infoEmpty: '0건', infoFiltered: '(전체 _MAX_건 중)', zeroRecords: '데이터가 없습니다.',
                    emptyTable: '데이터가 없습니다.', paginate: { previous: '‹', next: '›' },
                },
            });

            const grid = {
                dt,
                refresh() { dt.ajax.reload(null, false); },
                addBlankRow() {
                    const row = Object.assign({}, defaults, { id: null, _new: true });
                    const added = dt.row.add(row).draw(false); // 먼저 그려 행/페이지 수 반영
                    dt.page('last').draw(false);                // 새 행이 있는 마지막 페이지로 이동
                    const node = added.node();
                    if (!node) return;
                    node.classList.add('dt-new');
                    node.scrollIntoView({ block: 'center' });
                    const firstField = editableFields[0];
                    const td = node.querySelector('.sdt-col-' + firstField);
                    if (td) beginEdit(td, firstField, row, node);
                },
                async deleteSelected() {
                    const ids = [];
                    $el.find('tbody .sdt-check:checked').each(function () {
                        const d = dt.row(global.jQuery(this).closest('tr')).data();
                        if (d && d.id) ids.push(d.id);
                    });
                    if (!ids.length) { toast('선택된 항목이 없습니다.', 'warn'); return; }
                    const ok = global.confirmDialog
                        ? await global.confirmDialog({ title: '일괄 삭제', message: `${ids.length}건을 삭제할까요?`, tone: 'crit', confirmText: '삭제' })
                        : confirm(`${ids.length}건 삭제?`);
                    if (!ok) return;
                    const { ok: success, data } = await api(deleteUrl, 'DELETE', { ids });
                    if (success) { toast(`${data?.deleted ?? ids.length}건이 삭제되었습니다.`, 'ok'); grid.refresh(); }
                    else toast(data?.message || '삭제에 실패했습니다.', 'crit');
                },
            };

            // ── 인라인 편집 ─────────────────────────────────────
            function colDef(field) { return columns.find(c => c.field === field); }
            function beginEdit(td, field, rowData, tr) {
                const def = colDef(field);
                if (!def || !def.editor || td.querySelector('.sdt-cell-edit')) return;
                const cur = rowData[field];
                let input;
                if (def.editor === 'list') {
                    input = document.createElement('select');
                    input.className = 'sdt-cell-edit';
                    Object.entries(def.values || {}).forEach(([k, v]) => {
                        const o = document.createElement('option'); o.value = k; o.textContent = v;
                        if (String(k) === String(cur)) o.selected = true; input.appendChild(o);
                    });
                } else if (def.editor === 'tickCross') {
                    input = document.createElement('select');
                    input.className = 'sdt-cell-edit';
                    [['1', '사용'], ['0', '중지']].forEach(([k, v]) => { const o = document.createElement('option'); o.value = k; o.textContent = v; if (String(cur ? 1 : 0) === k) o.selected = true; input.appendChild(o); });
                } else {
                    input = document.createElement('input');
                    input.className = 'sdt-cell-edit';
                    input.type = def.editor === 'number' ? 'number' : 'text';
                    input.value = cur ?? '';
                }
                td.innerHTML = ''; td.appendChild(input); input.focus();
                if (input.select) input.select();
                let done = false;
                const commit = async () => {
                    if (done) return; done = true;
                    let val = input.value;
                    if (def.editor === 'number') val = val === '' ? 0 : Number(val);
                    if (def.editor === 'tickCross') val = val === '1';
                    rowData[field] = val;
                    if (rowData._new) { redrawRow(tr, rowData); return; }
                    const { ok, status, data } = await api(updateUrl(rowData.id), 'PATCH', { field, value: val });
                    if (ok) { toast('수정되었습니다.', 'ok'); Object.assign(rowData, data && typeof data === 'object' ? data : {}); redrawRow(tr, rowData); }
                    else { toast(status === 422 ? firstErr(data) : (data?.message || '수정 실패'), 'crit'); redrawRow(tr, rowData); }
                };
                input.addEventListener('blur', commit);
                input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); input.blur(); } if (e.key === 'Escape') { done = true; redrawRow(tr, rowData); } });
                if (def.editor === 'list' || def.editor === 'tickCross') input.addEventListener('change', () => input.blur());
            }
            function redrawRow(tr, rowData) { dt.row(tr).data(rowData).draw(false); if (rowData._new) dt.row(tr).node().classList.add('dt-new'); }
            function firstErr(d) { if (d?.errors) { const k = Object.keys(d.errors)[0]; return d.errors[k][0]; } return d?.message || '입력값을 확인하세요.'; }

            async function saveNewRow(tr) {
                const d = Object.assign({}, dt.row(tr).data()); delete d._new; delete d.id;
                const { ok, status, data } = await api(createUrl, 'POST', d);
                if (ok) {
                    if (data.temp_password) toast(`임시 비밀번호: ${data.temp_password} (안전하게 전달하세요)`, 'info', '등록 완료');
                    else toast(data.message || '등록되었습니다.', 'ok');
                    grid.refresh();
                } else if (status === 422) toast(firstErr(data), 'crit');
                else toast(data?.message || '등록에 실패했습니다.', 'crit');
            }

            // 이벤트 위임
            $el.on('click', 'tbody td.sdt-editable', function () {
                const tr = this.closest('tr'); const rowData = dt.row(tr).data(); if (!rowData) return;
                const field = (this.className.match(/sdt-col-([a-zA-Z_]+)/) || [])[1];
                if (field) beginEdit(this, field, rowData, tr);
            });
            $el.on('click', '.sdt-del', async function () {
                const tr = this.closest('tr'); const d = dt.row(tr).data();
                const ok = global.confirmDialog ? await global.confirmDialog({ title: '삭제', message: '이 항목을 삭제할까요?', tone: 'crit', confirmText: '삭제' }) : confirm('삭제?');
                if (!ok) return;
                const { ok: success, data } = await api(deleteUrl, 'DELETE', { ids: [d.id] });
                if (success) { toast('삭제되었습니다.', 'ok'); grid.refresh(); } else toast(data?.message || '삭제 실패', 'crit');
            });
            $el.on('click', '.sdt-save', function () { saveNewRow(this.closest('tr')); });
            $el.on('click', '.sdt-cancel', function () { dt.row(this.closest('tr')).remove().draw(false); });
            $el.on('change', '.sdt-check-all', function () { $el.find('tbody .sdt-check').prop('checked', this.checked).closest('tr').toggleClass('selected', this.checked); });
            $el.on('change', '.sdt-check', function () { global.jQuery(this).closest('tr').toggleClass('selected', this.checked); });

            // 업로드 후 새로고침 훅(x-excel-tools 공용)
            global.__smartGridRefresh = () => grid.refresh();
            return grid;
        },
    };

    global.SmartDT = SmartDT;
})(window);
