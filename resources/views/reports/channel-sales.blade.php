<x-app-layout title="채널별 매출" breadcrumb="리포트 / 채널별 매출">
    <x-page-header title="채널별 매출" subtitle="승인된 사용분(매출)을 GPO·Direct·3PL·수술·온라인 채널별로 집계합니다." />

    <x-filter-bar class="mt-6">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">시작일(사용일)</label>
            <input id="f-from" type="date" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">종료일</label>
            <input id="f-to" type="date" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        </div>
        <x-slot:actions><button id="f-apply" class="btn-primary !py-2 !text-sm">조회</button></x-slot:actions>
    </x-filter-bar>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_1.3fr]">
        <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
            <p class="text-xs text-ink-400">총 매출</p>
            <p id="total-amount" class="mt-1 font-mono text-2xl font-extrabold text-ink-900">₩0</p>
            <p id="total-cnt" class="text-xs text-ink-400">0건</p>
            <div class="mt-4 h-64"><canvas id="chartChannel"></canvas></div>
        </div>
        <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-line text-left text-xs text-ink-500">
                    <th class="py-2">채널</th><th class="py-2 text-right">건수</th><th class="py-2 text-right">매출</th><th class="py-2 text-right">비중</th>
                </tr></thead>
                <tbody id="rows"><tr><td colspan="4" class="py-8 text-center text-ink-300">조회 중…</td></tr></tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script>
        const won = n => '₩' + Math.round(n).toLocaleString();
        const palette = ['#2551C4','#0E7C7B','#D97706','#7C3AED','#0891B2'];
        let chart;
        async function loadReport() {
            const url = new URL('{{ route('reports.channel-sales.data') }}', location.origin);
            const f = document.getElementById('f-from').value, t = document.getElementById('f-to').value;
            if (f) url.searchParams.set('date_from', f); if (t) url.searchParams.set('date_to', t);
            const r = await fetch(url, { headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'} });
            const d = await r.json();
            document.getElementById('total-amount').textContent = won(d.total);
            document.getElementById('total-cnt').textContent = (d.total_cnt||0).toLocaleString() + '건';
            document.getElementById('rows').innerHTML = d.data.map((x,i) => `
                <tr class="border-b border-line/60">
                    <td class="py-2"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full" style="background:${palette[i%palette.length]}"></span>${x.channel_label}</td>
                    <td class="py-2 text-right font-mono">${x.cnt.toLocaleString()}</td>
                    <td class="py-2 text-right font-mono">${won(x.amount)}</td>
                    <td class="py-2 text-right font-mono">${x.share}%</td>
                </tr>`).join('');
            const labels = d.data.map(x=>x.channel_label), amounts = d.data.map(x=>x.amount);
            if (chart) chart.destroy();
            if (window.Chart) chart = new Chart(document.getElementById('chartChannel'), {
                type:'doughnut',
                data:{ labels, datasets:[{ data: amounts, backgroundColor: palette, borderWidth:0 }] },
                options:{ plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, font:{ size:11 } } } }, cutout:'62%' }
            });
        }
        window.addEventListener('DOMContentLoaded', () => {
            document.getElementById('f-apply').addEventListener('click', loadReport);
            loadReport();
        });
    </script>
    @endpush
</x-app-layout>
