/** Only public resources are cached; the dashboard requires a live connection. */
export function registerDashboardPwa(): void {
    if (!import.meta.env.PROD || !window.isSecureContext || !('serviceWorker' in navigator)) return;

    const register = (): void => {
        void navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' })
            .catch(() => { /* Installation failure must not block dashboard access. */ });
    };

    if (document.readyState === 'complete') register();
    else window.addEventListener('load', register, { once: true });
}
