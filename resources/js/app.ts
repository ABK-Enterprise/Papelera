import { createInertiaApp } from '@inertiajs/svelte';
import AppLayout from '@/layouts/AppLayout.svelte';
import AuthLayout from '@/layouts/AuthLayout.svelte';
import SettingsLayout from '@/layouts/settings/Layout.svelte';
import { initializeFlashToast } from '@/lib/flash-toast';
import { initializeTheme } from '@/lib/theme.svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome' || name === 'auth/AccessDenied' || name.startsWith('errors/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
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
