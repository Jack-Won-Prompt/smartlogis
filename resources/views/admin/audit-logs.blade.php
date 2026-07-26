<x-app-layout title="감사 로그" breadcrumb="관리 / 감사 로그">
    <x-page-header title="감사 로그" subtitle="마스터·문서의 생성/수정/삭제와 승인·마감·로그인 이력을 기록합니다. (읽기 전용)" />

    <x-filter-bar class="mt-6">
        <div class="min-w-[160px]">
            <label class="mb-1 block text-xs font-medium text-ink-500">동작</label>
            <select id="f-action" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>
                @foreach (\App\Enums\AuditAction::cases() as $a)
                    <option value="{{ $a->value }}">{{ $a->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[160px]">
            <label class="mb-1 block text-xs font-medium text-ink-500">대상(엔티티)</label>
            <input id="f-entity" type="text" placeholder="예: Product, UsageReport" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">시작일</label>
            <input id="f-from" type="date" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">종료일</label>
            <input id="f-to" type="date" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
    </x-filter-bar>

    <x-ww-grid-assets />
    <div id="audit-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            const actionMap = { CREATE:'생성', UPDATE:'수정', DELETE:'삭제', APPROVE:'승인', REJECT:'반려', CLOSE:'마감', REOPEN:'마감취소', LOGIN:'로그인', LOGOUT:'로그아웃' };

            const grid = window.WWGrid.connect('#audit-grid', {
                dataUrl:'{{ route('admin.audit-logs.data') }}', readonly:true, screenName:'감사로그',
                params:()=>({
                    action:document.getElementById('f-action').value,
                    entity:document.getElementById('f-entity').value,
                    date_from:document.getElementById('f-from').value,
                    date_to:document.getElementById('f-to').value,
                }),
                columns:[
                    { title:'일시', field:'created_at', width:170 },
                    { title:'사용자', field:'user_name', width:130 },
                    { title:'동작', field:'action', editor:'list', values:actionMap, width:100 },
                    { title:'대상', field:'entity', width:140 },
                    { title:'대상ID', field:'entity_id', width:80, align:'right' },
                    { title:'변경 전', field:'before', width:260 },
                    { title:'변경 후', field:'after', width:260 },
                ],
            });
            let t;
            ['f-action','f-entity','f-from','f-to'].forEach(id => {
                const el = document.getElementById(id);
                const ev = el.tagName === 'SELECT' ? 'change' : 'input';
                el.addEventListener(ev, ()=>{clearTimeout(t);t=setTimeout(()=>grid.refresh(),300);});
            });
        });
    </script>
    @endpush
</x-app-layout>
