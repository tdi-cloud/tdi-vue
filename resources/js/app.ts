import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import ConfirmDialogHost from './components/ConfirmDialogHost.vue';
import NetworkStatusBanner from './components/NetworkStatusBanner.vue';
import SessionExpiredDialog from './components/SessionExpiredDialog.vue';
import { Toaster } from './components/ui/toast';
import { initializeTheme } from './composables/useAppearance';
import { useFlash } from './composables/useFlash';
import { installNetworkMonitoring } from './composables/useNetworkStatus';
import { installHttpErrorHandling } from './lib/http';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({
            setup() {
                // Flash toasts live at the root so they also show on auth/public pages and survive layout changes.
                useFlash();

                return () => [h(App, props), h(ConfirmDialogHost), h(Toaster), h(NetworkStatusBanner), h(SessionExpiredDialog)];
            },
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#006aff',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Friendly toasts for failed axios requests (network, timeout, 4xx, 5xx)...
installHttpErrorHandling();

// Offline banner, blocked submits while offline, and slow-connection detection...
installNetworkMonitoring();
