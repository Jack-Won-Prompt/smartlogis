/*
 * wwgrid-connect — wwGrid(바닐라 배치편집 그리드)와 SmartLogis 서버를 잇는 얇은 글루.
 * wwGrid.js/.css 는 100% 원본 그대로 사용하고, 여기서는 공개 API(setData/getModifiedRows/
 * addRow/removeCheckedRows/resetModified/getCheckedRows/downloadExcel)만 호출한다.
 *
 * WWGrid.connect(selector, {
 *   dataUrl,                 // GET 목록(JSON {data,total})
 *   batchUrl,                // POST 배치 저장 {updated,added,deleted}
 *   columns,                 // [{ title, field, editor?('text'|'number'|'date'|'checkbox'|'list'), values?, align?, width?, defaultValue? }]
 *   params:()=>({}),         // 검색/필터 파라미터
 *   rowKey='id', readonly=false, defaults={}, onRowClick, screenName, cap=500, height, summary=false,
 *   buttons:{ add, delete, save, reset }   // 각 버튼 요소 id(선택)
 * }) → { grid, refresh, save, addRow, removeRows, reset, exportExcel }
 */
(function (global) {
    'use strict';

    function csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; }
    function toast(m, t, title) { if (global.toast) global.toast(m, t || 'info', title); }
    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        let data = null; try { data = await res.json(); } catch (e) { /* */ }
        return { ok: res.ok, status: res.status, data };
    }
    function cleanParams(p) { const o = {}; Object.entries(p || {}).forEach(([k, v]) => { if (v !== '' && v != null) o[k] = v; }); return o; }
    function firstErr(d) { if (d?.errors) { const k = Object.keys(d.errors)[0]; return d.errors[k][0]; } return d?.message || '입력값을 확인하세요.'; }

    // 우리 컬럼 정의 → wwGrid 컬럼 정의
    function mapColumns(cols, editable) {
        return cols.map((c) => {
            const col = { header: c.title, name: c.field, sortable: c.sortable !== false };
            if (c.width || c.minWidth) col.width = c.width || c.minWidth;
            if (c.align) col.align = c.align;
            const toOptions = (values) => Object.entries(values || {}).map(([value, label]) => ({ value: (value !== '' && !isNaN(value)) ? Number(value) : value, label: String(label) }));
            if (c.editor === 'list' && c.values) {
                // combo: 편집형이면 편집기, 읽기전용이면 라벨 표시용으로도 options 유지(전체 editable=false 로 편집 차단)
                col.editor = 'combo';
                col.options = toOptions(c.values);
            } else if (editable && c.editor) {
                if (c.editor === 'checkbox') col.editor = 'checkbox';
                else if (c.editor === 'date') col.editor = 'date';
                else if (c.editor === 'number') col.editor = 'number';
                else col.editor = 'text';
            }
            if (c.defaultValue !== undefined) col.defaultValue = c.defaultValue;
            return col;
        });
    }

    const WWGrid = {
        connect(selector, opts) {
            const {
                dataUrl, batchUrl, columns, params = () => ({}), rowKey = 'id',
                readonly = false, defaults = {}, onRowClick, screenName = 'grid',
                cap = 500, height = null, summary = false, buttons = {},
            } = opts;
            const host = document.querySelector(selector);
            const editable = !readonly;

            // 그리드가 남은 뷰포트 높이를 채우도록 계산(페이지 스크롤 대신 그리드 내부만 스크롤).
            function fitHeight() {
                const top = host.getBoundingClientRect().top;
                return Math.max(300, Math.floor(global.innerHeight - top - 88));
            }

            const grid = new global.wwGrid({
                el: host,
                rowKey,
                editable,
                rowCheckbox: !readonly,
                rowNumber: true,
                toolbar: false, // 엑셀/추가/삭제 등은 외부 버튼으로 연동
                footer: { total: true, selected: !readonly, modified: !readonly },
                summary,
                height: height || fitHeight(),
                columns: mapColumns(columns, editable),
            });

            // 창 크기 변화 시 높이 재조정(height 를 명시하지 않은 경우만 자동).
            if (!height) {
                const applyHeight = () => { if (grid._wrapEl) grid._wrapEl.style.maxHeight = fitHeight() + 'px'; };
                global.addEventListener('resize', applyHeight);
                setTimeout(applyHeight, 0);
            }

            function load() {
                const qs = new URLSearchParams(Object.assign({ size: cap }, cleanParams(params())));
                fetch(dataUrl + '?' + qs, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.json())
                    .then((res) => {
                        const rows = res.data || [];
                        grid.setData(rows);
                        if (res.total && res.total > rows.length) {
                            toast(`상위 ${rows.length}건만 표시됩니다(전체 ${res.total}건). 검색으로 좁혀 주세요.`, 'info');
                        }
                    })
                    .catch(() => toast('데이터를 불러오지 못했습니다.', 'crit'));
            }

            const api = {
                grid,
                refresh() { load(); },
                addRow(row) { grid.addRow(Object.assign({}, defaults, row || {})); },
                reset() { grid.resetModified(); },
                async removeRows() {
                    const checked = grid.getCheckedRows();
                    if (!checked.length) { toast('선택된 항목이 없습니다.', 'warn'); return; }
                    const ok = await global.confirmDialog({ title: '삭제', message: `${checked.length}건을 삭제 목록에 넣습니다. [저장] 시 반영됩니다.`, tone: 'crit', confirmText: '삭제 목록에' });
                    if (!ok) return;
                    grid.removeCheckedRows();
                },
                async save() {
                    const mod = grid.getModifiedRows();
                    if (!mod.updated.length && !mod.added.length && !mod.deleted.length) { toast('변경사항이 없습니다.', 'warn'); return; }
                    const { ok, status, data } = await postJSON(batchUrl, mod);
                    if (ok) {
                        const parts = [];
                        if (data?.added != null) parts.push(`추가 ${data.added}`);
                        if (data?.updated != null) parts.push(`수정 ${data.updated}`);
                        if (data?.deleted != null) parts.push(`삭제 ${data.deleted}`);
                        toast(parts.length ? `저장 완료 (${parts.join(' · ')})` : (data?.message || '저장되었습니다.'), 'ok', data?.temp_passwords ? '임시 비밀번호 발급' : null);
                        if (data?.temp_passwords && data.temp_passwords.length) {
                            toast('임시 비밀번호: ' + data.temp_passwords.map((t) => `${t.email} → ${t.temp}`).join(', '), 'info', '신규 계정');
                        }
                        load();
                    } else if (status === 422) toast(firstErr(data), 'crit');
                    else toast(data?.message || '저장에 실패했습니다.', 'crit');
                },
                exportExcel() { grid.downloadExcel({ filename: screenName + '-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') }); },
            };

            // 외부 버튼 연동
            const bind = (id, fn) => { if (id) { const el = document.getElementById(id); if (el) el.addEventListener('click', fn); } };
            bind(buttons.add, () => api.addRow());
            bind(buttons.delete, () => api.removeRows());
            bind(buttons.save, () => api.save());
            bind(buttons.reset, () => api.reset());

            // 읽기전용/문서 화면: 행 클릭 → 모달
            if (typeof onRowClick === 'function') {
                host.addEventListener('click', (e) => {
                    if (e.target.closest('input,button,a,.cg-checkbox-display,.cg-col-check')) return;
                    const cell = e.target.closest('[data-row-index]');
                    if (!cell) return;
                    const ri = parseInt(cell.dataset.rowIndex, 10);
                    if (isNaN(ri)) return;
                    const row = grid.getData()[ri];
                    if (row) onRowClick(row, e);
                });
            }

            load();
            global.__smartGridRefresh = () => load();
            host.__wwgrid = api;
            return api;
        },
    };

    global.WWGrid = WWGrid;
})(window);
