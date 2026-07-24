/*
 * Scan Pulse — 바코드 스캔 입력 인터랙션 (DESIGN.md §4.3).
 * 성공: 입력창 테두리 틸 펄스(300ms) + 파싱된 칩 stagger 등장.
 * 실패: crit 셰이크(200ms) + 인라인 사유.
 * Alpine.data('scanInput') 로 등록되어 <x-scan-input> 에서 사용된다.
 */

document.addEventListener('alpine:init', () => {
    window.Alpine.data('scanInput', (endpoint, csrf) => ({
        value: '',
        state: 'idle', // idle | loading | success | error
        message: '',
        chips: [], // [{label, value}]
        product: null,

        async submit() {
            const scan = this.value.trim();
            if (!scan) return;

            this.state = 'loading';
            this.chips = [];
            this.message = '';

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                    },
                    body: new URLSearchParams({ scan }),
                });
                const data = await res.json();
                const p = data.parsed || {};

                // 칩 stagger 구성
                const chips = [];
                if (p.gtin) chips.push({ label: 'GTIN', value: p.gtin });
                if (p.lot_no) chips.push({ label: 'LOT', value: p.lot_no });
                if (p.expiry_date) chips.push({ label: 'EXP', value: p.expiry_date });
                this.chips = chips;

                if (data.matched) {
                    this.state = 'success';
                    this.product = data.product;
                    this.message = data.product.product_name;
                    this.pulse();
                    // 화면(Livewire/Alpine)으로 매칭 결과 전달
                    this.$dispatch('scan:matched', { parsed: p, product: data.product });
                } else {
                    this.state = 'error';
                    this.product = null;
                    this.message = data.message || '제품을 찾지 못했습니다.';
                    this.shake();
                    this.$dispatch('scan:unmatched', { parsed: p, message: this.message });
                }
            } catch (e) {
                this.state = 'error';
                this.message = '스캔 처리 중 오류가 발생했습니다.';
                this.shake();
            }

            this.value = '';
            this.$nextTick(() => this.$refs.input?.focus());
        },

        pulse() {
            const el = this.$refs.box;
            if (!el) return;
            el.classList.remove('scan-pulse');
            void el.offsetWidth; // reflow
            el.classList.add('scan-pulse');
        },

        shake() {
            const el = this.$refs.box;
            if (!el) return;
            el.classList.remove('scan-shake');
            void el.offsetWidth;
            el.classList.add('scan-shake');
        },
    }));
});
