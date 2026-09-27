import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

theme: {
        extend: {
            fontSize: {
                xs: ['0.8125rem', { lineHeight: '1.25rem' }],
                sm: ['0.9375rem', { lineHeight: '1.5rem' }],
            },
            colors: {
                "background": "#F8F9FA",
                "surface": "#FFFFFF",
                "border": "#D1D5DB",
                "on-background": "#111827",
                "primary-fixed-dim": "#D8B8BA",
                "secondary-fixed-dim": "#D1D5DB",
                "on-primary": "#ffffff",
                "primary": "#75292D",
                "primary-hover": "#5E2024",
                "primary-active": "#4A191C",
                "primary-soft": "#F8EEEE",
                "success": "#166534",
                "success-soft": "#DCFCE7",
                "warning": "#92400E",
                "warning-soft": "#FEF3C7",
                "danger": "#B91C1C",
                "danger-soft": "#FEE2E2",
                "info": "#1D4ED8",
                "info-soft": "#DBEAFE",
                // ... เพิ่มให้ครบตามก้อนสีที่คุณส่งมา
            },
            // ... เพิ่ม fontFamily และ fontSize ด้วยถ้าต้องการให้เป๊ะ 100%
        }
    },

    plugins: [forms],
};
