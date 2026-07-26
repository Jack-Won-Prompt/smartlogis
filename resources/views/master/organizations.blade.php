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

    <div class="mb-4 mt-4 flex flex-wrap items-center justify-between gap-3">
        <x-excel-tools
            :download="route('master.organizations.export')"
            :upload="route('master.organizations.import')"
            :template="route('master.organizations.template')"
            :failures="url('master/organizations/failures')"
            name="거래처"
            note="양식을 내려받아 작성 후 업로드하세요. 코드가 같으면 갱신됩니다."
            params="{ keyword: document.getElementById('f-keyword').value, org_type: document.getElementById('f-type').value, is_active: document.getElementById('f-active').value }" />
        <div class="flex items-center gap-2">
            <button id="btn-save" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-600" data-magnetic>
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
    <div id="org-grid" class="mt-1"></div>

    @push('scripts')
    <script>
        (function () {
            const typeMap = @json($orgTypes);
            function f(id){ return document.getElementById(id).value; }
            const grid = window.WWGrid.connect('#org-grid', {
                dataUrl: '{{ route('master.organizations.data') }}',
                batchUrl: '{{ route('master.organizations.batch') }}',
                screenName: '거래처',
                params: () => ({ keyword: f('f-keyword'), org_type: f('f-type'), is_active: f('f-active') }),
                defaults: { org_type:'HOSPITAL', code:'', name:'', biz_reg_no:'', tel:'', is_active:true },
                columns: [
                    { title:'코드', field:'code', editor:'text', width:130 },
                    { title:'유형', field:'org_type', editor:'list', values:typeMap, width:110 },
                    { title:'거래처명', field:'name', editor:'text', width:200 },
                    { title:'사업자번호', field:'biz_reg_no', editor:'text', width:140 },
                    { title:'연락처', field:'tel', editor:'text', width:130 },
                    { title:'사용자', field:'users_count', align:'right', width:80 },
                    { title:'상태', field:'is_active', editor:'checkbox', width:90 },
                ],
                buttons: { add:'btn-add', delete:'btn-delete', save:'btn-save', reset:'btn-reset' },
            });
            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            ['f-type','f-active'].forEach(id=>document.getElementById(id).addEventListener('change',()=>grid.refresh()));
            document.getElementById('f-reset').addEventListener('click',()=>{['f-keyword','f-type','f-active'].forEach(id=>document.getElementById(id).value='');grid.refresh();});
        })();
    </script>
    @endpush
</x-app-layout>
