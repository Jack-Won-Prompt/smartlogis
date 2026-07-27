@php
    use App\Models\User;
    $users = User::orderBy('name')->get(['id', 'name']);
@endphp

<x-app-layout title="접속 로그" breadcrumb="관리 / 접속 로그">
    <x-page-header title="접속 로그" subtitle="메인(게스트) 포함 모든 페이지 접근 이력을 조회합니다. (읽기 전용)" />

    <x-filter-bar class="mt-6">
        <div class="min-w-[220px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색(경로 / IP)</label>
            <input id="f-keyword" type="text" placeholder="/chat, /dashboard, 192.168..." class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">사용자</label>
            <select id="f-user" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="">전체</option>
                <option value="guest">게스트(비로그인)</option>
                @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">시작일</label>
            <input id="f-from" type="date" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">종료일</label>
            <input id="f-to" type="date" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <x-slot:actions><button id="f-reset" class="rounded-lg border border-line bg-surface-1 px-4 py-2 text-sm font-medium text-ink-600 hover:bg-surface-0">초기화</button></x-slot:actions>
    </x-filter-bar>

    <x-ww-grid-assets />
    <div id="access-grid" class="mt-4"></div>

    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            function f(id){ return document.getElementById(id).value; }
            const grid = window.WWGrid.connect('#access-grid', {
                dataUrl: '{{ route('admin.access-logs.data') }}',
                readonly: true, screenName: '접속로그',
                params: () => {
                    const u = f('f-user');
                    return {
                        keyword: f('f-keyword'),
                        user_id: (u && u !== 'guest') ? u : '',
                        guest: u === 'guest' ? 1 : '',
                        date_from: f('f-from'),
                        date_to: f('f-to'),
                    };
                },
                columns: [
                    { title:'일시', field:'created_at', width:170 },
                    { title:'사용자', field:'user_name', width:150 },
                    { title:'로그인 ID', field:'login_id', width:150 },
                    { title:'경로', field:'path', width:240 },
                    { title:'라우트', field:'route', width:180 },
                    { title:'IP', field:'ip', width:130 },
                    { title:'User-Agent', field:'user_agent', width:320 },
                ],
            });

            let t;
            document.getElementById('f-keyword').addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => grid.refresh(), 350); });
            ['f-user','f-from','f-to'].forEach(id => document.getElementById(id).addEventListener('change', () => grid.refresh()));
            document.getElementById('f-reset').addEventListener('click', () => { ['f-keyword','f-user','f-from','f-to'].forEach(id => document.getElementById(id).value=''); grid.refresh(); });
        });
    </script>
    @endpush
</x-app-layout>
