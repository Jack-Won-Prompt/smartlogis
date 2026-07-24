@php
    use App\Models\StockBalance;
    // 병원 자기 재고(OrgLocationScope 자동 필터) 중 재고 있는 Lot
    $stock = StockBalance::with(['product','lot'])->where('qty','>',0)->get()
        ->map(fn($b)=>[
            'product_id'=>$b->product_id, 'product_name'=>$b->product->product_name, 'product_code'=>$b->product->product_code,
            'lot_id'=>$b->lot_id, 'lot_no'=>$b->lot->lot_no, 'qty'=>$b->qty,
            'unit'=>(float)$b->product->sales_price,
            'expiry'=>$b->lot->expiry_date?->toDateString(),
        ])->values();
@endphp

<x-app-layout title="사용분 등록" breadcrumb="사용분 / 사용분 등록">
    <x-page-header title="사용분 등록" subtitle="사용한 품목을 스캔하거나 재고에서 선택해 본사로 전송합니다." />

    <div class="mt-6 max-w-3xl" x-data="usageCreate()">
        <div class="rounded-2xl border border-line bg-surface-1 p-6">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-ink-500">사용일 *</label>
                    <input type="date" x-model="usage_date" class="rounded-lg border-line py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                </div>
                <div class="min-w-[240px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-ink-500">바코드 스캔</label>
                    <x-scan-input @scan:matched="addFromScan($event.detail)" @scan:unmatched="window.toast($event.detail.message,'warn')" />
                </div>
            </div>

            <div class="mt-4 flex items-end gap-2 border-t border-line pt-4">
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-medium text-ink-500">재고에서 선택</label>
                    <select x-model="pick" class="w-full rounded-lg border-line py-2 text-sm">
                        <option value="">품목·Lot 선택 (현재고)</option>
                        <template x-for="(s,i) in stock" :key="i">
                            <option :value="i" x-text="`${s.product_name} · Lot ${s.lot_no} (재고 ${s.qty})`"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-ink-500">부서</label>
                    <input type="text" x-model="dept" placeholder="예: 정형외과" class="w-36 rounded-lg border-line py-2 text-sm">
                </div>
                <button @click="addManual()" class="rounded-lg border border-line px-3 py-2 text-sm font-medium text-ink-600 hover:bg-surface-2">추가</button>
            </div>
        </div>

        {{-- 품목 --}}
        <div class="mt-4 rounded-2xl border border-line bg-surface-1 p-6">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2">부서</th><th class="py-2 text-right">수량</th><th class="py-2 text-right">금액</th><th></th></tr></thead>
                <tbody>
                    <template x-for="(it,i) in items" :key="i">
                        <tr class="border-b border-line/60">
                            <td class="py-1.5" x-text="it.product_name"></td>
                            <td class="py-1.5 font-mono text-xs" x-text="it.lot_no"></td>
                            <td class="py-1.5"><input x-model="it.dept" class="w-24 rounded border-line py-1 text-xs"></td>
                            <td class="py-1.5 text-right"><input type="number" x-model.number="it.qty" min="1" :max="it.max" class="w-20 rounded border-line py-1 text-right font-mono text-xs"></td>
                            <td class="py-1.5 text-right font-mono" x-text="'₩'+(it.qty*it.unit).toLocaleString()"></td>
                            <td class="py-1.5 text-right"><button @click="items.splice(i,1)" class="text-xs text-crit-600 hover:underline">삭제</button></td>
                        </tr>
                    </template>
                    <template x-if="items.length===0"><tr><td colspan="6" class="py-8 text-center text-xs text-ink-400">스캔하거나 재고에서 품목을 추가하세요.</td></tr></template>
                </tbody>
                <tfoot x-show="items.length"><tr class="border-t border-line"><td colspan="4" class="py-2 text-right text-sm font-semibold text-ink-700">합계</td><td class="py-2 text-right font-mono font-bold text-ink-900" x-text="'₩'+total().toLocaleString()"></td><td></td></tr></tfoot>
            </table>
            <div class="mt-5 flex justify-end gap-2">
                <button @click="submitReport()" :disabled="saving || items.length===0" class="btn-primary !py-2.5" data-magnetic>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
                    본사로 전송
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function usageCreate(){
            return {
                usage_date: new Date().toISOString().slice(0,10),
                stock: @json($stock),
                pick:'', dept:'', items:[], saving:false,
                total(){ return this.items.reduce((s,it)=>s+it.qty*it.unit,0); },
                addStock(s, dept){
                    const ex = this.items.find(it=>it.lot_id===s.lot_id);
                    if(ex){ ex.qty++; return; }
                    this.items.push({ product_id:s.product_id, product_name:s.product_name, lot_id:s.lot_id, lot_no:s.lot_no, qty:1, max:s.qty, unit:s.unit, dept: dept||'' });
                },
                addManual(){ if(this.pick===''){ return; } this.addStock(this.stock[this.pick], this.dept); this.pick=''; },
                addFromScan(d){
                    const s = this.stock.find(x=>x.product_id===d.product.id && (!d.parsed.lot_no || x.lot_no===d.parsed.lot_no)) || this.stock.find(x=>x.product_id===d.product.id);
                    if(!s){ window.toast('현재고에 없는 품목/Lot 입니다.','warn'); return; }
                    this.addStock(s, this.dept);
                },
                async submitReport(){
                    this.saving=true;
                    const payload = { usage_date:this.usage_date, items:this.items.map(it=>({product_id:it.product_id, lot_id:it.lot_id, qty:it.qty, dept:it.dept})) };
                    const res = await fetch('{{ route('usages.store') }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,Accept:'application/json'}, body: JSON.stringify(payload) });
                    const data = await res.json();
                    if(!res.ok){ this.saving=false; window.toast(data.message||(data.errors&&Object.values(data.errors)[0][0])||'등록 실패','crit'); return; }
                    // 등록 후 즉시 전송
                    const sub = await fetch(`{{ url('usages') }}/${data.id}/submit`, { method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,Accept:'application/json'} });
                    const subData = await sub.json(); this.saving=false;
                    if(sub.ok){ window.toast(subData.message,'ok','사용분 전송'); this.items=[]; }
                    else window.toast(subData.message||'전송 실패','crit');
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
