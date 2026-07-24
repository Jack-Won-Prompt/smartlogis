<x-app-layout title="월 마감" breadcrumb="정산 / 월 마감">
    <x-page-header title="월 마감" subtitle="마감된 월은 사용분 등록·승인이 차단됩니다. 마감 취소는 본사 관리자만 가능합니다." />

    <div id="cl-grid" class="mt-6"></div>

    @push('scripts')
    <script>
        const CSRF=()=>document.querySelector('meta[name=csrf-token]').content;
        let clGrid;
        window.addEventListener('DOMContentLoaded', () => {
            clGrid = window.SmartGrid.create('#cl-grid', {
                dataUrl:'{{ route('settlements.closing.data') }}', readonly:true, pageSize:100,
                columns:[
                    { title:'정산월', field:'year_month', width:120, formatter: window.SmartGrid.mono },
                    { title:'정산 건수', field:'settle_count', width:110, hozAlign:'right', formatter:(c)=>`<span class="sg-mono">${c.getValue()}</span>` },
                    { title:'합계 금액', field:'total_amount', minWidth:180, hozAlign:'right', formatter: window.SmartGrid.money },
                    { title:'상태', field:'closed', width:120, formatter:(c)=>c.getValue()?`<span class="sg-badge sg-hold">마감 · ${c.getData().closed_at||''}</span>`:'<span class="sg-badge sg-info">진행중</span>' },
                    { title:'', field:'_act', width:120, headerSort:false, hozAlign:'right',
                      formatter:(c)=>c.getData().closed
                        ? '<span data-act="reopen" class="sg-act" style="width:auto;padding:3px 12px;color:#b4700a;border:1px solid #fbefd8;border-radius:8px;font-size:12px;font-weight:600">마감취소</span>'
                        : '<span data-act="close" class="sg-act" style="width:auto;padding:3px 12px;color:#fff;background:#2551c4;border-radius:8px;font-size:12px;font-weight:600">마감</span>',
                      cellClick:(e,cell)=>{ const b=e.target.closest('[data-act]'); if(b) act(cell.getData().year_month, b.dataset.act); } },
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
