/*
 * SmartLogis 프런트 인터랙션 (바닐라 ES 모듈, 프레임워크 없음).
 * - 스크롤 등장(reveal), 숫자 카운트업, 마그네틱 버튼, 3D 틸트, 헤더 스크롤 상태, 패럴랙스.
 * prefers-reduced-motion 이면 모든 모션을 생략하고 즉시 최종 상태로 둔다.
 */

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initReveal() {
    const items = document.querySelectorAll('.reveal, .reveal-scale');
    if (!items.length) return;

    if (reduceMotion || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -8% 0px' }
    );

    items.forEach((el) => io.observe(el));
}

function animateCount(el) {
    const target = parseFloat(el.dataset.count || '0');
    const decimals = parseInt(el.dataset.decimals || '0', 10);
    const prefix = el.dataset.prefix || '';
    const suffix = el.dataset.suffix || '';
    const duration = 1100;
    const start = performance.now();

    const fmt = (v) =>
        prefix +
        v.toLocaleString('ko-KR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }) +
        suffix;

    if (reduceMotion) {
        el.textContent = fmt(target);
        return;
    }

    function tick(now) {
        const p = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
        el.textContent = fmt(target * eased);
        if (p < 1) requestAnimationFrame(tick);
        else el.textContent = fmt(target);
    }
    requestAnimationFrame(tick);
}

function initCounters() {
    const counters = document.querySelectorAll('[data-count]');
    if (!counters.length) return;

    if (!('IntersectionObserver' in window)) {
        counters.forEach(animateCount);
        return;
    }

    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCount(entry.target);
                    io.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.6 }
    );
    counters.forEach((el) => io.observe(el));
}

function initTilt() {
    if (reduceMotion) return;
    const cards = document.querySelectorAll('[data-tilt]');
    cards.forEach((card) => {
        const strength = parseFloat(card.dataset.tilt || '8');
        card.addEventListener('mousemove', (e) => {
            const r = card.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width - 0.5;
            const py = (e.clientY - r.top) / r.height - 0.5;
            card.style.transform = `perspective(900px) rotateY(${px * strength}deg) rotateX(${-py * strength}deg) translateZ(0)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
        });
    });
}

function initMagnetic() {
    if (reduceMotion) return;
    document.querySelectorAll('[data-magnetic]').forEach((el) => {
        el.addEventListener('mousemove', (e) => {
            const r = el.getBoundingClientRect();
            const x = e.clientX - r.left - r.width / 2;
            const y = e.clientY - r.top - r.height / 2;
            el.style.transform = `translate(${x * 0.18}px, ${y * 0.28}px)`;
        });
        el.addEventListener('mouseleave', () => {
            el.style.transform = '';
        });
    });
}

function initParallax() {
    if (reduceMotion) return;
    const layers = document.querySelectorAll('[data-parallax]');
    if (!layers.length) return;
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            const y = window.scrollY;
            layers.forEach((el) => {
                const speed = parseFloat(el.dataset.parallax || '0.2');
                el.style.transform = `translate3d(0, ${y * speed}px, 0)`;
            });
            ticking = false;
        });
    });
}

function initHeaderScroll() {
    const header = document.querySelector('[data-site-header]');
    if (!header) return;
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}

export function initInteractions() {
    initReveal();
    initCounters();
    initTilt();
    initMagnetic();
    initParallax();
    initHeaderScroll();
}

// DOM 준비 시 자동 실행
if (document.readyState !== 'loading') {
    initInteractions();
} else {
    document.addEventListener('DOMContentLoaded', initInteractions);
}
