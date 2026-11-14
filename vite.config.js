import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Builds the workbench's Inertia pages for developing the package's Vue components.
export default defineConfig({
    plugins: [
        laravel({
            input: 'workbench/resources/js/app.js',
            // Read by WorkbenchServiceProvider so @vite finds the dev server.
            hotFile: 'workbench/hot',
            refresh: ['workbench/resources/views/**', 'workbench/routes/**'],
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],
});
