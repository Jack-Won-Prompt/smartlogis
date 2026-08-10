@php $me = auth()->user(); $canRegister = $me->isHospital() || $me->isLife(); $canReceive = $me->isHq() || $me->isWarehouse(); @endphp
<x-app-layout title="반납 처리" breadcrumb="입출고 / 반납 처리">
    <x-page-header title="반납 처리" subtitle="미사용분을 병원 → 창고로 반납합니다. 등록 → 배송 → 수령확인(창고 재고 복귀) 순으로 처리됩니다.">
        @if($canRegister)
            <x-slot:actions>
                <button onclick="openReturnModal()" class="btn-primary">+ 새 반납 등록</button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-filter-bar class="mt-6">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">상태</label>
            <select id="f-status" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>
                <option value="REQUESTED">반납 등록</option>
                <option value="SHIPPING">배송 중</option>
                <option value="RECEIVED">수령 완료</option>
                <option value="CANCELED">취소</option>
            </select>
        </div>
        @unless($me->isHospital())
            <div>
                <label class="mb-1 block text-xs font-medium text-ink-500">병원</label>
                <select id="f-hospital" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                    <option value="">전체</option>
                    @foreach($hospitals as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                </select>
            </div>
        @endunless
    </x-filter-bar>

    <x-ww-grid-assets />
    <div id="return-grid" class="mt-4"></div>

    {{-- 선택 행 액션 바 --}}
    <div id="action-bar" class="mt-3 hidden items-center gap-2 rounded-xl border border-line bg-white p-3 text-sm shadow-sm">
        <span id="sel-info" class="font-medium text-ink-700"></span>
        <span class="flex-1"></span>
        <button id="btn-ship" class="hidden rounded-lg border border-line px-3 py-1.5 font-medium text-brand-600 hover:bg-brand-50">배송 시작</button>
        @if($canReceive)<button id="btn-receive" class="hidden rounded-lg bg-brand-600 px-3 py-1.5 font-semibold text-white hover:bg-brand-700">수령확인(재고 복귀)</button>@endif
        <button id="btn-cancel" class="hidden rounded-lg border border-crit-500/40 px-3 py-1.5 font-medium text-crit-600 hover:bg-crit-100">취소</button>
    </div>

    {{-- 새 반납 등록 모달 --}}
    @if($canRegister)
    <div id="return-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-navy/40 p-4" onclick="if(event.target===this)closeReturnModal()">
        <div class="flex max-h-[88vh] w-full max-w-2xl flex-col rounded-2xl bg-white p-5 shadow-lift">
            <h3 class="mb-3 text-base font-bold text-ink-900">새 반납 등록</h3>
            @unless($me->isHospital())
                <label class="mb-1 block text-xs font-medium text-ink-500">반납 병원</label>
                <select id="m-hospital" class="mb-3 w-full rounded-lg border-line bg-surface-1 py-2 text-sm" onchange="loadStock()">
                    <option value="">병원 선택</option>
                    @foreach($hospitals as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                </select>
            @endunless
            <p class="mb-2 text-xs text-ink-400">병원 보유 재고에서 반납 수량을 입력하세요(0 은 제외).</p>
            <div class="min-h-0 flex-1 overflow-y-auto rounded-lg border border-line">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-surface-1 text-xs text-ink-500">
                        <tr><th class="p-2 text-left">제품</th><th class="p-2 text-left">Lot</th><th class="p-2 text-right">보유</th><th class="p-2 text-right">반납수량</th></tr>
                    </thead>
                    <tbody id="m-rows"><tr><td colspan="4" class="p-6 text-center text-ink-300">병원을 선택하면 재고가 표시됩니다.</td></tr></tbody>
                </table>
            </div>
            <textarea id="m-reason" rows="2" placeholder="반납 사유(선택)" class="mt-3 w-full rounded-lg border-line bg-surface-1 px-3 py-2 text-sm"></textarea>
            <div class="mt-3 flex justify-end gap-2">
                <button onclick="closeReturnModal()" class="rounded-lg px-4 py-2 text-sm text-ink-500 hover:bg-surface-2">취소</button>
                <button onclick="submitReturn()" class="btn-primary">반납 등록</button>
            </div>
        </div>
    </div>
    @endif

    @push('scripts')
    <script>
        const IS_HOSPITAL = @json($me->isHospital());
        const csrf = () => document.querySelector('meta[name=csrf-token]').content;
        let grid, selected = null;

        window.addEventListener('DOMContentLoaded', () => {
            const f = id => document.getElementById(id)?.value || '';
            grid = window.WWGrid.connect('#return-grid', {
                dataUrl: '{{ route('returns.data') }}', readonly: true, screenName: '반납',
                params: () => ({ status: f('f-status'), hospital_id: f('f-hospital') }),
                columns: [
                    { title:'반납번호', field:'return_no', width:150 },
                    { title:'병원', field:'hospital', width:160 },
                    { title:'창고', field:'warehouse', width:140 },
                    { title:'품목수', field:'items', width:80 },
                    { title:'상태', field:'status_label', width:110 },
                    { title:'등록일', field:'created_at', width:150 },
                ],
                onRowClick: (row) => selectRow(row),
            });
            document.getElementById('f-status').addEventListener('change', () => grid.refresh());
            document.getElementById('f-hospital')?.addEventListener('change', () => grid.refresh());
        });

        function selectRow(row) {
            selected = row;
            const bar = document.getElementById('action-bar');
            bar.classList.remove('hidden'); bar.classList.add('flex');
            document.getElementById('sel-info').textContent = `${row.return_no} · ${row.hospital} · ${row.status_label}`;
            const show = (id, on) => { const el = document.getElementById(id); if(el) el.classList.toggle('hidden', !on); };
            show('btn-ship', row.status === 'REQUESTED');
            show('btn-receive', row.status === 'REQUESTED' || row.status === 'SHIPPING');
            show('btn-cancel', row.status !== 'RECEIVED' && row.status !== 'CANCELED');
        }

        async function act(path) {
            if (!selected) return;
            const r = await fetch(`/returns/${selected.id}/${path}`, { method:'POST', headers:{'X-CSRF-TOKEN':csrf(),'Accept':'application/json'} });
            const d = await r.json().catch(()=>({}));
            if (r.ok) { window.toast?.('처리했습니다.', 'ok'); grid.refresh(); document.getElementById('action-bar').classList.add('hidden'); selected=null; }
            else window.toast?.(d.message || '처리에 실패했습니다.', 'crit');
        }
        document.getElementById('btn-ship')?.addEventListener('click', () => act('ship'));
        document.getElementById('btn-receive')?.addEventListener('click', () => window.confirmDialog({title:'수령확인', message:'수령확인하면 병원 재고가 차감되고 창고 재고가 복귀됩니다. 진행할까요?', confirmText:'수령확인', tone:'brand'}).then(ok=>{ if(ok) act('receive'); }));
        document.getElementById('btn-cancel')?.addEventListener('click', () => window.confirmDialog({title:'반납 취소', message:'이 반납을 취소할까요?', confirmText:'취소', tone:'crit'}).then(ok=>{ if(ok) act('cancel'); }));

        // ── 등록 모달 ──
        function openReturnModal(){ const m=document.getElementById('return-modal'); m.classList.remove('hidden'); m.classList.add('flex'); if(IS_HOSPITAL) loadStock(); }
        function closeReturnModal(){ const m=document.getElementById('return-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
        window.openReturnModal = openReturnModal; window.closeReturnModal = closeReturnModal;

        async function loadStock(){
            const orgId = IS_HOSPITAL ? '' : (document.getElementById('m-hospital')?.value || '');
            if (!IS_HOSPITAL && !orgId) return;
            const url = new URL('{{ route('inventory.status.data') }}', location.origin);
            url.searchParams.set('size','200'); if(orgId) url.searchParams.set('org_id', orgId);
            const r = await fetch(url, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
            const d = await r.json();
            const rows = (d.data||[]).filter(x => x.qty > 0);
            const tb = document.getElementById('m-rows');
            tb.innerHTML = rows.length ? rows.map(x => {
                const [org, pid, lid] = x.id.split(':');
                return `<tr class="border-t border-line/60" data-pid="${pid}" data-lid="${lid}">
                    <td class="p-2">${x.product_name}</td><td class="p-2">${x.lot_no}</td>
                    <td class="p-2 text-right">${x.qty}</td>
                    <td class="p-2 text-right"><input type="number" min="0" max="${x.qty}" value="0" class="w-20 rounded border-line py-1 text-right text-sm r-qty"></td></tr>`;
            }).join('') : '<tr><td colspan="4" class="p-6 text-center text-ink-300">보유 재고가 없습니다.</td></tr>';
        }
        window.loadStock = loadStock;

        async function submitReturn(){
            const items = [...document.querySelectorAll('#m-rows tr[data-pid]')].map(tr => ({
                product_id: +tr.dataset.pid, lot_id: +tr.dataset.lid, qty: +tr.querySelector('.r-qty').value || 0,
            })).filter(i => i.qty > 0);
            if (!items.length) { window.toast?.('반납 수량을 입력하세요.', 'warn'); return; }
            const body = { items, reason: document.getElementById('m-reason').value };
            if (!IS_HOSPITAL) body.hospital_id = +document.getElementById('m-hospital').value || null;
            const r = await fetch('{{ route('returns.store') }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf(),'Accept':'application/json'}, body: JSON.stringify(body) });
            const d = await r.json().catch(()=>({}));
            if (r.ok) { window.toast?.(`반납 등록됨: ${d.return_no}`, 'ok'); closeReturnModal(); grid.refresh(); }
            else window.toast?.(d.message || '등록에 실패했습니다.', 'crit');
        }
        window.submitReturn = submitReturn;
    </script>
    @endpush
</x-app-layout>
