<x-app-layout title="Lot 추적" breadcrumb="재고 / Lot 추적">
    <x-page-header title="Lot 추적" subtitle="리콜 대응 — 특정 Lot 의 입고·출고·사용 이력을 시간순으로 추적합니다." />

    <div class="mt-6" x-data="lotTrace()">
        {{-- Lot 검색 --}}
        <div class="relative max-w-xl">
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" x-model="q" @input.debounce.300ms="search()" @focus="search()"
                       placeholder="제품명 · 제품코드 · Lot 번호로 검색"
                       class="w-full rounded-xl border-line bg-surface-1 py-3 pl-10 pr-4 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
            </div>
            <div x-show="results.length && showList" x-cloak @click.outside="showList=false"
                 class="absolute z-20 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-line bg-surface-1 p-1.5 shadow-lift">
                <template x-for="r in results" :key="r.id">
                    <button @click="select(r)" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-ink-700 hover:bg-surface-2" x-text="r.label"></button>
                </template>
            </div>
        </div>

        {{-- 결과 --}}
        <template x-if="lot">
            <div class="mt-6">
                <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-surface-1 p-5">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3ZM12 12 4 7.5M12 12l8-4.5M12 12v9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <div>
                        <p class="font-display text-lg font-bold text-ink-900" x-text="lot.product_name"></p>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="chip-mono" x-text="'LOT ' + lot.lot_no"></span>
                            <template x-if="lot.expiry_date"><span class="chip-mono" x-text="'EXP ' + lot.expiry_date"></span></template>
                        </div>
                    </div>
                </div>

                {{-- 타임라인 --}}
                <div class="mt-5 rounded-2xl border border-line bg-surface-1 p-6">
                    <p class="mb-4 text-sm font-semibold text-ink-700">이동 이력 <span class="text-ink-300" x-text="'· ' + trace.length + '건'"></span></p>
                    <template x-if="trace.length === 0"><p class="py-8 text-center text-sm text-ink-400">이 Lot 의 이동 이력이 없습니다.</p></template>
                    <ol class="relative space-y-4 pl-6">
                        <template x-for="(t, i) in trace" :key="t.id">
                            <li class="relative">
                                <span class="absolute -left-6 top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-surface-1"
                                      :class="t.qty >= 0 ? 'bg-ok-600' : 'bg-crit-600'"></span>
                                <span class="absolute -left-[19px] top-4 h-full w-px bg-line" x-show="i < trace.length - 1"></span>
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <div>
                                        <span class="text-sm font-semibold text-ink-900" x-text="t.tx_type"></span>
                                        <span class="ml-2 text-sm text-ink-500" x-text="t.org_name"></span>
                                        <template x-if="t.memo"><span class="ml-2 text-xs text-ink-300" x-text="t.memo"></span></template>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-mono text-sm font-semibold tabular-nums" :class="t.qty >= 0 ? 'text-ok-600' : 'text-crit-600'"
                                              x-text="(t.qty >= 0 ? '+' : '') + t.qty.toLocaleString()"></span>
                                        <span class="font-mono text-xs text-ink-400" x-text="t.at"></span>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ol>
                </div>
            </div>
        </template>

        <template x-if="!lot">
            <div class="mt-16 flex flex-col items-center justify-center gap-3 text-center">
                <span class="grid h-14 w-14 place-items-center rounded-2xl bg-surface-2 text-ink-300">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                </span>
                <p class="text-sm text-ink-500">추적할 Lot 을 검색해 선택하세요.</p>
            </div>
        </template>
    </div>

    @push('scripts')
    <script>
        function lotTrace(){
            return {
                q:'', results:[], showList:false, lot:null, trace:[],
                async search(){
                    if(this.q.trim().length < 1){ this.results=[]; return; }
                    const res = await fetch(`{{ route('inventory.lot-trace.lots') }}?keyword=${encodeURIComponent(this.q)}`, { headers:{Accept:'application/json'} });
                    this.results = await res.json(); this.showList = true;
                },
                async select(r){
                    this.showList=false; this.q=r.label;
                    const res = await fetch(`{{ url('inventory/lot-trace') }}/${r.id}/trace`, { headers:{Accept:'application/json'} });
                    const data = await res.json();
                    this.lot = data.lot; this.trace = data.data;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
