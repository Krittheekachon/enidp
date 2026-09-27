import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        hmr: {
            host: '127.0.0.1',
        },
        watch: {
            ignored: [
                '**/vendor/**',
                '**/vendor */**',
                '**/vendor.broken-*/**',
                '**/node_modules/**',
                '**/node_modules.broken-*/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
                '**/public/build/**',
            ],
        },
    },
    esbuild: {
        jsx: 'transform',
        jsxFactory: 'h',
        jsxFragment: 'VueFragment',
        jsxInject: "import { h, Fragment as VueFragment } from 'vue'",
    },
    optimizeDeps: {
        include: ['qs'],
        noDiscovery: true,
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
