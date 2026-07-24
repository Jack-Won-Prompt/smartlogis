<x-app-layout title="대시보드" breadcrumb="대시보드">
    <x-page-header
        title="관제 대시보드"
        :subtitle="$role->label().' · '.now()->timezone('Asia/Seoul')->format('Y-m-d (D) H:i').' 기준'">
        <x-slot name="actions">
            <span class="chip-mono">{{ auth()->user()->organization->name }}</span>
        </x-slot>
    </x-page-header>

    {{-- 미니 Flow Rail: 오늘의 이동 수량 --}}
    <div class="mt-6 rounded-2xl border border-line bg-surface-1 p-6">
        <div class="mb-5 flex items-center justify-between">
            <p class="text-sm font-semibold text-ink-700">오늘의 물류 흐름</p>
            <span class="text-xs text-ink-400">단위: EA</span>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([
                ['공급사 입고', $flow['SUPPLIER']],
                ['병원 출고', $flow['WAREHOUSE']],
                ['병원 입고', $flow['HOSPITAL']],
                ['사용', $flow['USAGE']],
            ] as $item)
                <div class="relative rounded-xl bg-surface-0 p-4">
                    @unless($loop->first)
                        <span class="absolute -left-2 top-1/2 hidden -translate-y-1/2 text-ink-300 sm:block">▸</span>
                    @endunless
                    <p class="text-xs text-ink-500">{{ $item[0] }}</p>
                    <p class="mt-1 font-mono text-2xl font-bold text-ink-900 tabular-nums">{{ number_format($item[1]) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- KPI 카드 --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($kpis as $kpi)
            <x-kpi-card
                :label="$kpi['label']"
                :value="$kpi['value'].($kpi['suffix'] ? ' '.$kpi['suffix'] : '')"
                :tone="$kpi['tone']" />
        @endforeach
    </div>

    {{-- 차트 --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-line bg-surface-1 p-6 lg:col-span-2">
            <p class="mb-4 text-sm font-semibold text-ink-700">거점별 재고 분포 (상위 6)</p>
            <div class="h-64"><canvas id="chartByOrg"></canvas></div>
        </div>
        <div class="rounded-2xl border border-line bg-surface-1 p-6">
            <p class="mb-4 text-sm font-semibold text-ink-700">유통기한 구간 분포</p>
            <div class="h-64"><canvas id="chartExpiry"></canvas></div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-line bg-surface-1 p-6">
            <p class="mb-4 text-sm font-semibold text-ink-700">보관유형별 제품 수</p>
            <div class="h-56"><canvas id="chartStorage"></canvas></div>
        </div>
        <div class="rounded-2xl border border-line bg-surface-1 p-6 lg:col-span-2">
            <p class="mb-4 text-sm font-semibold text-ink-700">{{ $charts['trend']['label'] }} 추이</p>
            <div class="h-56">
                @if(count($charts['trend']['labels']))
                    <canvas id="chartTrend"></canvas>
                @else
                    <div class="flex h-full flex-col items-center justify-center gap-3 text-center">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7M2 20h20"/></svg>
                        </span>
                        <p class="max-w-sm text-sm text-ink-500">사용분 승인이 쌓이면 {{ $charts['trend']['label'] }} 추이가 표시됩니다.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        // app.js(deferred module)가 window.SmartCharts 를 세팅한 뒤 실행되도록 대기
        window.addEventListener('DOMContentLoaded', () => {
            const { registerChartTheme, barOptions, doughnutOptions, semantic } = window.SmartCharts;
            registerChartTheme(window.Chart);

            const byOrg = @json($charts['byOrg']);
            const expiry = @json($charts['expiry']);
            const storage = @json($charts['storage']);

            if (byOrg.labels.length) {
                new Chart(document.getElementById('chartByOrg'), {
                    type: 'bar',
                    data: { labels: byOrg.labels, datasets: [{ label: '재고 수량', data: byOrg.data, backgroundColor: '#2D6AE0', maxBarThickness: 40 }] },
                    options: barOptions({ plugins: { legend: { display: false } } }),
                });
            }

            new Chart(document.getElementById('chartExpiry'), {
                type: 'doughnut',
                data: { labels: expiry.labels, datasets: [{ data: expiry.data, backgroundColor: [semantic.crit, semantic.warn, semantic.info, semantic.ok], borderWidth: 0 }] },
                options: doughnutOptions(),
            });

            new Chart(document.getElementById('chartStorage'), {
                type: 'bar',
                data: { labels: storage.labels, datasets: [{ label: '제품 수', data: storage.data, backgroundColor: ['#2D6AE0','#2563A8','#7A5AC8'], maxBarThickness: 40 }] },
                options: barOptions({ plugins: { legend: { display: false } } }),
            });

            const trend = @json($charts['trend']);
            const trendEl = document.getElementById('chartTrend');
            if (trendEl && trend.labels.length) {
                const { lineOptions, money } = window.SmartCharts;
                new Chart(trendEl, {
                    type: 'line',
                    data: { labels: trend.labels, datasets: [{ label: trend.label, data: trend.data, borderColor: '#2D6AE0', backgroundColor: 'rgba(45,106,224,0.08)', fill: true }] },
                    options: lineOptions({ plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => money(c.parsed.y) } } }, scales: { y: { ticks: { callback: (v) => '₩' + (v/10000).toLocaleString() + '만' } } } }),
                });
            }
        });
        </script>
    @endpush
</x-app-layout>
