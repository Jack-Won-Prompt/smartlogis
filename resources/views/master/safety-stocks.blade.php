@php
    use App\Enums\OrgType; use App\Models\Organization; use App\Models\Product;
    $hospitals = Organization::where('org_type', OrgType::HOSPITAL)->orderBy('name')->get(['id','name']);
    $products = Product::where('is_active', true)->orderBy('product_name')->get(['id','product_name','product_code']);
@endphp

<x-app-layout title="안전재고" breadcrumb="기준정보 / 안전재고">
    <x-page-header title="안전재고 설정" subtitle="병원 × 품목 안전재고를 셀에서 바로 수정·추가하고, 현재고 기반으로 자동 산출합니다." />

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
        <div class="flex items-center gap-2" x-data="{ open:false, importing:false, failKey:null }">
            <a href="{{ route('master.safety-stocks.export') }}" class="btn-ghost !py-2 !text-sm">엑셀 다운로드</a>
            <button @click="open=!open" class="btn-ghost !py-2 !text-sm">엑셀 업로드</button>
            <div x-show="open" x-cloak @click.outside="open=false" class="absolute z-30 mt-24 w-80 rounded-xl border border-line bg-surface-1 p-4 shadow-lift">
                <p class="text-sm font-semibold text-ink-900">안전재고 엑셀 업로드</p>
                <p class="mt-1 text-xs text-ink-500">병원코드·제품코드 기준으로 갱신됩니다.</p>
                <a href="{{ route('master.safety-stocks.template') }}" class="mt-3 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">양식 다운로드</a>
                <input type="file" id="import-file" accept=".xlsx,.xls,.csv" class="mt-3 block w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:font-semibold file:text-brand-700">
                <button @click="ssImport($event,this)" :disabled="importing" class="mt-3 w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                    <span x-text="importing ? '처리 중…' : '업로드 실행'"></span>
                </button>
                <template x-if="failKey"><a :href="`{{ url('master/safety-stocks/failures') }}/${failKey}`" class="mt-2 block rounded-lg border border-crit-600/30 bg-crit-100 px-3 py-2 text-center text-xs font-semibold text-crit-600">실패 행 다운로드</a></template>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button id="btn-suggest" class="btn-ghost !py-2 !text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7M21 4v4h-4"/></svg>
                자동 산출
            </button>
            <button id="btn-delete" class="btn-ghost !py-2 !text-sm !text-crit-600 !ring-crit-600/20 hover:!bg-crit-100">선택 삭제</button>
            <button id="btn-add" class="btn-primary !py-2 !text-sm" data-magnetic>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> 행 추가
            </button>
        </div>
    </div>

    <div id="ss-grid"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const hospitalMap = @json($hospitals->pluck('name','id'));
            const productMap = @json($products->mapWithKeys(fn($p)=>[$p->id => $p->product_name.' ('.$p->product_code.')']));
            function f(id){ return document.getElementById(id).value; }
            const filters = () => ({ hospital_id:f('f-hospital'), keyword:f('f-keyword') });

            const grid = window.SmartGrid.create('#ss-grid', {
                dataUrl: '{{ route('master.safety-stocks.data') }}',
                createUrl: '{{ route('master.safety-stocks.store') }}',
                updateUrl: (id) => `{{ url('master/safety-stocks') }}/${id}`,
                deleteUrl: '{{ route('master.safety-stocks.bulkDestroy') }}',
                params: filters,
                defaults: { hospital_id:'', product_id:'', safety_qty:0, max_qty:0, reorder_qty:0 },
                columns: [
                    { title:'병원', field:'hospital_id', editor:'list', editorParams:{values:hospitalMap}, minWidth:150, formatter:(c)=>hospitalMap[c.getValue()]??c.getData().hospital_name??'' },
                    { title:'제품', field:'product_id', editor:'list', editorParams:{values:productMap}, minWidth:220, formatter:(c)=>c.getData().product_name ?? (productMap[c.getValue()]||'') },
                    { title:'안전재고', field:'safety_qty', editor:'number', hozAlign:'right', width:120, formatter:(c)=>`<span class="sg-mono" style="font-weight:600">${Number(c.getValue()).toLocaleString()}</span>` },
                    { title:'최대재고', field:'max_qty', editor:'number', hozAlign:'right', width:120, formatter:(c)=>`<span class="sg-mono">${Number(c.getValue()).toLocaleString()}</span>` },
                    { title:'보충수량', field:'reorder_qty', editor:'number', hozAlign:'right', width:120, formatter:(c)=>`<span class="sg-mono">${Number(c.getValue()).toLocaleString()}</span>` },
                ],
            });

            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            document.getElementById('f-hospital').addEventListener('change',()=>grid.refresh());
            document.getElementById('f-reset').addEventListener('click',()=>{['f-keyword','f-hospital'].forEach(id=>document.getElementById(id).value='');grid.refresh();});
            document.getElementById('btn-add').addEventListener('click',()=>grid.addBlankRow());
            document.getElementById('btn-delete').addEventListener('click',()=>grid.deleteSelected());

            document.getElementById('btn-suggest').addEventListener('click', async () => {
                const hid = f('f-hospital');
                if(!hid){ window.toast('병원을 먼저 선택하세요.','warn'); return; }
                const ok = await window.confirmDialog({ title:'자동 산출', message:'선택 병원의 안전재고를 현재고 기준으로 추천 적용할까요?', tone:'brand', confirmText:'적용' });
                if(!ok) return;
                const res = await fetch('{{ route('master.safety-stocks.autoSuggest') }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content, Accept:'application/json'}, body: JSON.stringify({ hospital_id: hid }) });
                const data = await res.json();
                window.toast(data.message || '완료', res.ok ? 'ok':'warn', '자동 산출');
                grid.refresh();
            });
        });

        async function ssImport(e, ctx){
            const input = document.getElementById('import-file');
            if(!input.files.length){ window.toast('파일을 선택하세요.','warn'); return; }
            ctx.importing = true;
            const fd = new FormData(); fd.append('file', input.files[0]);
            try {
                const res = await fetch('{{ route('master.safety-stocks.import') }}', { method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content, Accept:'application/json'}, body: fd });
                const data = await res.json();
                ctx.failKey = data.failKey;
                window.toast(data.message, data.failed ? 'warn':'ok', '엑셀 업로드');
                location.reload();
            } catch(err){ window.toast('업로드 중 오류가 발생했습니다.','crit'); }
            finally { ctx.importing = false; }
        }
    </script>
    @endpush
</x-app-layout>
