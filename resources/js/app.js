import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { installModalEnterSubmit } from './modalEnterSubmit';

const appName = 'EN-IDP';

const legacyMockKeys = [
    'mock-employee-idp-gaps',
    'mock-employee-idp-activities',
    'mock-employee-idp-forms',
    'mock-employee-idp-goals',
    'mock-employee-progress-forms',
];

if (typeof window !== 'undefined') {
    legacyMockKeys.forEach((key) => window.localStorage.removeItem(key));
    installModalEnterSubmit();
}

createInertiaApp({
    title: (title) => `${title || 'หน้าหลัก'} — ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#C7432B',
    },
});
