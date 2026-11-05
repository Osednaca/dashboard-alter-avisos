import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import type { PageProps } from '@/Types';
import { registerDashboardPwa } from '@/pwa';

registerDashboardPwa();

let appName = 'Alter';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        appName = (props.initialPage.props as unknown as PageProps).app.name;
        createRoot(el).render(
            <>
                <App {...props} />
                <Toaster
                    theme="dark"
                    position="bottom-right"
                    closeButton
                    toastOptions={{
                        style: {
                            background: '#0d1c2b',
                            border: '1px solid #1b3042',
                            color: '#f5f7fa',
                        },
                    }}
                />
            </>,
        );
    },
    progress: {
        color: '#ffc83d',
        showSpinner: false,
    },
});
