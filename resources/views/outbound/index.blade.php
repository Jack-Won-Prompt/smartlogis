@php
    use App\Models\Organization; use App\Models\Product; use App\Enums\OrgType; use App\Enums\OutboundStatus;
    $mode = $mode ?? 'order';
    $cfg = [
        'order'    => ['title' => '출고 지시', 'sub' => '창고→병원 출고를 지시하고, 행을 더블클릭해 상세에서 관리합니다.'],
        'picking'  => ['title' => '피킹/출고', 'sub' => '승인된 출고를 더블클릭해 상세에서 품목을 선택 → FEFO 피킹 → 배송 시작합니다.'],
        'delivery' => ['title' => '배송 현황', 'sub' => '배송 중/완료 출고를 확인하고 상세에서 배송 완료를 처리합니다.'],
    ][$mode];
    $warehouses = Organization::where('org_type', OrgType::WAREHOUSE)->orderBy('name')->get(['id','name']);
    $hospitals = Organization::where('org_type', OrgType::HOSPITAL)->orderBy('name')->get(['id','name']);
    $products = Product::where('is_active', true)->orderBy('product_name')->get(['id','product_name','product_code']);
    $statuses = OutboundStatus::options();
@endphp

<x-app-layout :title="$cfg['title']" :breadcrumb="'입출고 / '.$cfg['title']">
    <x-page-header :title="$cfg['title']" :subtitle="$cfg['sub']" />

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
        <x-hospital-filter :roles="['HQ','WAREHOUSE']" />
    </x-filter-bar>

    <div id="ob-actions" class="mb-4 mt-4 flex items-center justify-end gap-2" x-data>
        @if($mode === 'order')
        <button @click="$dispatch('ob-open')" class="btn-primary !py-2 !text-sm" data-magnetic>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> 출고 지시
        </button>
        @endif
    </div>

    <x-ww-grid-assets />
    <x-list-detail :url-base="url('outbounds')" items-label="품목">
        <x-slot:list><div id="ob-grid"></div></x-slot:list>
        <x-slot:info>
            <div class="flex justify-between"><span class="text-ink-400">창고</span><span class="font-medium text-ink-800" x-text="doc.warehouse_name"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">병원</span><span x-text="doc.hospital_name"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">구분</span><span x-text="doc.source_label"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">출고예정일</span><span x-text="doc.planned_date || '—'"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">배송 시작</span><span x-text="doc.shipped_at || '—'"></span></div>
            <div class="flex justify-between"><span class="text-ink-400">배송 완료</span><span x-text="doc.delivered_at || '—'"></span></div>
        </x-slot:info>
        <x-slot:items>
            @if($mode === 'picking')
                <div class="mb-2 flex items-center justify-between text-xs text-ink-400">
                    <span>피킹할 품목을 선택하세요(미배정 기본 선택).</span>
                    <button type="button" @click="selItems = (doc.items||[]).filter(it=>!it.lot_assigned).map(it=>it.id)" class="font-semibold text-brand-600 hover:underline">미피킹 전체선택</button>
                </div>
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2 w-8"></th><th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2 text-right">수량</th></tr></thead>
                    <tbody>
                        <template x-for="(it,i) in (doc.items||[])" :key="it.id ?? i">
                            <tr class="border-b border-line/60">
                                <td class="py-2"><input type="checkbox" :checked="isSel(it.id)" @change="toggleItem(it.id)" :disabled="it.lot_assigned" class="rounded border-line text-brand-600 focus:ring-brand-400"></td>
                                <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span> <span class="font-mono text-[11px] text-ink-300" x-text="it.product_code"></span></td>
                                <td class="py-2 font-mono text-xs"><span x-show="it.lot_assigned" x-text="it.lot_no"></span><span x-show="!it.lot_assigned" class="text-ink-300">미배정</span></td>
                                <td class="py-2 text-right font-mono font-semibold" x-text="Number(it.qty).toLocaleString()"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            @else
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2 text-right">수량</th></tr></thead>
                    <tbody>
                        <template x-for="(it,i) in (doc.items||[])" :key="i">
                            <tr class="border-b border-line/60">
                                <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span> <span class="font-mono text-[11px] text-ink-300" x-text="it.product_code"></span></td>
                                <td class="py-2 font-mono text-xs" x-text="it.lot_no || '미배정'"></td>
                                <td class="py-2 text-right font-mono font-semibold" x-text="Number(it.qty).toLocaleString()"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            @endif
        </x-slot:items>
        <x-slot:actions>
            <button type="button" @click="window.open('{{ url('outbounds') }}/'+doc.id+'/labels','_blank')" class="rounded-xl border border-line px-4 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-50">🏷 라벨</button>
            @if($mode === 'picking')
                <template x-if="(doc.items||[]).some(it=>!it.lot_assigned)">
                    <button @click="selItems.length ? act('{{ url('outbounds') }}/'+doc.id+'/pick',{body:{item_ids:selItems},closeAfter:false,confirm:{title:'FEFO 피킹',message:'선택한 품목을 FEFO(유통기한 임박 순)로 피킹하고 창고 재고를 차감합니다.',confirmText:'피킹',tone:'brand'}}) : window.toast('피킹할 품목을 선택하세요.','warn')" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">선택 피킹</button>
                </template>
                <template x-if="doc.status==='PICKING' && !(doc.items||[]).some(it=>!it.lot_assigned)">
                    <button @click="act('{{ url('outbounds') }}/'+doc.id+'/ship',{confirm:{title:'배송 시작',message:doc.outbound_no+' 배송을 시작할까요?',confirmText:'배송 시작',tone:'brand'}})" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">배송 시작</button>
                </template>
            @elseif($mode === 'delivery')
                <template x-if="doc.status==='SHIPPED'">
                    <button @click="act('{{ url('outbounds') }}/'+doc.id+'/deliver',{confirm:{title:'배송 완료',message:doc.outbound_no+' 배송을 완료하면 병원 재고에 반영됩니다.',confirmText:'배송 완료',tone:'brand'}})" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">배송 완료</button>
                </template>
                <template x-if="doc.status==='DELIVERED'"><span class="text-xs text-ink-400">배송 완료된 출고입니다.</span></template>
            @endif
        </x-slot:actions>
    </x-list-detail>

    @if($mode === 'order')
    {{-- 생성 슬라이드오버 --}}
    <div x-data="obForm()" @ob-open.window="open()" x-show="show" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.escape.window="show=false">
        <div class="absolute inset-0 bg-navy/40 backdrop-blur-sm" @click="show=false"></div>
        <div class="relative flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-surface-1 shadow-lift"
             x-show="show" x-transition:enter="transition ease-brand duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
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
    @endif

    @push('scripts')
    <script>
        const CSRF = () => document.querySelector('meta[name=csrf-token]').content;
        window.addEventListener('DOMContentLoaded', () => {
            function f(id){ return document.getElementById(id).value; }
            const grid = window.WWGrid.connect('#ob-grid', {
                dataUrl: '{{ route('outbounds.data') }}', readonly:true, screenName:'{{ $cfg['title'] }}', exportInto:'#ob-actions',
                onRowDblClick:(row)=>window.dispatchEvent(new CustomEvent('detail-open',{detail:row.id})),
                params: () => ({ keyword:f('f-keyword'), status:f('f-status'), hospital_id:(document.getElementById('f-hospital')||{}).value, mode:'{{ $mode }}' }),
                columns: [
                    { title:'출고번호', field:'outbound_no', width:170 },
                    { title:'창고', field:'warehouse_name', width:140 },
                    { title:'병원', field:'hospital_name', width:140 },
                    { title:'구분', field:'source_label', width:90 },
                    { title:'품목', field:'items_count', editor:'number', width:80 },
                    { title:'상태', field:'status_label', width:100 },
                ],
            });
            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            document.getElementById('f-status').addEventListener('change',()=>grid.refresh());
            document.getElementById('f-hospital')?.addEventListener('change',()=>grid.refresh());
        });

        @if($mode === 'order')
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
                    if(res.ok){ window.toast(`출고 ${data.outbound_no} 등록`,'ok'); this.show=false; window.__smartGridRefresh?.(); }
                    else window.toast(data.message||(data.errors&&Object.values(data.errors)[0][0])||'등록 실패','crit');
                },
            };
        }
        @endif
    </script>
    @endpush
</x-app-layout>
