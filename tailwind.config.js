import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
        './index.html',
        './src/**/*.{js,ts,jsx,tsx}',
    ],
    darkMode: 'class', // Enable dark mode support
    theme: {
        fontFamily: {
            sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            satoshi: ['Satoshi', 'sans-serif'], // TailAdmin font
        },
        screens: {
            '2xsm': '375px',
            xsm: '425px',
            '3xl': '2000px',
            ...defaultTheme.screens, // Retain default screens
        },
        extend: {
            colors: {
                current: 'currentColor',
                transparent: 'transparent',
                white: '#FFFFFF',
                black: '#1C2434',
                red: '#FB5454',
                'black-2': '#010101',
                body: '#64748B',
                bodydark: '#AEB7C0',
                bodydark1: '#DEE4EE',
                bodydark2: '#8A99AF',
                primary: '#3C50E0',
                secondary: '#80CAEE',
                stroke: '#E2E8F0',
                gray: '#EFF4FB',
                graydark: '#333A48',
                'gray-2': '#F7F9FC',
                'gray-3': '#FAFAFA',
                whiten: '#F1F5F9',
                whiter: '#F5F7FD',
                boxdark: '#24303F',
                'boxdark-2': '#1A222C',
                strokedark: '#2E3A47',
                'form-strokedark': '#3d4d60',
                'form-input': '#1d2a39',
                success: '#219653',
                danger: '#D34053',
                warning: '#FFA70B',
                'meta-1': '#DC3545',
                'meta-2': '#EFF2F7',
                'meta-3': '#10B981',
                'meta-4': '#313D4A',
                'meta-5': '#259AE6',
                'meta-6': '#FFBA00',
                'meta-7': '#FF6766',
                'meta-8': '#F0950C',
                'meta-9': '#E5E7EB',
                'meta-10': '#0FADCF',
                boxdark: '#24303F',
                bodydark: '#AEB7C0',
            },
            fontSize: {
                'title-xxl': ['44px', '55px'],
                'title-xl': ['36px', '45px'],
                'title-lg': ['28px', '35px'],
                'title-md': ['24px', '30px'],
                'title-sm': ['20px', '26px'],
                'title-xsm': ['18px', '24px'],
            },
            spacing: {
                4.5: '1.125rem',
                5.5: '1.375rem',
                6.5: '1.625rem',
                7.5: '1.875rem',
                8.5: '2.125rem',
                9.5: '2.375rem',
                10.5: '2.625rem',
                11: '2.75rem',
                11.5: '2.875rem',
                12.5: '3.125rem',
                13: '3.25rem',
                13.5: '3.375rem',
                14: '3.5rem',
                14.5: '3.625rem',
                15: '3.75rem',
                15.5: '3.875rem',
                16: '4rem',
            },

            width: {
                '100': '100%', // Custom class for 100% width
            },

            boxShadow: {
                default: '0px 8px 13px -3px rgba(0, 0, 0, 0.07)',
                8: '1px 0 0 #313D4A, -1px 0 0 #313D4A, 0 1px 0 #313D4A, 0 -1px 0 #313D4A, 0 3px 13px rgba(0, 0, 0, 0.08)',
                7: '-5px 0 0 #EFF4FB, 5px 0 0 #EFF4FB',
            },
            zIndex: {
                999999: '999999',
                99999: '99999',
                9999: '9999',
                999: '999',
                99: '99',
                9: '9',
                1: '1',
            },
            animation: {
                linspin: 'linspin 1568.2353ms linear infinite',
                easespin: 'easespin 5332ms cubic-bezier(0.4, 0, 0.2, 1) infinite both',
                rotating: 'rotating 30s linear infinite',
            },
            keyframes: {
                linspin: {
                    '100%': { transform: 'rotate(360deg)' },
                },
                easespin: {
                    '12.5%': { transform: 'rotate(135deg)' },
                    '25%': { transform: 'rotate(270deg)' },
                    '37.5%': { transform: 'rotate(405deg)' },
                    '100%': { transform: 'rotate(1080deg)' },
                },
                rotating: {
                    '0%, 100%': { transform: 'rotate(360deg)' },
                    '50%': { transform: 'rotate(0deg)' },
                },
            },
        },
    },
    plugins: [
        forms, // Default Inertia form plugin
        // Add TailAdmin plugins here if needed
    ],
};
