@php use App\Enums\OrgType; $orgTypes = OrgType::options(); @endphp

<x-app-layout title="거래처" breadcrumb="기준정보 / 거래처">
    <x-page-header title="거래처 관리" subtitle="본사·물류창고·거점병원·공급사를 셀에서 바로 수정·추가합니다." />

    <x-filter-bar class="mt-6">
        <div class="min-w-[220px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="거래처명 / 코드 / 사업자번호" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">유형</label>
            <select id="f-type" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($orgTypes as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">사용여부</label>
            <select id="f-active" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option><option value="1">사용</option><option value="0">중지</option>
            </select>
        </div>
        <x-slot:actions><button id="f-reset" class="rounded-lg border border-line bg-surface-1 px-4 py-2 text-sm font-medium text-ink-600 hover:bg-surface-0">초기화</button></x-slot:actions>
    </x-filter-bar>

    <div class="mb-4 mt-4 flex items-center justify-end gap-2">
        <button id="btn-delete" class="btn-ghost !py-2 !text-sm !text-crit-600 !ring-crit-600/20 hover:!bg-crit-100">선택 삭제</button>
        <button id="btn-add" class="btn-primary !py-2 !text-sm" data-magnetic>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> 행 추가
        </button>
    </div>

    <div id="org-grid"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const typeMap = @json($orgTypes);
            const typeTones = { HQ:'info', WAREHOUSE:'hold', HOSPITAL:'ok', SUPPLIER:'warn' };
            const filters = () => ({ keyword: f('f-keyword'), org_type: f('f-type'), is_active: f('f-active') });
            function f(id){ return document.getElementById(id).value; }

            const grid = window.SmartGrid.create('#org-grid', {
                dataUrl: '{{ route('master.organizations.data') }}',
                createUrl: '{{ route('master.organizations.store') }}',
                updateUrl: (id) => `{{ url('master/organizations') }}/${id}`,
                deleteUrl: '{{ route('master.organizations.bulkDestroy') }}',
                params: filters,
                defaults: { org_type:'HOSPITAL', code:'', name:'', biz_reg_no:'', tel:'', is_active:true },
                columns: [
                    { title:'코드', field:'code', editor:'input', width:130, formatter: window.SmartGrid.mono },
                    { title:'유형', field:'org_type', editor:'list', editorParams:{values:typeMap}, width:120,
                      formatter:(c)=>{const v=c.getValue();return `<span class="sg-badge sg-${typeTones[v]||'hold'}">${typeMap[v]||v}</span>`;} },
                    { title:'거래처명', field:'name', editor:'input', minWidth:200 },
                    { title:'사업자번호', field:'biz_reg_no', editor:'input', width:150, formatter: window.SmartGrid.mono },
                    { title:'연락처', field:'tel', editor:'input', width:150 },
                    { title:'사용자', field:'users_count', width:90, hozAlign:'right', formatter:(c)=>`<span class="sg-mono">${c.getValue()}</span>` },
                    { title:'상태', field:'is_active', editor:'tickCross', hozAlign:'center', width:90,
                      formatter:(c)=>c.getValue()?'<span class="sg-badge sg-ok">사용</span>':'<span class="sg-badge sg-hold">중지</span>' },
                ],
            });

            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            ['f-type','f-active'].forEach(id=>document.getElementById(id).addEventListener('change',()=>grid.refresh()));
            document.getElementById('f-reset').addEventListener('click',()=>{['f-keyword','f-type','f-active'].forEach(id=>document.getElementById(id).value='');grid.refresh();});
            document.getElementById('btn-add').addEventListener('click',()=>grid.addBlankRow());
            document.getElementById('btn-delete').addEventListener('click',()=>grid.deleteSelected());
        });
    </script>
    @endpush
</x-app-layout>
