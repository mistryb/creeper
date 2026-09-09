import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Pages that bring their own chrome and must not be wrapped in `AppLayout`.
 * The marketing pages wrap themselves in `MarketingLayout`, which is written
 * for a visitor with no account — the app sidebar navigates a signed-in
 * install and has nothing to offer them.
 */
const SELF_LAYOUT_PAGES = ['welcome', 'deploy', 'design'];

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case SELF_LAYOUT_PAGES.includes(name):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    // Ribbon green, so even the loading bar is in the palette.
    progress: {
        color: '#1c6547',
    },
});
