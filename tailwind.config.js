import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                // 본문·디스플레이 모두 Pretendard 통일, 데이터(수량/코드/금액)만 모노
                sans: ['"Pretendard Variable"', 'Pretendard', ...defaultTheme.fontFamily.sans],
                display: ['"Pretendard Variable"', 'Pretendard', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },

            colors: {
                // 브랜드 파랑 — logo_header.png 에서 추출 (navy "smart" → bright "logis")
                brand: {
                    50: '#EEF4FF',
                    100: '#DCE8FD',
                    200: '#BFD5FB',
                    300: '#8FB6F8',
                    400: '#5B90F3',
                    500: '#2D6AE0', // logis 밝은 파랑 (주 강조)
                    600: '#2551C4',
                    700: '#1B3A8F', // smart 네이비
                    800: '#162E73',
                    900: '#122560',
                    950: '#0B1740',
                },
                // 업무 신호등 (DESIGN.md §1.3) — 앱 화면에서 상태 표현용
                ok: { 600: '#1E8A5B', 100: '#DEF3E8' },
                warn: { 600: '#B4700A', 100: '#FBEFD8' },
                crit: { 600: '#C2362B', 100: '#FBE4E1' },
                info: { 600: '#2563A8', 100: '#E0ECF9' },
                hold: { 600: '#6B7A88', 100: '#EDF1F4' },
                ink: {
                    900: '#0E1A2B',
                    700: '#2C3E52',
                    500: '#5A6E82',
                    300: '#93A4B6',
                },
                // 표면/구분선 (DESIGN.md §1.1 — 청회색 계열)
                surface: {
                    0: '#F6F8FA',
                    1: '#FFFFFF',
                    2: '#EEF2F5',
                },
                line: {
                    DEFAULT: '#DDE4EA',
                    strong: '#B9C4CE',
                },
                // 관제탑 사이드바 고정 네이비 (DESIGN.md §3)
                navy: {
                    DEFAULT: '#101B26',
                    700: '#1B2A38',
                    600: '#24384C',
                },
            },

            borderRadius: {
                xl: '0.75rem',
                '2xl': '1.25rem',
                '3xl': '1.75rem',
            },

            boxShadow: {
                soft: '0 1px 2px rgba(16,26,43,0.04), 0 8px 24px -12px rgba(16,26,43,0.12)',
                lift: '0 20px 50px -20px rgba(27,58,143,0.35)',
                glow: '0 0 0 1px rgba(45,106,224,0.25), 0 12px 40px -12px rgba(45,106,224,0.45)',
            },

            backgroundImage: {
                'brand-gradient': 'linear-gradient(120deg, #122560 0%, #1B3A8F 45%, #2D6AE0 100%)',
                'brand-sheen': 'linear-gradient(120deg, #1B3A8F 0%, #2D6AE0 55%, #5B90F3 100%)',
                'grid-lines':
                    'linear-gradient(rgba(255,255,255,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.06) 1px, transparent 1px)',
                'radial-glow':
                    'radial-gradient(60% 60% at 50% 0%, rgba(45,106,224,0.25) 0%, rgba(45,106,224,0) 70%)',
            },

            backgroundSize: {
                grid: '40px 40px',
            },

            transitionTimingFunction: {
                brand: 'cubic-bezier(0.2, 0, 0, 1)',
            },

            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-10px)' },
                },
                'float-slow': {
                    '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
                    '50%': { transform: 'translateY(-16px) rotate(2deg)' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                'gradient-pan': {
                    '0%, 100%': { backgroundPosition: '0% 50%' },
                    '50%': { backgroundPosition: '100% 50%' },
                },
                'pulse-ring': {
                    '0%': { transform: 'scale(0.9)', opacity: '0.7' },
                    '70%': { transform: 'scale(1.6)', opacity: '0' },
                    '100%': { opacity: '0' },
                },
                marquee: {
                    '0%': { transform: 'translateX(0)' },
                    '100%': { transform: 'translateX(-50%)' },
                },
                'flow-dash': {
                    to: { 'stroke-dashoffset': '-24' },
                },
            },

            animation: {
                'fade-up': 'fade-up 0.7s cubic-bezier(0.2,0,0,1) both',
                'fade-in': 'fade-in 0.8s ease both',
                float: 'float 6s ease-in-out infinite',
                'float-slow': 'float-slow 9s ease-in-out infinite',
                shimmer: 'shimmer 2.5s linear infinite',
                'gradient-pan': 'gradient-pan 8s ease infinite',
                'pulse-ring': 'pulse-ring 2.4s cubic-bezier(0.2,0,0,1) infinite',
                marquee: 'marquee 28s linear infinite',
                'flow-dash': 'flow-dash 1s linear infinite',
            },
        },
    },

    plugins: [forms],
};
