@php
    use App\Models\Organization; use App\Models\Product; use App\Enums\OrgType; use App\Enums\InboundStatus;
    $suppliers = Organization::where('org_type', OrgType::SUPPLIER)->orderBy('name')->get(['id','name']);
    $warehouses = Organization::where('org_type', OrgType::WAREHOUSE)->orderBy('name')->get(['id','name']);
    $products = Product::where('is_active', true)->orderBy('product_name')->get(['id','product_name','product_code','gtin']);
    $statuses = InboundStatus::options();
@endphp

<x-app-layout title="입고 예정(ASN)" breadcrumb="입출고 / 입고 예정(ASN)">
    <x-page-header title="입고 예정(ASN)" subtitle="공급사→창고 입고 예정을 등록합니다. 스캔으로 품목을 빠르게 추가하세요." />

    <x-filter-bar class="mt-6">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="입고번호" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">상태</label>
            <select id="f-status" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
    </x-filter-bar>

    <div class="mb-4 mt-4 flex items-center justify-end" x-data>
        <button @click="$dispatch('asn-open')" class="btn-primary !py-2 !text-sm" data-magnetic>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> ASN 등록
        </button>
    </div>

    <div id="asn-grid"></div>

    {{-- 등록 슬라이드오버 --}}
    <div x-data="asnForm()" @asn-open.window="open()" x-show="show" x-cloak class="fixed inset-0 z-[90]" @keydown.escape.window="show=false">
        <div class="absolute inset-0 bg-navy/40 backdrop-blur-sm" @click="show=false"></div>
        <div class="absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-surface-1 shadow-lift"
             x-show="show" x-transition:enter="transition ease-brand duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="font-display text-lg font-bold text-ink-900">ASN 등록</h3>
                <button @click="show=false" class="text-ink-400 hover:text-ink-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink-500">공급사 *</label>
                        <select x-model="from_org_id" class="w-full rounded-lg border-line py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                            <option value="">선택</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink-500">입고 창고 *</label>
                        <select x-model="to_org_id" class="w-full rounded-lg border-line py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                            <option value="">선택</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-ink-500">입고예정일 *</label>
                        <input type="date" x-model="planned_date" class="w-full rounded-lg border-line py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                    </div>
                </div>

                {{-- 스캔으로 품목 추가 --}}
                <div class="rounded-xl border border-line bg-surface-0 p-4">
                    <p class="mb-2 text-xs font-semibold text-ink-500">바코드 스캔 또는 수기 추가</p>
                    <x-scan-input @scan:matched="addFromScan($event.detail)" @scan:unmatched="notFound($event.detail)" />
                    <div class="mt-2 flex items-center gap-2">
                        <select x-model="manualProduct" class="flex-1 rounded-lg border-line py-1.5 text-sm">
                            <option value="">수기 선택</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->product_name }} ({{ $p->product_code }})</option>@endforeach
                        </select>
                        <button @click="addManual()" class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink-600 hover:bg-surface-2">추가</button>
                    </div>
                </div>

                {{-- 품목 목록 --}}
                <div>
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-line text-left text-xs text-ink-500">
                            <th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2">유통기한</th><th class="py-2 text-right">수량</th><th></th>
                        </tr></thead>
                        <tbody>
                            <template x-for="(it, i) in items" :key="i">
                                <tr class="border-b border-line/60">
                                    <td class="py-1.5 pr-2" x-text="it.product_name"></td>
                                    <td class="py-1.5 pr-2"><input x-model="it.lot_no" class="w-24 rounded border-line py-1 font-mono text-xs"></td>
                                    <td class="py-1.5 pr-2"><input type="date" x-model="it.expiry_date" class="rounded border-line py-1 text-xs"></td>
                                    <td class="py-1.5 text-right"><input type="number" x-model.number="it.qty" min="1" class="w-20 rounded border-line py-1 text-right font-mono text-xs"></td>
                                    <td class="py-1.5 text-right"><button @click="items.splice(i,1)" class="text-crit-600 hover:underline text-xs">삭제</button></td>
                                </tr>
                            </template>
                            <template x-if="items.length===0"><tr><td colspan="5" class="py-6 text-center text-xs text-ink-400">스캔하거나 수기로 품목을 추가하세요.</td></tr></template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
                <button @click="show=false" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold text-ink-600 hover:bg-surface-2">취소</button>
                <button @click="submit()" :disabled="saving" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">등록</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let asnGrid;
        window.addEventListener('DOMContentLoaded', () => {
            const statusTones = { PLANNED:'hold', RECEIVING:'info', CONFIRMED:'ok', CANCELED:'hold' };
            function f(id){ return document.getElementById(id).value; }
            asnGrid = window.SmartGrid.create('#asn-grid', {
                dataUrl: '{{ route('inbounds.data') }}', readonly:true,
                params: () => ({ keyword:f('f-keyword'), status:f('f-status') }),
                columns: [
                    { title:'입고번호', field:'inbound_no', width:170, formatter: window.SmartGrid.mono },
                    { title:'공급사', field:'from_name', minWidth:140 },
                    { title:'입고창고', field:'to_name', minWidth:140 },
                    { title:'예정일', field:'planned_date', width:120, formatter:(c)=>`<span class="sg-mono">${c.getValue()||''}</span>` },
                    { title:'품목', field:'items_count', width:80, hozAlign:'right', formatter:(c)=>`<span class="sg-mono">${c.getValue()}</span>` },
                    { title:'상태', field:'status', width:110, formatter:(c)=>{const v=c.getValue();return `<span class="sg-badge sg-${statusTones[v]||'hold'}">${c.getData().status_label}</span>`;} },
                ],
            });
            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>asnGrid.refresh(),350);});
            document.getElementById('f-status').addEventListener('change',()=>asnGrid.refresh());
        });

        function asnForm(){
            return {
                show:false, saving:false, from_org_id:'', to_org_id:'', planned_date:'', manualProduct:'', items:[],
                products: @json($products),
                open(){ this.show=true; this.from_org_id=''; this.to_org_id=''; this.planned_date=new Date().toISOString().slice(0,10); this.items=[]; },
                addFromScan(d){ this.items.push({ product_id:d.product.id, product_name:d.product.product_name, lot_no:d.parsed.lot_no||'', expiry_date:d.parsed.expiry_date||'', qty:1 }); },
                notFound(d){ window.toast(d.message,'warn'); },
                addManual(){ if(!this.manualProduct) return; const p=this.products.find(x=>x.id==this.manualProduct); this.items.push({ product_id:p.id, product_name:p.product_name, lot_no:'', expiry_date:'', qty:1 }); this.manualProduct=''; },
                async submit(){
                    if(!this.from_org_id||!this.to_org_id||!this.planned_date){ window.toast('공급사·창고·예정일을 입력하세요.','warn'); return; }
                    if(this.items.length===0){ window.toast('품목을 추가하세요.','warn'); return; }
                    this.saving=true;
                    const res = await fetch('{{ route('inbounds.store') }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,Accept:'application/json'}, body: JSON.stringify({ from_org_id:this.from_org_id, to_org_id:this.to_org_id, planned_date:this.planned_date, items:this.items }) });
                    const data = await res.json();
                    this.saving=false;
                    if(res.ok){ window.toast(`ASN ${data.inbound_no} 등록 완료`,'ok'); this.show=false; asnGrid.refresh(); }
                    else { window.toast(data.message || (data.errors && Object.values(data.errors)[0][0]) || '등록 실패','crit'); }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
