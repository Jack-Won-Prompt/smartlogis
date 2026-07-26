/*
 * AutoSelect — 네이티브 <select> 를 검색형(자동완성) 콤보로 확장하는 경량 바닐라 컴포넌트.
 * 외부 라이브러리 없음. 원본 <select> 는 그대로 유지(값/change 이벤트)해서 기존 로직·Alpine x-model 과 호환.
 *   - 필터 바 select(id + change 리스너) : choose() 가 change 를 디스패치 → 기존 grid.refresh() 동작
 *   - 모달 select(x-model)              : choose() 가 change 를 디스패치 → Alpine 상태 갱신
 *   - 모달이 다시 열릴 때(Alpine 이 값을 프로그램적으로 리셋) IntersectionObserver 로 표시 재동기화
 * 그리드(wwGrid) 편집 콤보는 셀 편집 시 동적 생성되므로 여기(DOMContentLoaded)서 건드리지 않는다.
 */
(function (global) {
    'use strict';

    function injectStyle() {
        if (document.getElementById('as-style')) return;
        const s = document.createElement('style');
        s.id = 'as-style';
        s.textContent = `
        .as-wrap{position:relative}
        .as-wrap>select{display:none!important}
        .as-input{cursor:text;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7a88' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='m6 9 6 6 6-6'/></svg>");background-repeat:no-repeat;background-position:right 8px center;padding-right:30px!important}
        .as-list{position:absolute;z-index:60;left:0;right:0;top:calc(100% + 4px);max-height:260px;overflow-y:auto;background:#fff;border:1px solid #e4e8ed;border-radius:10px;box-shadow:0 12px 28px -8px rgba(16,27,38,.18);padding:4px;display:none}
        .as-wrap.as-open .as-list{display:block}
        .as-opt{padding:7px 10px;border-radius:7px;font-size:13px;color:#101b26;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .as-opt:hover,.as-opt.as-active{background:#eef3fe;color:#2551c4}
        .as-empty{padding:9px 10px;font-size:12px;color:#93a4b6}
        `;
        document.head.appendChild(s);
    }

    function escapeHtml(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    function enhance(sel) {
        if (sel.__asDone || sel.disabled) return;
        sel.__asDone = true;
        injectStyle();

        const wrap = document.createElement('div');
        wrap.className = 'as-wrap';
        // 원본 select 의 폭/정렬 클래스를 래퍼에 계승(레이아웃 유지)
        if (/\bw-full\b/.test(sel.className)) wrap.classList.add('w-full');
        if (/\bflex-1\b/.test(sel.className)) wrap.classList.add('flex-1');
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);

        const input = document.createElement('input');
        input.type = 'text';
        input.className = sel.className + ' as-input';
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('role', 'combobox');
        input.placeholder = sel.getAttribute('data-placeholder') || '검색·선택';
        wrap.appendChild(input);

        const list = document.createElement('div');
        list.className = 'as-list';
        wrap.appendChild(list);

        const opts = () => Array.from(sel.options).map((o) => ({ value: o.value, label: o.textContent.trim() }));
        const currentLabel = () => { const o = sel.options[sel.selectedIndex]; return o ? o.textContent.trim() : ''; };
        const syncFromSelect = () => { input.value = currentLabel(); };

        let open = false, active = -1, filtered = [];

        function renderList(q) {
            const ql = (q || '').toLowerCase();
            filtered = opts().filter((o) => o.label.toLowerCase().includes(ql));
            if (active >= filtered.length) active = filtered.length - 1;
            list.innerHTML = filtered.length
                ? filtered.map((o, i) => `<div class="as-opt${i === active ? ' as-active' : ''}" data-i="${i}">${escapeHtml(o.label)}</div>`).join('')
                : '<div class="as-empty">결과 없음</div>';
        }
        function openList(q) { open = true; wrap.classList.add('as-open'); renderList(q); }
        function closeList() { open = false; wrap.classList.remove('as-open'); }
        function choose(i) {
            const o = filtered[i]; if (!o) return;
            sel.value = o.value;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            sel.dispatchEvent(new Event('input', { bubbles: true }));
            syncFromSelect();
            closeList();
        }

        input.addEventListener('focus', () => { syncFromSelect(); input.select(); active = -1; openList(''); });
        input.addEventListener('input', () => { active = 0; openList(input.value); });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (!open) openList(input.value); active = Math.min(active + 1, filtered.length - 1); renderList(input.value); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); renderList(input.value); }
            else if (e.key === 'Enter') { e.preventDefault(); if (open) choose(active >= 0 ? active : (filtered.length === 1 ? 0 : -1)); }
            else if (e.key === 'Escape') { closeList(); syncFromSelect(); }
        });
        list.addEventListener('mousedown', (e) => { const el = e.target.closest('.as-opt'); if (el) { e.preventDefault(); choose(parseInt(el.dataset.i, 10)); } });
        document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) { closeList(); syncFromSelect(); } });

        // 외부(다른 코드/Alpine)에서 select.value 를 바꾸고 change 를 쏘면 표시 동기화
        sel.addEventListener('change', syncFromSelect);
        // 모달이 다시 표시될 때(Alpine 이 값을 프로그램적으로 리셋) 재동기화
        if ('IntersectionObserver' in global) {
            new global.IntersectionObserver((ents) => { ents.forEach((en) => { if (en.isIntersecting) syncFromSelect(); }); }).observe(wrap);
        }

        syncFromSelect();
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('select:not([data-no-autocomplete])').forEach(enhance);
    }

    global.AutoSelect = { enhance, enhanceAll };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => enhanceAll());
    else enhanceAll();
})(window);
