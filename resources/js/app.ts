import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

// The build may run without the production .env (e.g. in CI), so prefer the
// app name the server shares with every page (config('app.name')).
let appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const nameFrom = (props: Record<string, unknown>) =>
    typeof props.name === 'string' && props.name !== '' ? props.name : null;

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        appName = nameFrom(props.initialPage.props) ?? appName;
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
