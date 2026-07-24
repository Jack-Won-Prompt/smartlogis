/*
 * Chart.js 공통 테마 (DESIGN.md §5.5). 브랜드 파랑 + 시맨틱 팔레트.
 * 사용법:
 *   import { registerChartTheme, brandPalette, money } from './charts/theme.js';
 *   registerChartTheme(Chart);
 * Chart.js 자체는 각 대시보드 뷰에서 CDN/npm 으로 로드한다(여기서는 테마만 정의).
 */

export const brandPalette = ['#2D6AE0', '#1B3A8F', '#7A5AC8', '#B4700A', '#C2362B', '#5E7080'];

export const semantic = {
    ok: '#1E8A5B',
    warn: '#B4700A',
    crit: '#C2362B',
    info: '#2563A8',
    hold: '#6B7A88',
    brand: '#2D6AE0',
};

const LINE = '#DDE4EA';
const INK_500 = '#5A6E82';
const INK_900 = '#0E1A2B';

/** ₩1,234,000 포맷 */
export function money(v) {
    return '₩' + Math.round(Number(v) || 0).toLocaleString('ko-KR');
}

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function registerChartTheme(Chart) {
    if (!Chart) return;

    Chart.defaults.font.family =
        '"Pretendard Variable", Pretendard, system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = INK_500;
    Chart.defaults.borderColor = LINE;
    Chart.defaults.animation = reduceMotion ? false : { duration: 400, easing: 'easeOutQuart' };

    Chart.defaults.plugins.legend.position = 'top';
    Chart.defaults.plugins.legend.align = 'end';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.padding = 16;

    Chart.defaults.plugins.tooltip.backgroundColor = INK_900;
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.titleColor = '#fff';
    Chart.defaults.plugins.tooltip.bodyColor = '#E7EDF3';
    Chart.defaults.plugins.tooltip.boxPadding = 6;
}

/** 막대 차트 공통 옵션 */
export function barOptions(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        borderRadius: 4,
        scales: {
            x: { grid: { display: false } },
            y: { grid: { color: LINE }, border: { display: false }, beginAtZero: true },
        },
        ...extra,
    };
}

/** 라인 차트 공통 옵션 (fill 8% 단색, 점 숨김) */
export function lineOptions(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        elements: {
            line: { borderWidth: 2, tension: 0.35 },
            point: { radius: 0, hoverRadius: 4 },
        },
        scales: {
            x: { grid: { display: false } },
            y: { grid: { color: LINE }, border: { display: false }, beginAtZero: true },
        },
        ...extra,
    };
}

/** 도넛 차트 공통 옵션 */
export function doughnutOptions(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        ...extra,
    };
}
