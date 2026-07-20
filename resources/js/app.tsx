import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { TooltipProvider } from './components/ui/tooltip';
import { Toaster as Sonner } from './components/ui/sonner';
import { Toaster } from "@/components/ui/toaster";
import BrowserPageTitle from './components/BrowserPageTitle';

const appName = import.meta.env.VITE_APP_NAME || 'HotelCheckin';

createInertiaApp({
    title: (title) => title || appName,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        if (typeof (globalThis as any).window === 'undefined') {
            (globalThis as any).window = globalThis;
        }
        (globalThis as any).__currency = (props.initialPage.props as any).hotel?.currency || 'USD';
        if (import.meta.env.SSR) {
            hydrateRoot(el, <App {...props} />);
            return;
        }

        createRoot(el).render(
        <TooltipProvider>
          <BrowserPageTitle />
          <Toaster />
          <Sonner />
          <App {...props} />
        </TooltipProvider>
      );
    },
    progress: {
        color: '#4B5563',
    },
});
