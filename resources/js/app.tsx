import { createInertiaApp, router } from '@inertiajs/react';
import { ConfirmProvider } from '@/components/confirm-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import {
    configureAnalytics,
    identifyOnce,
    trackPageview,
} from '@/lib/analytics';
import { installHttpErrorHandlers } from '@/lib/http-errors';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const inBrowser = typeof window !== 'undefined';

// One entry renders in the browser and, through @inertiajs/vite, on the server.
// The plugin resolves ./pages and wraps this call with the SSR bootstrap.
void createInertiaApp({
    // The progress bar's injected style carries the CSP nonce the layout publishes
    nonce: inBrowser
        ? document.querySelector<HTMLMetaElement>('meta[property=csp-nonce]')
              ?.nonce
        : undefined,
    title: (title) => (title ? `${title} - ${appName}` : appName),
    strictMode: true,
    withApp: (app) => (
        <TooltipProvider delay={0}>
            <ConfirmProvider>
                {app}
                <Toaster />
            </ConfirmProvider>
        </TooltipProvider>
    ),
    progress: { color: '#4B5563' },
});

if (inBrowser) {
    // This will set light / dark mode on load...
    initializeTheme();

    // Every failed request past this point says something a person can act on.
    installHttpErrorHandlers();

    // No provider ships by default — analytics is a no-op until one is wired
    // here. Do Not Track still silences everything if that ever changes.
    router.on('navigate', (event) => {
        const props = event.detail.page.props;

        configureAnalytics(null, Boolean(props.doNotTrack));
        trackPageview(event.detail.page.url);

        if (props.auth.user) {
            identifyOnce(props.auth.user.id, props.organization?.id ?? null);
        }
    });
}
