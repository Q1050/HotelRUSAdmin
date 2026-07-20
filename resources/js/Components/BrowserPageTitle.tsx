import { router } from '@inertiajs/react';
import { useEffect } from 'react';

function updateBrowserTitle() {
    window.requestAnimationFrame(() => {
        const heading = document.querySelector<HTMLElement>('main h1');

        if (!heading?.textContent?.trim()) {
            return;
        }

        const headingText = heading.textContent.trim();
        const header = heading.closest('header');
        const subheading = header?.querySelector<HTMLElement>('p');
        const subheadingText = subheading?.textContent?.trim();

        document.title = subheadingText
            ? `${headingText}: ${subheadingText}`
            : headingText;
    });
}

export default function BrowserPageTitle() {
    useEffect(() => {
        updateBrowserTitle();

        const removeNavigateListener = router.on('navigate', updateBrowserTitle);
        const observer = new MutationObserver(updateBrowserTitle);

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });

        return () => {
            removeNavigateListener();
            observer.disconnect();
        };
    }, []);

    return null;
}
