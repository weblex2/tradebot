import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                neon: {
                    green: '#00ff87',
                    red:   '#ff3d71',
                    blue:  '#00b4d8',
                    gold:  '#ffd60a',
                },
                glass: {
                    DEFAULT: 'rgba(255, 255, 255, 0.07)',
                    light:   'rgba(255, 255, 255, 0.12)',
                    border:  'rgba(255, 255, 255, 0.15)',
                },
            },
            backgroundImage: {
                'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
            },
            backdropBlur: {
                xs: '2px',
            },
        },
    },
    plugins: [forms],
};
