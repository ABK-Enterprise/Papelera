import { createInertiaApp, router } from '@inertiajs/svelte';
import AppLayout from '@/layouts/AppLayout.svelte';
import AuthLayout from '@/layouts/AuthLayout.svelte';
import SettingsLayout from '@/layouts/settings/Layout.svelte';
import { initializeFlashToast } from '@/lib/flash-toast';
import { initializeTheme } from '@/lib/theme.svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Resilient Authentication Interceptor: Auto-retry once on 419 Token Mismatch during login
let isRetryingLogin = false;

router.on('httpException', (event) => {
    const response = (event.detail as any)?.response;
    if (response?.status === 419) {
        const path = typeof window !== 'undefined' ? window.location.pathname : '';
        if ((path === '/login' || path.endsWith('/login')) && !isRetryingLogin) {
            isRetryingLogin = true;
            event.preventDefault();

            // Give browser a brief tick to commit the fresh XSRF-TOKEN cookie sent with the 419 response
            setTimeout(() => {
                const loginForm = document.querySelector('form') as HTMLFormElement | null;
                if (loginForm && (window.location.pathname === '/login' || window.location.pathname.endsWith('/login'))) {
                    loginForm.requestSubmit();
                } else {
                    router.reload();
                }
                setTimeout(() => {
                    isRetryingLogin = false;
                }, 3000);
            }, 60);

            return false;
        }
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome' || name === 'auth/AccessDenied' || name.startsWith('errors/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/') ||
                 name === 'tenant/zones/Index' ||
                 name === 'tenant/warehouses/Index' ||
                 name.startsWith('tenant/settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Suppress unhandled exceptions originating from browser DevTools / Soft Navigation heuristics
if (typeof window !== 'undefined') {
    window.addEventListener('error', (event) => {
        if (event.message?.includes("reading 'startTime'")) {
            event.preventDefault();
        }
    });

    window.addEventListener('unhandledrejection', (event) => {
        if (event.reason?.message?.includes("reading 'startTime'")) {
            event.preventDefault();
        }
    });
}
