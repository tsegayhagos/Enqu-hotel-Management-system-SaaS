import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#f0fbfb',
                    100: '#d5f5f4',
                    200: '#ace9e7',
                    300: '#74d6d4',
                    400: '#3cbbba',
                    500: '#0ea5a4', // Enqu Vibrant Teal
                    600: '#0b8686',
                    700: '#006378',
                    800: '#004758', // Enqu Deep Teal (Primary Brand)
                    900: '#003542',
                    950: '#00212a',
                    DEFAULT: '#004758',
                },
                accent: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',
                    500: '#f59e0b', // Enqu Warm Gold / Amber
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                    DEFAULT: '#f59e0b',
                },
                enquDark: {
                    800: '#17232b',
                    900: '#0f171c', // Enqu Deep Dark Background
                    950: '#090e12',
                    DEFAULT: '#0f171c',
                },
            },
        },
    },

    plugins: [forms],
};
