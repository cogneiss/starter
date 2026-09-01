import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
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

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent<ComponentType>(
            `./pages/${name}.tsx`,
            import.meta.glob<ComponentType>('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <TooltipProvider delay={0}>
                    <ConfirmProvider>
                        <App {...props} />
                        <Toaster />
                    </ConfirmProvider>
                </TooltipProvider>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

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

    if (props.auth?.user) {
        identifyOnce(props.auth.user.id, props.organization?.id ?? null);
    }
});
