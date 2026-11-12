import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

// The build may run without the production .env (e.g. in CI), so prefer the
// app name the server shares with every page (config('app.name')).
let appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const nameFrom = (props: Record<string, unknown>) =>
    typeof props.name === 'string' && props.name !== '' ? props.name : null;

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup: ({ App, props, plugin }) => {
                appName = nameFrom(props.initialPage.props) ?? appName;

                return createSSRApp({ render: () => h(App, props) }).use(
                    plugin,
                );
            },
        }),
    { cluster: true },
);
