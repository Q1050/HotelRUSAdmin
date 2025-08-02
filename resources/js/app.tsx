import '../css/style.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { TooltipProvider } from './components/ui/tooltip';
import { Toaster as Sonner } from './components/ui/sonner';
import { Toaster } from "@/components/ui/toaster";

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        if (import.meta.env.SSR) {
            hydrateRoot(el, <App {...props} />);
            return;
        }

        createRoot(el).render(
        <TooltipProvider>
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
