@php
    use App\Enums\OrgType; use App\Models\Organization; use App\Models\Product;
    $hospitals = Organization::where('org_type', OrgType::HOSPITAL)->orderBy('name')->get(['id','name']);
    $products = Product::where('is_active', true)->orderBy('product_name')->get(['id','product_name','product_code']);
@endphp

<x-app-layout title="안전재고" breadcrumb="기준정보 / 안전재고">
    <x-page-header title="안전재고 설정" subtitle="병원 × 품목 안전재고를 셀에서 바로 수정·추가하고, 최근 사용량(월평균) 기반으로 안전재고를 자동 산출합니다." />

    <x-filter-bar class="mt-6">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">병원</label>
            <select id="f-hospital" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($hospitals as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
            </select>
        </div>
        <div class="min-w-[220px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">제품 검색</label>
            <input id="f-keyword" type="text" placeholder="제품명 / 코드" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <x-slot:actions><button id="f-reset" class="rounded-lg border border-line bg-surface-1 px-4 py-2 text-sm font-medium text-ink-600 hover:bg-surface-0">초기화</button></x-slot:actions>
    </x-filter-bar>

    <div class="mb-4 mt-4 flex flex-wrap items-center justify-between gap-3">
        <x-excel-tools
            :download="route('master.safety-stocks.export')"
            :upload="route('master.safety-stocks.import')"
            :template="route('master.safety-stocks.template')"
            :failures="url('master/safety-stocks/failures')"
            name="안전재고"
            note="병원코드·제품코드 기준으로 갱신됩니다."
            params="{ hospital_id: document.getElementById('f-hospital').value, keyword: document.getElementById('f-keyword').value }" />
        <div class="flex items-center gap-2">
            <button id="btn-suggest" class="btn-ghost !py-2 !text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7M21 4v4h-4"/></svg>
                자동 산출
            </button>
            <button id="btn-save" class="btn-amber inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition-colors" data-magnetic>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> 저장
            </button>
            <button id="btn-reset" class="rounded-lg border border-line bg-surface-1 px-4 py-2 text-sm font-medium text-ink-600 hover:bg-surface-0">변경 취소</button>
            <button id="btn-delete" class="btn-ghost !py-2 !text-sm !text-crit-600 !ring-crit-600/20 hover:!bg-crit-100">선택 삭제</button>
            <button id="btn-add" class="btn-primary !py-2 !text-sm" data-magnetic>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> 행 추가
            </button>
        </div>
    </div>

    <x-ww-grid-assets />
    <div id="ss-grid" class="mt-1"></div>

    @push('scripts')
    <script>
        (function () {
            const hospitalMap = @json($hospitals->pluck('name','id'));
            const productMap = @json($products->mapWithKeys(fn($p)=>[$p->id => $p->product_name.' ('.$p->product_code.')']));
            function f(id){ return document.getElementById(id).value; }

            const grid = window.WWGrid.connect('#ss-grid', {
                dataUrl: '{{ route('master.safety-stocks.data') }}',
                batchUrl: '{{ route('master.safety-stocks.batch') }}',
                screenName: '안전재고',
                params: () => ({ hospital_id:f('f-hospital'), keyword:f('f-keyword') }),
                defaults: { hospital_id:'', product_id:'', safety_qty:0, max_qty:0, reorder_qty:0 },
                columns: [
                    { title:'병원', field:'hospital_id', editor:'list', values:hospitalMap, width:180 },
                    { title:'제품', field:'product_id', editor:'list', values:productMap, width:280 },
                    { title:'안전재고', field:'safety_qty', editor:'number', width:110 },
                    { title:'최대재고', field:'max_qty', editor:'number', width:110 },
                    { title:'보충수량', field:'reorder_qty', editor:'number', width:110 },
                ],
                buttons: { add:'btn-add', delete:'btn-delete', save:'btn-save', reset:'btn-reset' },
            });

            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            document.getElementById('f-hospital').addEventListener('change',()=>grid.refresh());
            document.getElementById('f-reset').addEventListener('click',()=>{['f-keyword','f-hospital'].forEach(id=>document.getElementById(id).value='');grid.refresh();});

            document.getElementById('btn-suggest').addEventListener('click', async () => {
                const hid = f('f-hospital');
                if(!hid){ window.toast('병원을 먼저 선택하세요.','warn'); return; }
                const ok = await window.confirmDialog({ title:'자동 산출', message:'선택 병원의 안전재고를 최근 사용량 기준으로 추천 적용할까요?', tone:'brand', confirmText:'적용' });
                if(!ok) return;
                const res = await fetch('{{ route('master.safety-stocks.autoSuggest') }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content, Accept:'application/json'}, body: JSON.stringify({ hospital_id: hid }) });
                const data = await res.json();
                window.toast(data.message || '완료', res.ok ? 'ok':'warn', '자동 산출');
                grid.refresh();
            });
        })();
    </script>
    @endpush
</x-app-layout>
