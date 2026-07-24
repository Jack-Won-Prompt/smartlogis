@php use App\Enums\UsageStatus; $statuses = UsageStatus::options(); @endphp

<x-app-layout title="사용분 승인" breadcrumb="사용분 / 사용분 승인">
    <x-page-header title="사용분 승인" subtitle="전송된 사용분을 승인하면 병원 재고가 차감되고 정산에 반영됩니다." />

    <x-filter-bar class="mt-6">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-medium text-ink-500">검색</label>
            <input id="f-keyword" type="text" placeholder="사용분번호" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">상태</label>
            <select id="f-status" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                <option value="SUBMITTED">전송됨(승인대기)</option>
                <option value="">전체</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </div>
    </x-filter-bar>

    <div id="ap-grid" class="mt-4"></div>

    {{-- 승인/반려 모달 --}}
    <div x-data="approval()" @ap-open.window="load($event.detail)" x-show="show" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.escape.window="show=false">
        <div class="absolute inset-0 bg-navy/40 backdrop-blur-sm" @click="show=false"></div>
        <div class="relative flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-surface-1 shadow-lift"
             x-show="show" x-transition:enter="transition ease-brand duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <div><h3 class="font-display text-lg font-bold text-ink-900" x-text="doc.report_no"></h3>
                    <p class="text-xs text-ink-500"><span x-text="doc.hospital_name"></span> · <span x-text="doc.usage_date"></span></p></div>
                <button @click="show=false" class="text-ink-400 hover:text-ink-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-line text-left text-xs text-ink-500"><th class="py-2">제품</th><th class="py-2">Lot</th><th class="py-2">부서</th><th class="py-2 text-right">수량</th><th class="py-2 text-right">금액</th></tr></thead>
                    <tbody>
                        <template x-for="(it,i) in doc.items" :key="i">
                            <tr class="border-b border-line/60">
                                <td class="py-2"><span class="font-medium text-ink-900" x-text="it.product_name"></span></td>
                                <td class="py-2 font-mono text-xs" x-text="it.lot_no"></td>
                                <td class="py-2 text-xs text-ink-500" x-text="it.dept || '—'"></td>
                                <td class="py-2 text-right font-mono" x-text="Number(it.qty).toLocaleString()"></td>
                                <td class="py-2 text-right font-mono" x-text="'₩'+Number(it.amount).toLocaleString()"></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot><tr class="border-t border-line"><td colspan="4" class="py-2 text-right text-sm font-semibold text-ink-700">합계</td><td class="py-2 text-right font-mono font-bold text-ink-900" x-text="'₩'+Number(doc.total_amount).toLocaleString()"></td></tr></tfoot>
                </table>

                <div x-show="rejecting" x-cloak>
                    <label class="mb-1 block text-xs font-medium text-ink-500">반려 사유 *</label>
                    <textarea x-model="reason" rows="2" class="w-full rounded-lg border-line py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25" placeholder="예: 수량 오류, Lot 불일치"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
                <template x-if="!rejecting">
                    <div class="flex gap-2">
                        <button @click="rejecting=true" class="rounded-xl border border-crit-600/30 px-4 py-2.5 text-sm font-semibold text-crit-600 hover:bg-crit-100">반려</button>
                        <button @click="approve()" :disabled="saving || doc.status!=='SUBMITTED'" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">승인</button>
                    </div>
                </template>
                <template x-if="rejecting">
                    <div class="flex gap-2">
                        <button @click="rejecting=false" class="rounded-xl border border-line px-4 py-2.5 text-sm font-semibold text-ink-600 hover:bg-surface-2">취소</button>
                        <button @click="doReject()" :disabled="saving" class="rounded-xl bg-crit-600 px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-50">반려 확정</button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let apGrid; const CSRF=()=>document.querySelector('meta[name=csrf-token]').content;
        window.addEventListener('DOMContentLoaded', () => {
            const tones={ DRAFT:'hold', SUBMITTED:'info', APPROVED:'ok', REJECTED:'crit' };
            function f(id){ return document.getElementById(id).value; }
            apGrid = window.SmartGrid.create('#ap-grid', {
                dataUrl:'{{ route('usages.data') }}', readonly:true,
                params:()=>({ keyword:f('f-keyword'), status:f('f-status') }),
                columns:[
                    { title:'사용분번호', field:'report_no', width:200, formatter: window.SmartGrid.mono },
                    { title:'병원', field:'hospital_name', minWidth:140 },
                    { title:'사용일', field:'usage_date', width:120, formatter:(c)=>`<span class="sg-mono">${c.getValue()}</span>` },
                    { title:'품목', field:'items_count', width:70, hozAlign:'right', formatter:(c)=>`<span class="sg-mono">${c.getValue()}</span>` },
                    { title:'금액', field:'total_amount', width:130, hozAlign:'right', formatter: window.SmartGrid.money },
                    { title:'상태', field:'status', width:110, formatter:(c)=>`<span class="sg-badge sg-${tones[c.getValue()]||'hold'}">${c.getData().status_label}</span>` },
                    { title:'', field:'_a', width:80, headerSort:false, hozAlign:'right',
                      formatter:(c)=>c.getData().status==='SUBMITTED'?'<span class="sg-act" style="width:auto;padding:2px 10px;color:#2551c4;font-size:12px;font-weight:600">검토 ▸</span>':'<span style="color:#93a4b6;font-size:12px">보기</span>',
                      cellClick:(e,cell)=>window.dispatchEvent(new CustomEvent('ap-open',{detail:cell.getData().id})) },
                ],
            });
            let t;
            document.getElementById('f-keyword').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>apGrid.refresh(),350);});
            document.getElementById('f-status').addEventListener('change',()=>apGrid.refresh());
        });

        function approval(){
            return {
                show:false, saving:false, rejecting:false, reason:'', doc:{items:[]},
                async load(id){ this.rejecting=false; this.reason=''; const r=await fetch(`{{ url('usages') }}/${id}`,{headers:{Accept:'application/json'}}); this.doc=await r.json(); this.show=true; },
                async approve(){
                    const ok=await window.confirmDialog({ title:'사용분 승인', message:`${this.doc.report_no} 승인 시 재고가 차감되고 정산에 반영됩니다.`, tone:'brand', confirmText:'승인', summary:[{label:'병원',value:this.doc.hospital_name},{label:'품목',value:this.doc.items.length+'건'},{label:'합계',value:'₩'+Number(this.doc.total_amount).toLocaleString()}] });
                    if(!ok) return; this.saving=true;
                    const res=await fetch(`{{ url('usages') }}/${this.doc.id}/approve`,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF(),Accept:'application/json'}});
                    const data=await res.json(); this.saving=false;
                    if(res.ok){ window.toast(data.message,'ok','승인'); this.show=false; apGrid.refresh(); } else window.toast(data.message||'승인 실패','crit');
                },
                async doReject(){
                    if(!this.reason.trim()){ window.toast('반려 사유를 입력하세요.','warn'); return; }
                    this.saving=true;
                    const res=await fetch(`{{ url('usages') }}/${this.doc.id}/reject`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),Accept:'application/json'},body:JSON.stringify({reason:this.reason})});
                    const data=await res.json(); this.saving=false;
                    if(res.ok){ window.toast(data.message,'ok','반려'); this.show=false; apGrid.refresh(); } else window.toast(data.message||'반려 실패','crit');
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
