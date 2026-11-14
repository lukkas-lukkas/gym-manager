import { createInertiaApp, router } from '@inertiajs/vue3';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    pages: './pages',
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});

/**
 * The menu links prefetch their pages and keep them cached for a while. A change saved on one page usually affects
 * others too (an enrollment creates a revenue, a payment changes the balance and the reports), and Inertia only
 * drops the cache of the page it redirects to. So after any request that may have changed data, drop every
 * prefetched page: the next visit asks the server again instead of showing data from before the change.
 */
router.on('finish', (event) => {
    if (event.detail.visit.method !== 'get' && event.detail.visit.completed) {
        router.flushAll();
    }
});
