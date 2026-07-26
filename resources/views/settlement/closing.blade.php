<x-app-layout title="월 마감" breadcrumb="정산 / 월 마감">
    <x-page-header title="월 마감" subtitle="마감된 월은 사용분 등록·승인이 차단됩니다. 마감 취소는 본사 관리자만 가능합니다." />

    <x-ww-grid-assets />
    <div id="cl-grid" class="mt-6"></div>

    @push('scripts')
    <script>
        const CSRF=()=>document.querySelector('meta[name=csrf-token]').content;
        let clGrid;
        window.addEventListener('DOMContentLoaded', () => {
            clGrid = window.WWGrid.connect('#cl-grid', {
                dataUrl:'{{ route('settlements.closing.data') }}', readonly:true, screenName:'월마감',
                onRowClick:(row)=>act(row.year_month, row.closed ? 'reopen' : 'close'),
                columns:[
                    { title:'정산월', field:'year_month', width:120 },
                    { title:'정산 건수', field:'settle_count', editor:'number', width:110 },
                    { title:'합계 금액', field:'total_amount', editor:'number', width:200 },
                    { title:'마감', field:'closed', editor:'checkbox', width:80 },
                    { title:'마감일', field:'closed_at', width:170 },
                ],
            });
        });
        async function act(ym, action){
            const isClose = action==='close';
            const ok = await window.confirmDialog({ title: isClose?'월 마감':'마감 취소', message:`${ym} 을(를) ${isClose?'마감':'마감 취소'}할까요?`, tone: isClose?'brand':'warn', confirmText:'확인' });
            if(!ok) return;
            const res = await fetch(`{{ url('settlements/closing') }}/${action}`, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),Accept:'application/json'}, body: JSON.stringify({ year_month: ym }) });
            const data = await res.json();
            window.toast(data.message||(res.ok?'완료':'실패'), res.ok?'ok':'crit');
            clGrid.refresh();
        }
    </script>
    @endpush
</x-app-layout>
