/*
 * 커스텀 UI 인프라 — 토스트/확인 다이얼로그 (네이티브 alert/confirm 미사용).
 * Alpine store 로 등록되어 어디서든 호출 가능:
 *   window.toast('저장되었습니다', 'ok')
 *   Livewire: $this->dispatch('toast', message: '...', tone: 'ok')
 *   확인 모달: window.confirmDialog({ title, message, summary, tone, confirmText }).then(ok => ...)
 */

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // ── 토스트 스토어 ─────────────────────────────
    Alpine.store('toasts', {
        items: [],
        seq: 0,
        push(message, tone = 'info', title = null, timeout = 4000) {
            const id = ++this.seq;
            this.items.push({ id, message, tone, title });
            if (timeout > 0) setTimeout(() => this.remove(id), timeout);
            return id;
        },
        remove(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    // ── 확인 다이얼로그 스토어 ────────────────────
    Alpine.store('confirm', {
        open: false,
        payload: {},
        _resolve: null,
        show(opts) {
            this.payload = {
                title: opts.title ?? '확인',
                message: opts.message ?? '',
                summary: opts.summary ?? null, // [{label, value}]
                tone: opts.tone ?? 'brand', // brand | crit | warn
                confirmText: opts.confirmText ?? '확인',
                cancelText: opts.cancelText ?? '취소',
            };
            this.open = true;
            return new Promise((resolve) => (this._resolve = resolve));
        },
        respond(ok) {
            this.open = false;
            if (this._resolve) this._resolve(ok);
            this._resolve = null;
        },
    });

    // 전역 헬퍼
    window.toast = (message, tone = 'info', title = null, timeout = 4000) =>
        Alpine.store('toasts').push(message, tone, title, timeout);
    window.confirmDialog = (opts) => Alpine.store('confirm').show(opts);

    // Livewire → 커스텀 토스트 브리지
    window.addEventListener('toast', (e) => {
        const d = e.detail || {};
        Alpine.store('toasts').push(d.message ?? '', d.tone ?? 'info', d.title ?? null, d.timeout ?? 4000);
    });
});
