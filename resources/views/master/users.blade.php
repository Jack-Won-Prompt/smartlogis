@php
    use App\Enums\OrgType; use App\Enums\UserStatus; use App\Models\Organization;
    $roles = OrgType::options(); $statuses = UserStatus::options();
    $orgs = Organization::orderBy('name')->get(['id','name']);
@endphp

<x-app-layout title="사용자" breadcrumb="기준정보 / 사용자">
    <x-page-header title="사용자/권한" subtitle="셀에서 편집 후 [저장]으로 일괄 반영합니다. 신규 계정은 임시 비밀번호가 발급됩니다." />

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

    <div class="mb-4 mt-4 flex flex-wrap items-center justify-between gap-3">
        <x-excel-tools
            :download="route('master.users.export')"
            :upload="route('master.users.import')"
            :template="route('master.users.template')"
            :failures="url('master/users/failures')"
            name="사용자"
            note="양식(이메일·이름·역할·소속코드)을 작성해 업로드하세요. 이메일이 같으면 갱신됩니다."
            params="{ keyword: document.getElementById('f-keyword').value, role: document.getElementById('f-role').value, status: document.getElementById('f-status').value }" />
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
    <div id="user-grid" class="mt-1"></div>

    @push('scripts')
    <script>
        (function () {
            const roleMap = @json($roles);
            const statusMap = @json($statuses);
            const orgMap = @json($orgs->pluck('name','id'));
            function f(id){ return document.getElementById(id).value; }

            const grid = window.WWGrid.connect('#user-grid', {
                dataUrl: '{{ route('master.users.data') }}',
                batchUrl: '{{ route('master.users.batch') }}',
                screenName: '사용자',
                params: () => ({ keyword:f('f-keyword'), role:f('f-role'), status:f('f-status') }),
                defaults: { login_id:'', email:'', name:'', role:'HOSPITAL', org_id:'', status:'ACTIVE' },
                columns: [
                    { title:'이메일 (로그인 계정)', field:'email', editor:'text', width:230 },
                    { title:'이름', field:'name', editor:'text', width:110 },
                    { title:'역할', field:'role', editor:'list', values:roleMap, width:110 },
                    { title:'소속', field:'org_id', editor:'list', values:orgMap, width:170 },
                    { title:'상태', field:'status', editor:'list', values:statusMap, width:110 },
                    { title:'계정ID', field:'login_id', width:170 },
                ],
                buttons: { add:'btn-add', delete:'btn-delete', save:'btn-save', reset:'btn-reset' },
            });

            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),350);});
            ['f-role','f-status'].forEach(id=>document.getElementById(id).addEventListener('change',()=>grid.refresh()));
            document.getElementById('f-reset').addEventListener('click',()=>{['f-keyword','f-role','f-status'].forEach(id=>document.getElementById(id).value='');grid.refresh();});
        })();
    </script>
    @endpush
</x-app-layout>
