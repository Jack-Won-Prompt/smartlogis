<x-app-layout title="재고 실사" breadcrumb="재고 / 재고 실사">
    <x-page-header title="재고 실사" subtitle="대상(창고·병원)을 선택해 품목을 확인하고, 실사 수량을 입력해 확정하면 차이만큼 재고가 자동 조정됩니다." />

    <div class="mt-6" x-data="stocktakeApp()" x-init="init()">
        {{-- ── 상단: 대상 선택 + 실사 생성/확정 ── --}}
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4 shadow-sm">
            <div class="min-w-[220px]">
                <label class="mb-1 block text-xs font-medium text-ink-500">실사 대상 (창고·병원) <span class="text-crit-500">*</span></label>
                <select x-model="orgId" @change="onOrg()" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                    <option value="">대상 선택</option>
                    @foreach($orgs as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-ink-500">실사일</label>
                <input type="date" x-model="countDate" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
            </div>
            <div class="ml-auto flex items-center gap-2">
                <button @click="create()" :disabled="!orgId || saving" class="btn-primary !py-2 !text-sm disabled:opacity-50" data-magnetic>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> 실사 생성
                </button>
                <button @click="confirmStocktake()" :disabled="!stocktake || stocktake.status==='CONFIRMED' || saving"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-40">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> 실사 확정
                </button>
            </div>
        </div>

        {{-- ── 탭 ── --}}
        <div class="mt-4 flex items-center gap-1 border-b border-line">
            <template x-for="t in tabs" :key="t.key">
                <button @click="tab=t.key"
                        :class="tab===t.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-400 hover:text-ink-700'"
                        class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold">
                    <span x-text="t.label"></span>
                    <span x-show="t.count!==null" class="ml-1 text-xs text-ink-300" x-text="'('+t.count+')'"></span>
                </button>
            </template>
        </div>

        {{-- ── 재고 품목 ── --}}
        <div x-show="tab==='stock'" class="mt-4 overflow-hidden rounded-xl border border-line bg-white">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-line bg-surface-1 text-left text-xs text-ink-500">
                    <th class="px-4 py-2.5">제품코드</th><th class="px-4 py-2.5">제품명</th><th class="px-4 py-2.5">Lot</th>
                    <th class="px-4 py-2.5">유통기한</th><th class="px-4 py-2.5 text-right">현재고</th>
                </tr></thead>
                <tbody>
                    <template x-for="s in stock" :key="s.id">
                        <tr class="border-b border-line/60 last:border-0">
                            <td class="px-4 py-2 font-mono text-xs text-ink-400" x-text="s.product_code"></td>
                            <td class="px-4 py-2 font-medium text-ink-900" x-text="s.product_name"></td>
                            <td class="px-4 py-2 font-mono text-xs" x-text="s.lot_no"></td>
                            <td class="px-4 py-2 text-xs" x-text="s.expiry_date || '—'"></td>
                            <td class="px-4 py-2 text-right font-mono" x-text="Number(s.qty).toLocaleString()"></td>
                        </tr>
                    </template>
                    <tr x-show="stock.length===0"><td colspan="5" class="px-4 py-12 text-center text-sm text-ink-300" x-text="orgId ? '보유 재고가 없습니다.' : '대상을 선택하면 재고 품목이 표시됩니다.'"></td></tr>
                </tbody>
            </table>
        </div>

        {{-- ── 실사 입력 ── --}}
        <div x-show="tab==='count'" class="mt-4">
            <template x-if="!stocktake">
                <div class="rounded-xl border border-dashed border-line bg-surface-1 px-4 py-12 text-center text-sm text-ink-400">
                    <b>[실사 생성]</b>을 눌러 현재고 스냅샷으로 실사를 시작하거나, <b>[실사 이력]</b> 탭에서 기존 실사를 여세요.
                </div>
            </template>
            <div x-show="stocktake" class="overflow-hidden rounded-xl border border-line bg-white">
                <div class="flex flex-wrap items-center gap-3 border-b border-line bg-surface-1 px-4 py-3 text-sm">
                    <span class="font-mono font-bold text-ink-900" x-text="stocktake?.stocktake_no"></span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                          :class="stocktake?.status==='CONFIRMED' ? 'bg-ok-100 text-ok-700' : 'bg-info-100 text-info-700'"
                          x-text="stocktake?.status_label"></span>
                    <span class="ml-auto text-xs text-ink-500">차이 항목 <b class="text-ink-800" x-text="diffCount()"></b>건 · 순증감 <b :class="diffNet()>0?'text-ok-600':(diffNet()<0?'text-crit-600':'text-ink-500')" x-text="(diffNet()>0?'+':'')+diffNet()"></b></span>
                </div>
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-line text-left text-xs text-ink-500">
                        <th class="px-4 py-2.5">제품</th><th class="px-4 py-2.5">Lot</th>
                        <th class="px-4 py-2.5 text-right">전산 재고</th><th class="px-4 py-2.5 text-right">실사 수량</th><th class="px-4 py-2.5 text-right">차이</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="it in (stocktake?.items || [])" :key="it.id">
                            <tr class="border-b border-line/60 last:border-0">
                                <td class="px-4 py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span><span class="ml-1 font-mono text-xs text-ink-300" x-text="it.product_code"></span></td>
                                <td class="px-4 py-2 font-mono text-xs" x-text="it.lot_no"></td>
                                <td class="px-4 py-2 text-right font-mono" x-text="Number(it.system_qty).toLocaleString()"></td>
                                <td class="px-4 py-2 text-right">
                                    <input type="number" min="0" x-model.number="it.counted_qty" @change="saveItem(it)"
                                           :disabled="stocktake.status==='CONFIRMED'"
                                           class="w-24 rounded border-line py-1 text-right font-mono text-xs disabled:bg-surface-2">
                                </td>
                                <td class="px-4 py-2 text-right font-mono font-semibold" :class="diff(it)>0?'text-ok-600':(diff(it)<0?'text-crit-600':'text-ink-300')" x-text="(diff(it)>0?'+':'')+diff(it)"></td>
                            </tr>
                        </template>
                        <tr x-show="stocktake && stocktake.items.length===0"><td colspan="5" class="px-4 py-10 text-center text-sm text-ink-300">스냅샷된 품목이 없습니다(생성 시점 보유 재고 없음).</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── 실사 이력 ── --}}
        <div x-show="tab==='history'" class="mt-4 overflow-hidden rounded-xl border border-line bg-white">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-line bg-surface-1 text-left text-xs text-ink-500">
                    <th class="px-4 py-2.5">실사번호</th><th class="px-4 py-2.5">실사일</th><th class="px-4 py-2.5 text-right">품목</th><th class="px-4 py-2.5">상태</th><th class="px-4 py-2.5"></th>
                </tr></thead>
                <tbody>
                    <template x-for="h in history" :key="h.id">
                        <tr class="border-b border-line/60 last:border-0">
                            <td class="px-4 py-2 font-mono text-xs" x-text="h.stocktake_no"></td>
                            <td class="px-4 py-2" x-text="h.count_date"></td>
                            <td class="px-4 py-2 text-right font-mono" x-text="h.items_count"></td>
                            <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="h.status==='CONFIRMED'?'bg-ok-100 text-ok-700':(h.status==='COUNTING'?'bg-info-100 text-info-700':'bg-surface-2 text-ink-500')" x-text="h.status_label"></span></td>
                            <td class="px-4 py-2 text-right"><button @click="openStocktake(h.id)" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-brand-600 hover:bg-brand-50">열기</button></td>
                        </tr>
                    </template>
                    <tr x-show="history.length===0"><td colspan="5" class="px-4 py-10 text-center text-sm text-ink-300" x-text="orgId ? '실사 이력이 없습니다.' : '대상을 선택하세요.'"></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script>
        const CSRF = () => document.querySelector('meta[name=csrf-token]').content;
        function stocktakeApp(){
            return {
                orgId:'', countDate: new Date().toISOString().slice(0,10),
                tab:'stock', stock:[], stocktake:null, history:[], saving:false,
                get tabs(){ return [
                    { key:'stock', label:'재고 품목', count: this.stock.length },
                    { key:'count', label:'실사 입력', count: this.stocktake ? this.stocktake.items.length : null },
                    { key:'history', label:'실사 이력', count: this.history.length },
                ]; },
                init(){ if('{{ $orgs->count() }}'==='1'){ this.orgId='{{ $orgs->first()->id ?? '' }}'; this.onOrg(); } },
                async onOrg(){ this.stocktake=null; this.tab='stock'; if(!this.orgId){ this.stock=[]; this.history=[]; return; } await Promise.all([this.loadStock(), this.loadHistory()]); },
                async loadStock(){
                    const url=new URL('{{ route('inventory.status.data') }}', location.origin);
                    url.searchParams.set('size','300'); url.searchParams.set('org_id', this.orgId);
                    const r=await fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}); const d=await r.json();
                    this.stock=(d.data||[]).filter(x=>x.qty>0);
                },
                async loadHistory(){
                    const url=new URL('{{ route('stocktakes.data') }}', location.origin);
                    url.searchParams.set('size','50'); url.searchParams.set('org_id', this.orgId);
                    const r=await fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}); const d=await r.json();
                    this.history=d.data||[];
                },
                async create(){
                    if(!this.orgId){ window.toast('실사 대상을 선택하세요.','warn'); return; }
                    this.saving=true;
                    const r=await fetch('{{ route('stocktakes.store') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),Accept:'application/json'},body:JSON.stringify({org_id:this.orgId,count_date:this.countDate})});
                    const d=await r.json(); this.saving=false;
                    if(!r.ok){ window.toast(d.message||'생성 실패','crit'); return; }
                    window.toast(`실사 ${d.stocktake_no} 생성 — 수량을 입력하세요.`,'ok');
                    await this.openStocktake(d.id); await this.loadHistory();
                },
                async openStocktake(id){
                    const r=await fetch(`{{ url('stocktakes') }}/${id}`,{headers:{Accept:'application/json'}});
                    this.stocktake=await r.json(); this.tab='count';
                },
                diff(it){ return (it.counted_qty===null||it.counted_qty==='')?0:(Number(it.counted_qty)-Number(it.system_qty)); },
                diffCount(){ return this.stocktake ? this.stocktake.items.filter(it=>this.diff(it)!==0).length : 0; },
                diffNet(){ return this.stocktake ? this.stocktake.items.reduce((s,it)=>s+this.diff(it),0) : 0; },
                async saveItem(it){
                    if(it.counted_qty===null||it.counted_qty===''){ return; }
                    await fetch(`{{ url('stocktakes') }}/${this.stocktake.id}/items/${it.id}`,{method:'PATCH',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),Accept:'application/json'},body:JSON.stringify({counted_qty:it.counted_qty})});
                },
                async confirmStocktake(){
                    if(!this.stocktake || this.stocktake.status==='CONFIRMED') return;
                    const ok=await window.confirmDialog({title:'실사 확정', message:`${this.stocktake.stocktake_no} 확정 시 차이(${(this.diffNet()>0?'+':'')+this.diffNet()})만큼 재고가 조정됩니다. 진행할까요?`, tone:'warn', confirmText:'확정'});
                    if(!ok) return; this.saving=true;
                    const r=await fetch(`{{ url('stocktakes') }}/${this.stocktake.id}/confirm`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF(),Accept:'application/json'}});
                    const d=await r.json(); this.saving=false;
                    if(!r.ok){ window.toast(d.message||'확정 실패','crit'); return; }
                    window.toast(d.message,'ok','실사 확정');
                    await this.openStocktake(this.stocktake.id); await this.loadHistory(); await this.loadStock();
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
