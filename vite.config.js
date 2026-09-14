import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // The visual editor is its own bundle: it is a full-screen
                // app that no other admin screen loads.
                'resources/css/builder.css',
                'resources/js/builder/index.js',
                // Front-end block styles, loaded by any page using a layout.
                'resources/css/builder-front.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
