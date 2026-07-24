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

    <div id="audit-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const badge = window.SmartGrid.badge({
                CREATE:{label:'생성',tone:'ok'}, UPDATE:{label:'수정',tone:'info'}, DELETE:{label:'삭제',tone:'crit'},
                APPROVE:{label:'승인',tone:'ok'}, REJECT:{label:'반려',tone:'warn'}, CLOSE:{label:'마감',tone:'info'},
                REOPEN:{label:'마감취소',tone:'warn'}, LOGIN:{label:'로그인',tone:'hold'}, LOGOUT:{label:'로그아웃',tone:'hold'},
            });
            const diff = (c) => {
                const v = c.getValue();
                if (!v) return '<span class="sg-mono">—</span>';
                const esc = v.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                return `<span class="sg-mono" style="font-size:11px" title="${esc}">${v.length>60 ? esc.slice(0,60)+'…' : esc}</span>`;
            };

            const grid = window.SmartGrid.create('#audit-grid', {
                dataUrl:'{{ route('admin.audit-logs.data') }}', readonly:true, pageSize:30,
                params:()=>({
                    action:document.getElementById('f-action').value,
                    entity:document.getElementById('f-entity').value,
                    date_from:document.getElementById('f-from').value,
                    date_to:document.getElementById('f-to').value,
                }),
                columns:[
                    { title:'일시', field:'created_at', width:170, formatter: window.SmartGrid.mono },
                    { title:'사용자', field:'user_name', width:130 },
                    { title:'동작', field:'action', width:100, formatter: badge },
                    { title:'대상', field:'entity', width:140, formatter: window.SmartGrid.mono },
                    { title:'대상ID', field:'entity_id', width:80, hozAlign:'right', formatter:(c)=>`<span class="sg-mono">${c.getValue()??'—'}</span>` },
                    { title:'변경 전', field:'before', minWidth:200, formatter: diff },
                    { title:'변경 후', field:'after', minWidth:200, formatter: diff },
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
