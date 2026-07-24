@php
    use App\Enums\OrgType; use App\Enums\UserStatus; use App\Models\Organization;
    $roles = OrgType::options(); $statuses = UserStatus::options();
    $orgs = Organization::orderBy('name')->get(['id','name']);
@endphp

<x-app-layout title="사용자" breadcrumb="기준정보 / 사용자">
    <x-page-header title="사용자/권한" subtitle="계정을 셀에서 바로 수정·추가합니다. 신규 등록 시 임시 비밀번호가 발급됩니다." />

    <x-filter-bar class="mt-6">
        <div class="min-w-[220px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="이름 / 아이디 / 이메일" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">역할</label>
            <select id="f-role" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($roles as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">상태</label>
            <select id="f-status" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
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

    <div id="user-grid"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const roleMap = @json($roles);
            const statusMap = @json($statuses);
            const orgMap = @json($orgs->pluck('name','id'));
            const statusTones = { PENDING:'warn', INVITED:'info', ACTIVE:'ok', SUSPENDED:'hold' };
            function f(id){ return document.getElementById(id).value; }
            const filters = () => ({ keyword:f('f-keyword'), role:f('f-role'), status:f('f-status') });

            const grid = window.SmartGrid.create('#user-grid', {
                dataUrl: '{{ route('master.users.data') }}',
                createUrl: '{{ route('master.users.store') }}',
                updateUrl: (id) => `{{ url('master/users') }}/${id}`,
                deleteUrl: '{{ route('master.users.bulkDestroy') }}',
                params: filters,
                defaults: { login_id:'', email:'', name:'', role:'HOSPITAL', org_id:'', status:'ACTIVE' },
                columns: [
                    { title:'아이디', field:'login_id', editor:'input', width:130, formatter: window.SmartGrid.mono },
                    { title:'이름', field:'name', editor:'input', width:120 },
                    { title:'역할', field:'role', editor:'list', editorParams:{values:roleMap}, width:110, formatter:(c)=>roleMap[c.getValue()]||c.getValue() },
                    { title:'소속', field:'org_id', editor:'list', editorParams:{values:orgMap}, minWidth:160, formatter:(c)=>orgMap[c.getValue()]??c.getData().org_name??'' },
                    { title:'이메일', field:'email', editor:'input', minWidth:180 },
                    { title:'상태', field:'status', editor:'list', editorParams:{values:statusMap}, width:110,
                      formatter:(c)=>{const v=c.getValue();return `<span class="sg-badge sg-${statusTones[v]||'hold'}">${statusMap[v]||v}</span>`;} },
                ],
            });

            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            ['f-role','f-status'].forEach(id=>document.getElementById(id).addEventListener('change',()=>grid.refresh()));
            document.getElementById('f-reset').addEventListener('click',()=>{['f-keyword','f-role','f-status'].forEach(id=>document.getElementById(id).value='');grid.refresh();});
            document.getElementById('btn-add').addEventListener('click',()=>grid.addBlankRow());
            document.getElementById('btn-delete').addEventListener('click',()=>grid.deleteSelected());
        });
    </script>
    @endpush
</x-app-layout>
