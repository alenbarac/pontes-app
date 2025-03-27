/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx,ts,tsx}',
        './resources/css/**/*.css',       // important if you use @apply in CSS
        './index.html',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
