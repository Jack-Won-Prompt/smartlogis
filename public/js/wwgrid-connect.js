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
                readonly = false, defaults = {}, onRowClick, onRowDblClick, screenName = 'grid',
                cap = 500, height = null, summary = false, buttons = {},
                paged = false, pageSize = 30,   // paged:true → 서버 페이징(10/30/50/100)
                exportButton = true, exportFilename = null,   // 우상단 엑셀 다운로드(현재 필터 전체 데이터)
                exportInto = null,   // 지정 시 해당 요소(기존 액션행)에 버튼을 합류시켜 한 줄로 표시
            } = opts;
            const host = document.querySelector(selector);
            const editable = !readonly;
            // 페이징 상태
            let page = 1, curSize = pageSize, lastPage = 1, total = 0, pagerEl = null;

            // 그리드가 남은 뷰포트 높이를 채우도록 계산(페이지 스크롤 대신 그리드 내부만 스크롤).
            // 페이징 모드는 그리드 아래 페이저 높이만큼 더 비워 페이저가 항상 뷰포트에 보이게 한다.
            function fitHeight() {
                const top = host.getBoundingClientRect().top;
                const reserve = paged ? 148 : 88;
                return Math.max(300, Math.floor(global.innerHeight - top - reserve));
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
                // 모든 그리드는 뷰포트를 채우는 고정 높이 + 내부 스크롤(거래처 관리 표준). 페이징도 동일.
                height: height || fitHeight(),
                columns: mapColumns(columns, editable),
            });

            // 그리드가 항상 남은 뷰포트를 꽉 채우도록 고정 높이 지정(행이 적어도 하단 공백 없음).
            // 행이 많으면 내부 스크롤. 창 크기 변화에도 재조정. 페이징 모드도 동일 적용.
            if (!height) {
                const applyHeight = () => {
                    if (!grid._wrapEl) return;
                    const h = fitHeight() + 'px';
                    grid._wrapEl.style.height = h;
                    grid._wrapEl.style.maxHeight = h;
                };
                applyHeight();
                global.addEventListener('resize', applyHeight);
            }

            // 우상단 엑셀 다운로드 — 현재 필터의 전체 데이터를 받아 내보낸다(페이징이어도 전체, 콤보는 라벨).
            async function exportAll(btn) {
                try {
                    if (btn) btn.disabled = true;
                    const qs = new URLSearchParams(Object.assign({ size: 100000, page: 1 }, cleanParams(params())));
                    const res = await fetch(dataUrl + '?' + qs, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
                    const rows = res.data || [];
                    if (!rows.length) { toast('내보낼 데이터가 없습니다.', 'warn'); return; }
                    // ExcelExporter 가 참조하는 멤버만 갖춘 경량 프록시 → 전체 행을 라이브 그리드 훼손 없이 내보낸다.
                    const proxy = {
                        columns: grid.columns, columnGroups: grid.columnGroups,
                        summary: grid.summary, theme: grid.theme,
                        getData: () => rows, getCheckedRows: () => [],
                        _formatDisplay: (v, c) => grid._formatDisplay(v, c),
                    };
                    const fname = (exportFilename || screenName) + '-' + new Date().toISOString().slice(0, 10).replace(/-/g, '');
                    grid.downloadExcel.call(proxy, { filename: fname });
                    toast(`엑셀 ${rows.length.toLocaleString()}건을 내려받았습니다.`, 'ok');
                } catch (e) {
                    toast('엑셀 내보내기에 실패했습니다.', 'crit');
                } finally {
                    if (btn) btn.disabled = false;
                }
            }

            // 우상단 다운로드 버튼. exportInto 가 있으면 기존 액션행에 합류(한 줄), 없으면 그리드 위 자체 툴바.
            if (exportButton) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn-ghost !py-2 !text-sm';
                btn.innerHTML = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg> 엑셀 다운로드';
                btn.addEventListener('click', () => exportAll(btn));
                const target = exportInto ? document.querySelector(exportInto) : null;
                if (target) {
                    target.insertBefore(btn, target.firstChild);   // 액션행 왼쪽에 합류(주 버튼은 오른쪽 유지)
                } else {
                    const bar = document.createElement('div');
                    bar.className = 'cg-grid-toolbar mb-2 mt-4 flex justify-end';   // 필터와 붙지 않게 상단 여백
                    bar.appendChild(btn);
                    host.parentNode.insertBefore(bar, host);
                }
            }

            function load() {
                const base = paged ? { size: curSize, page } : { size: cap };
                const qs = new URLSearchParams(Object.assign(base, cleanParams(params())));
                fetch(dataUrl + '?' + qs, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.json())
                    .then((res) => {
                        const rows = res.data || [];
                        grid.setData(rows);
                        if (paged) {
                            lastPage = res.last_page || 1;
                            total = res.total != null ? res.total : rows.length;
                            if (page > lastPage) { page = Math.max(1, lastPage); }
                            renderPager();
                        } else if (res.total && res.total > rows.length) {
                            toast(`상위 ${rows.length}건만 표시됩니다(전체 ${res.total}건). 검색으로 좁혀 주세요.`, 'info');
                        }
                    })
                    .catch(() => toast('데이터를 불러오지 못했습니다.', 'crit'));
            }

            // 페이징 바(10/30/50/100) — 그리드 바로 아래에 렌더.
            function renderPager() {
                if (!pagerEl) {
                    pagerEl = document.createElement('div');
                    pagerEl.className = 'cg-pager mt-3 flex flex-wrap items-center justify-between gap-3 text-sm text-ink-500';
                    host.parentNode.insertBefore(pagerEl, host.nextSibling);
                }
                const sizes = [10, 30, 50, 100];
                pagerEl.innerHTML =
                    '<div class="flex items-center gap-2"><span>페이지당</span>' +
                    '<select class="cg-pagesize rounded-lg border-line bg-surface-1 py-1.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">' +
                    sizes.map((s) => `<option value="${s}"${s === curSize ? ' selected' : ''}>${s}</option>`).join('') +
                    `</select><span class="text-ink-400">· 전체 <b class="text-ink-700">${total.toLocaleString()}</b>건</span></div>` +
                    '<div class="flex items-center gap-1">' +
                    `<button class="cg-first rounded-lg border border-line px-2.5 py-1.5 hover:bg-surface-2 disabled:opacity-40"${page <= 1 ? ' disabled' : ''}>«</button>` +
                    `<button class="cg-prev rounded-lg border border-line px-3 py-1.5 hover:bg-surface-2 disabled:opacity-40"${page <= 1 ? ' disabled' : ''}>이전</button>` +
                    `<span class="px-2 font-medium text-ink-700">${page} / ${lastPage}</span>` +
                    `<button class="cg-next rounded-lg border border-line px-3 py-1.5 hover:bg-surface-2 disabled:opacity-40"${page >= lastPage ? ' disabled' : ''}>다음</button>` +
                    `<button class="cg-last rounded-lg border border-line px-2.5 py-1.5 hover:bg-surface-2 disabled:opacity-40"${page >= lastPage ? ' disabled' : ''}>»</button>` +
                    '</div>';
                pagerEl.querySelector('.cg-pagesize').onchange = (e) => { curSize = parseInt(e.target.value, 10) || pageSize; page = 1; load(); };
                pagerEl.querySelector('.cg-first').onclick = () => { if (page > 1) { page = 1; load(); } };
                pagerEl.querySelector('.cg-prev').onclick = () => { if (page > 1) { page -= 1; load(); } };
                pagerEl.querySelector('.cg-next').onclick = () => { if (page < lastPage) { page += 1; load(); } };
                pagerEl.querySelector('.cg-last').onclick = () => { if (page < lastPage) { page = lastPage; load(); } };
            }

            const api = {
                grid,
                refresh() { if (paged) page = 1; load(); },
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
                exportExcel() { return exportAll(); },
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

            // 행 더블클릭 → 상세(탭) 열기
            if (typeof onRowDblClick === 'function') {
                host.addEventListener('dblclick', (e) => {
                    if (e.target.closest('input,button,a,.cg-checkbox-display,.cg-col-check')) return;
                    const cell = e.target.closest('[data-row-index]');
                    if (!cell) return;
                    const ri = parseInt(cell.dataset.rowIndex, 10);
                    if (isNaN(ri)) return;
                    const row = grid.getData()[ri];
                    if (row) onRowDblClick(row, e);
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
