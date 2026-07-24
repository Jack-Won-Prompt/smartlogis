@php
    use App\Models\Organization; use App\Models\Product; use App\Enums\OrgType; use App\Enums\OutboundStatus;
    $warehouses = Organization::where('org_type', OrgType::WAREHOUSE)->orderBy('name')->get(['id','name']);
    $hospitals = Organization::where('org_type', OrgType::HOSPITAL)->orderBy('name')->get(['id','name']);
    $products = Product::where('is_active', true)->orderBy('product_name')->get(['id','product_name','product_code']);
    $statuses = OutboundStatus::options();
@endphp

<x-app-layout title="출고 지시" breadcrumb="입출고 / 출고 지시">
    <x-page-header title="출고 지시" subtitle="출고 지시 → FEFO 피킹 → 배송 → 완료까지 한 화면에서 처리합니다." />

    <x-filter-bar class="mt-6">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="출고번호" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">상태</label>
            <select id="f-status" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
    </x-filter-bar>

    <div class="mb-4 mt-4 flex items-center justify-end" x-data>
        <button @click="$dispatch('ob-open')" class="btn-primary !py-2 !text-sm" data-magnetic>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> 출고 지시
        </button>
    </div>

    <div id="ob-grid"></div>

    {{-- 생성 슬라이드오버 --}}
    <div x-data="obForm()" @ob-open.window="open()" x-show="show" x-cloak class="fixed inset-0 z-[90]" @keydown.escape.window="show=false">
        <div class="absolute inset-0 bg-navy/40 backdrop-blur-sm" @click="show=false"></div>
        <div class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-surface-1 shadow-lift"
             x-show="show" x-transition:enter="transition ease-brand duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="font-display text-lg font-bold text-ink-900">출고 지시</h3>
                <button @click="show=false" class="text-ink-400 hover:text-ink-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink-500">출고 창고 *</label>
                        <select x-model="warehouse_id" class="w-full rounded-lg border-line py-2 text-sm">
                            <option value="">선택</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink-500">병원 *</label>
                        <select x-model="hospital_id" class="w-full rounded-lg border-line py-2 text-sm">
                            <option value="">선택</option>@foreach($hospitals as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink-500">출고예정일 *</label>
                        <input type="date" x-model="planned_date" class="w-full rounded-lg border-line py-2 text-sm">
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <select x-model="pick" class="flex-1 rounded-lg border-line py-2 text-sm">
                        <option value="">제품 선택</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->product_name }} ({{ $p->product_code }})</option>@endforeach
                    </select>
                    <button @click="add()" class="rounded-lg border border-line px-3 py-2 text-sm font-medium text-ink-600 hover:bg-surface-2">추가</button>
                </div>
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2">제품</th><th class="py-2 text-right">수량</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(it,i) in items" :key="i">
                            <tr class="border-b border-line/60">
                                <td class="py-1.5" x-text="it.product_name"></td>
                                <td class="py-1.5 text-right"><input type="number" x-model.number="it.qty" min="1" class="w-20 rounded border-line py-1 text-right font-mono text-xs"></td>
                                <td class="py-1.5 text-right"><button @click="items.splice(i,1)" class="text-xs text-crit-600 hover:underline">삭제</button></td>
                            </tr>
                        </template>
                        <template x-if="items.length===0"><tr><td colspan="3" class="py-6 text-center text-xs text-ink-400">제품을 추가하세요. 출고 확정 시 FEFO 로 Lot 이 자동 배정됩니다.</td></tr></template>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
                <button @click="show=false" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold text-ink-600 hover:bg-surface-2">취소</button>
                <button @click="submit()" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">지시 등록</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let obGrid;
        const CSRF = () => document.querySelector('meta[name=csrf-token]').content;
        window.addEventListener('DOMContentLoaded', () => {
            const tones = { DRAFT:'hold', APPROVED:'ok', PICKING:'info', SHIPPED:'info', DELIVERED:'ok', CANCELED:'hold' };
            function f(id){ return document.getElementById(id).value; }
            obGrid = window.SmartGrid.create('#ob-grid', {
                dataUrl: '{{ route('outbounds.data') }}', readonly:true,
                params: () => ({ keyword:f('f-keyword'), status:f('f-status') }),
                columns: [
                    { title:'출고번호', field:'outbound_no', width:170, formatter: window.SmartGrid.mono },
                    { title:'창고', field:'warehouse_name', minWidth:130 },
                    { title:'병원', field:'hospital_name', minWidth:130 },
                    { title:'구분', field:'source_label', width:90 },
                    { title:'품목', field:'items_count', width:70, hozAlign:'right', formatter:(c)=>`<span class="sg-mono">${c.getValue()}</span>` },
                    { title:'상태', field:'status', width:100, formatter:(c)=>`<span class="sg-badge sg-${tones[c.getValue()]||'hold'}">${c.getData().status_label}</span>` },
                    { title:'처리', field:'_act', width:110, headerSort:false, hozAlign:'right', formatter: actionBtn,
                      cellClick:(e,cell)=>{ const b=e.target.closest('[data-act]'); if(b) advance(cell.getData().id, b.dataset.act); } },
                ],
            });
            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>obGrid.refresh(),350);});
            document.getElementById('f-status').addEventListener('change',()=>obGrid.refresh());
        });

        function actionBtn(cell){
            const s = cell.getValue();
            const map = { APPROVED:['pick','피킹'], PICKING:['ship','배송'], SHIPPED:['deliver','완료'] };
            const a = map[s];
            if(!a) return '<span style="color:#93a4b6;font-size:12px">—</span>';
            return `<span data-act="${a[0]}" class="sg-act" style="width:auto;padding:3px 12px;color:#fff;background:#2551c4;border-radius:8px;font-size:12px;font-weight:600">${a[1]} ▸</span>`;
        }
        async function advance(id, act){
            const labels = { pick:'FEFO 피킹', ship:'배송 시작', deliver:'배송 완료' };
            const ok = await window.confirmDialog({ title: labels[act], message:`${labels[act]} 처리할까요?`, tone:'brand', confirmText:'확인' });
            if(!ok) return;
            const res = await fetch(`{{ url('outbounds') }}/${id}/${act}`, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF(),Accept:'application/json'} });
            const data = await res.json();
            window.toast(data.message || (res.ok?'완료':'실패'), res.ok?'ok':'crit');
            obGrid.refresh();
        }

        function obForm(){
            return {
                show:false, saving:false, warehouse_id:'', hospital_id:'', planned_date:'', pick:'', items:[],
                products: @json($products),
                open(){ this.show=true; this.warehouse_id=''; this.hospital_id=''; this.planned_date=new Date().toISOString().slice(0,10); this.items=[]; },
                add(){ if(!this.pick) return; const p=this.products.find(x=>x.id==this.pick); this.items.push({product_id:p.id, product_name:p.product_name, qty:1}); this.pick=''; },
                async submit(){
                    if(!this.warehouse_id||!this.hospital_id||!this.planned_date){ window.toast('창고·병원·예정일을 입력하세요.','warn'); return; }
                    if(this.items.length===0){ window.toast('제품을 추가하세요.','warn'); return; }
                    this.saving=true;
                    const res = await fetch('{{ route('outbounds.store') }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),Accept:'application/json'}, body: JSON.stringify({ warehouse_id:this.warehouse_id, hospital_id:this.hospital_id, planned_date:this.planned_date, items:this.items }) });
                    const data = await res.json(); this.saving=false;
                    if(res.ok){ window.toast(`출고 ${data.outbound_no} 등록`,'ok'); this.show=false; obGrid.refresh(); }
                    else window.toast(data.message||(data.errors&&Object.values(data.errors)[0][0])||'등록 실패','crit');
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
