@php $me = auth()->user(); $canRegister = $me->isHospital() || $me->isLife(); $canReceive = $me->isHq() || $me->isWarehouse(); $canCancel = $me->isHq() || $me->isHospital() || $me->isLife(); @endphp
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
    <x-list-detail :url-base="url('returns')" items-label="품목">
        <x-slot:list><div id="return-grid"></div></x-slot:list>
        <x-slot:info>
            <div class="flex justify-between"><span class="text-ink-400">병원</span><span class="font-medium text-ink-800" x-text="doc.hospital"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">창고</span><span x-text="doc.warehouse"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">등록일</span><span x-text="doc.created_at || '—'"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">배송 시작</span><span x-text="doc.shipped_at || '—'"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">수령 확인</span><span x-text="doc.received_at || '—'"></span></div>
            <template x-if="doc.reason"><div class="rounded-lg bg-surface-2 px-3 py-2 text-xs text-ink-600"><b>사유</b> <span x-text="doc.reason"></span></div></template>
        </x-slot:info>
        <x-slot:items>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2">유통기한</th><th class="py-2 text-right">수량</th></tr></thead>
                <tbody>
                    <template x-for="(it,i) in (doc.items||[])" :key="i">
                        <tr class="border-b border-line/60">
                            <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span> <span class="font-mono text-[11px] text-ink-300" x-text="it.product_code"></span></td>
                            <td class="py-2 font-mono text-xs" x-text="it.lot_no"></td>
                            <td class="py-2 font-mono text-xs" x-text="it.expiry_date || '—'"></td>
                            <td class="py-2 text-right font-mono font-semibold" x-text="Number(it.qty).toLocaleString()"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </x-slot:items>
        <x-slot:actions>
            <button type="button" @click="window.open('{{ url('returns') }}/'+doc.id+'/order','_blank')" class="rounded-xl border border-line px-4 py-2 text-sm font-semibold text-ink-700 hover:bg-surface-2">📄 반품지시서</button>
            <template x-if="doc.status==='REQUESTED'">
                <button @click="act('{{ url('returns') }}/'+doc.id+'/ship')" :disabled="saving" class="rounded-xl border border-line px-4 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-50">배송 시작</button>
            </template>
            @if($canReceive)
            <template x-if="doc.status==='REQUESTED' || doc.status==='SHIPPING'">
                <button @click="act('{{ url('returns') }}/'+doc.id+'/receive',{confirm:{title:'수령확인',message:'수령확인하면 병원 재고가 차감되고 창고 재고가 복귀됩니다. 진행할까요?',confirmText:'수령확인',tone:'brand'}})" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">수령확인(재고 복귀)</button>
            </template>
            @endif
            @if($canCancel)
            <template x-if="doc.status!=='RECEIVED' && doc.status!=='CANCELED'">
                <button @click="act('{{ url('returns') }}/'+doc.id+'/cancel',{confirm:{title:'반납 취소',message:'이 반납을 취소할까요?',confirmText:'취소',tone:'crit'}})" :disabled="saving" class="rounded-xl border border-crit-500/40 px-4 py-2 text-sm font-semibold text-crit-600 hover:bg-crit-100">취소</button>
            </template>
            @endif
            <template x-if="doc.status==='RECEIVED' || doc.status==='CANCELED'"><span class="text-xs text-ink-400">완료/취소된 반납입니다.</span></template>
        </x-slot:actions>
    </x-list-detail>

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
        let grid;

        window.addEventListener('DOMContentLoaded', () => {
            const f = id => document.getElementById(id)?.value || '';
            grid = window.WWGrid.connect('#return-grid', {
                dataUrl: '{{ route('returns.data') }}', readonly: true, screenName: '반납',
                onRowDblClick: (row) => window.dispatchEvent(new CustomEvent('detail-open', { detail: row.id })),
                params: () => ({ status: f('f-status'), hospital_id: f('f-hospital') }),
                columns: [
                    { title:'반납번호', field:'return_no', width:150 },
                    { title:'병원', field:'hospital', width:160 },
                    { title:'창고', field:'warehouse', width:140 },
                    { title:'품목수', field:'items', width:80 },
                    { title:'상태', field:'status_label', width:110 },
                    { title:'등록일', field:'created_at', width:150 },
                ],
            });
            document.getElementById('f-status').addEventListener('change', () => grid.refresh());
            document.getElementById('f-hospital')?.addEventListener('change', () => grid.refresh());
        });

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
