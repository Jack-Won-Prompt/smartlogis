@php use App\Enums\StocktakeStatus; $statuses = StocktakeStatus::options(); @endphp

<x-app-layout title="재고 실사" breadcrumb="재고 / 재고 실사">
    <x-page-header title="재고 실사" subtitle="현재고 스냅샷을 실사하고 확정하면 차이만큼 재고가 자동 조정됩니다." />

    <x-filter-bar class="mt-6">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">상태</label>
            <select id="f-status" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
    </x-filter-bar>

    <div class="mb-4 mt-4 flex items-center justify-end gap-2" x-data="stCreate()">
        <select x-model="org_id" class="rounded-lg border-line bg-surface-1 py-2 text-sm">
            <option value="">실사 대상 선택</option>@foreach($orgs as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
        </select>
        <input type="date" x-model="count_date" class="rounded-lg border-line bg-surface-1 py-2 text-sm">
        <button @click="create()" class="btn-primary !py-2 !text-sm" data-magnetic>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> 실사 생성
        </button>
    </div>

    <x-ww-grid-assets />
    <div id="st-grid"></div>

    {{-- 실사 입력 모달 --}}
    <div x-data="stCount()" @st-open.window="load($event.detail)" x-show="show" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.escape.window="show=false">
        <div class="absolute inset-0 bg-navy/40 backdrop-blur-sm" @click="show=false"></div>
        <div class="relative flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-surface-1 shadow-lift"
             x-show="show" x-transition:enter="transition ease-brand duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <div><h3 class="font-display text-lg font-bold text-ink-900" x-text="doc.stocktake_no"></h3><p class="text-xs text-ink-500" x-text="doc.org_name"></p></div>
                <button @click="show=false" class="text-ink-400 hover:text-ink-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-5">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-line text-left text-xs text-ink-500">
                        <th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2 text-right">전산</th><th class="py-2 text-right">실사</th><th class="py-2 text-right">차이</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="it in doc.items" :key="it.id">
                            <tr class="border-b border-line/60">
                                <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span></td>
                                <td class="py-2 font-mono text-xs" x-text="it.lot_no"></td>
                                <td class="py-2 text-right font-mono" x-text="Number(it.system_qty).toLocaleString()"></td>
                                <td class="py-2 text-right"><input type="number" x-model.number="it.counted_qty" @change="saveItem(it)" min="0" class="w-24 rounded border-line py-1 text-right font-mono text-xs"></td>
                                <td class="py-2 text-right font-mono font-semibold" :class="diff(it) > 0 ? 'text-ok-600' : (diff(it) < 0 ? 'text-crit-600' : 'text-ink-300')" x-text="(diff(it)>0?'+':'')+diff(it)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
                <button @click="show=false" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold text-ink-600 hover:bg-surface-2">닫기</button>
                <button @click="confirm()" :disabled="saving || doc.status==='CONFIRMED'" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">실사 확정</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let stGrid; const CSRF=()=>document.querySelector('meta[name=csrf-token]').content;
        window.addEventListener('DOMContentLoaded', () => {
            const tones={ DRAFT:'hold', COUNTING:'info', CONFIRMED:'ok' };
            stGrid = window.WWGrid.connect('#st-grid', {
                dataUrl:'{{ route('stocktakes.data') }}', readonly:true, screenName:'재고실사',
                onRowClick:(row)=>window.dispatchEvent(new CustomEvent('st-open',{detail:row.id})),
                params:()=>({ status:document.getElementById('f-status').value }),
                columns:[
                    { title:'실사번호', field:'stocktake_no', width:170 },
                    { title:'대상', field:'org_name', width:160 },
                    { title:'실사일', field:'count_date', width:120 },
                    { title:'품목', field:'items_count', editor:'number', width:80 },
                    { title:'상태', field:'status_label', width:100 },
                ],
            });
            document.getElementById('f-status').addEventListener('change',()=>stGrid.refresh());
        });

        function stCreate(){
            return {
                org_id:'', count_date: new Date().toISOString().slice(0,10),
                async create(){
                    if(!this.org_id){ window.toast('실사 대상을 선택하세요.','warn'); return; }
                    const res = await fetch('{{ route('stocktakes.store') }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),Accept:'application/json'}, body: JSON.stringify({ org_id:this.org_id, count_date:this.count_date }) });
                    const data = await res.json();
                    if(res.ok){ window.toast(`실사 ${data.stocktake_no} 생성`,'ok'); stGrid.refresh(); window.dispatchEvent(new CustomEvent('st-open',{detail:data.id})); }
                    else window.toast(data.message||'생성 실패','crit');
                },
            };
        }
        function stCount(){
            return {
                show:false, saving:false, doc:{items:[]},
                diff(it){ return (it.counted_qty===null||it.counted_qty==='')?0:(Number(it.counted_qty)-Number(it.system_qty)); },
                async load(id){ const res=await fetch(`{{ url('stocktakes') }}/${id}`,{headers:{Accept:'application/json'}}); this.doc=await res.json(); this.show=true; },
                async saveItem(it){
                    if(it.counted_qty===null||it.counted_qty==='') return;
                    await fetch(`{{ url('stocktakes') }}/${this.doc.id}/items/${it.id}`, { method:'PATCH', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),Accept:'application/json'}, body: JSON.stringify({ counted_qty: it.counted_qty }) });
                },
                async confirm(){
                    const ok = await window.confirmDialog({ title:'실사 확정', message:`${this.doc.stocktake_no} 확정 시 차이만큼 재고가 조정됩니다. 진행할까요?`, tone:'warn', confirmText:'확정' });
                    if(!ok) return; this.saving=true;
                    const res = await fetch(`{{ url('stocktakes') }}/${this.doc.id}/confirm`, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF(),Accept:'application/json'} });
                    const data = await res.json(); this.saving=false;
                    if(res.ok){ window.toast(data.message,'ok','실사 확정'); this.show=false; stGrid.refresh(); }
                    else window.toast(data.message||'확정 실패','crit');
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
