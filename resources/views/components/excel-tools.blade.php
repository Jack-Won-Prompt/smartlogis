@props([
    'download',            // 다운로드 URL (필수) — 현재 필터가 params 로 붙는다
    'name' => '',          // 화면명(안내문)
    'upload' => null,      // import URL (지정 시 업로드 버튼 표시)
    'template' => null,    // 양식 다운로드 URL
    'failures' => null,    // 실패 행 다운로드 base URL (뒤에 /{key})
    'params' => '{}',      // 현재 필터 JS 객체식 (다운로드에 쿼리스트링으로 부착)
    'note' => null,        // 업로드 안내문
])
<div class="relative flex items-center gap-2"
     x-data="excelTools({ downloadUrl: @js($download), importUrl: @js($upload), failBase: @js($failures), params: () => ({{ $params }}) })">
    <button type="button" @click="download()" class="btn-ghost !py-2 !text-sm">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        엑셀 다운로드
    </button>
    @if($upload)
        <button type="button" @click="open=!open" class="btn-ghost !py-2 !text-sm">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 8l5-5 5 5M12 3v12"/></svg>
            엑셀 업로드
        </button>
        <div x-show="open" x-cloak @click.outside="open=false"
             class="absolute left-0 top-full z-30 mt-2 w-80 rounded-xl border border-line bg-surface-1 p-4 shadow-lift">
            <p class="text-sm font-semibold text-ink-900">{{ $name }} 엑셀 업로드</p>
            <p class="mt-1 text-xs text-ink-500">{{ $note ?? '양식을 내려받아 작성 후 업로드하세요. 검증 실패 행은 결과에서 다시 받을 수 있습니다.' }}</p>
            @if($template)
                <a href="{{ $template }}" class="mt-3 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">양식 다운로드</a>
            @endif
            <input type="file" x-ref="file" accept=".xlsx,.xls,.csv"
                   class="mt-3 block w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:font-semibold file:text-brand-700">
            <button type="button" @click="upload()" :disabled="importing"
                    class="mt-3 w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                <span x-text="importing ? '처리 중…' : '업로드 실행'"></span>
            </button>
            <template x-if="failKey">
                <a :href="failBase + '/' + failKey"
                   class="mt-2 block rounded-lg border border-crit-600/30 bg-crit-100 px-3 py-2 text-center text-xs font-semibold text-crit-600">실패 행 다운로드</a>
            </template>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            // 공용 엑셀 툴바 Alpine 팩토리 — 다운로드(현재 필터 포함) + 업로드.
            window.excelTools = function (cfg) {
                return {
                    open: false, importing: false, failKey: null,
                    download() {
                        const p = cfg.params ? (cfg.params() || {}) : {};
                        const qs = new URLSearchParams();
                        Object.entries(p).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) qs.append(k, v); });
                        window.location.href = cfg.downloadUrl + (qs.toString() ? ('?' + qs.toString()) : '');
                    },
                    async upload() {
                        const file = this.$refs.file?.files?.[0];
                        if (!file) { window.toast('파일을 선택하세요.', 'warn'); return; }
                        this.importing = true; this.failKey = null;
                        const fd = new FormData(); fd.append('file', file);
                        try {
                            const res = await fetch(cfg.importUrl, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
                                body: fd,
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok) {
                                window.toast(data.message || '업로드 완료', data.failed > 0 ? 'warn' : 'ok', '엑셀 업로드');
                                this.failKey = data.failKey || null;
                                // 그리드 새로고침(있으면) 또는 페이지 리로드
                                if (window.__smartGridRefresh) window.__smartGridRefresh(); else setTimeout(() => window.location.reload(), 900);
                            } else {
                                window.toast(data.message || '업로드에 실패했습니다.', 'crit');
                            }
                        } catch (e) {
                            window.toast('업로드 중 오류가 발생했습니다.', 'crit');
                        }
                        this.importing = false;
                    },
                };
            };
        </script>
    @endpush
@endonce
