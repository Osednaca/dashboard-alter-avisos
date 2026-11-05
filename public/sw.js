/* Bump the owned cache version when changing the public offline shell. */
const CACHE_PREFIX = 'alter-public-pwa-';
const CACHE_NAME = `${CACHE_PREFIX}v2`;
const OFFLINE_URL = '/offline.html';
const PUBLIC_FILES = new Set([
    OFFLINE_URL, '/brand/alter-logo-20261003.png', '/icons/alter-192-20261003.png',
    '/icons/alter-512-20261003.png', '/icons/alter-maskable-512-20261003.png',
]);
const HASHED_BUILD_ASSET = /^\/build\/assets\/[A-Za-z0-9_-]+-[A-Za-z0-9_-]{8,}\.(?:js|css|woff2?|png|jpe?g|svg|webp)$/;

function publicAsset(url) {
    return url.origin === self.location.origin
        && !url.search
        && (PUBLIC_FILES.has(url.pathname) || HASHED_BUILD_ASSET.test(url.pathname));
}

function safeResponse(response, requestedUrl) {
    if (!response.ok || response.status !== 200 || response.redirected || !['basic', 'default'].includes(response.type)) return false;
    if (response.url && (!publicAsset(new URL(response.url)) || response.url !== requestedUrl.href)) return false;
    if (/(?:^|,)\s*(?:private|no-store)(?:\s|,|=|$)/i.test(response.headers.get('Cache-Control') || '')) return false;
    if (/(?:^|,)\s*(?:cookie|authorization|\*)(?:\s|,|$)/i.test(response.headers.get('Vary') || '')) return false;
    const type = (response.headers.get('Content-Type') || '').split(';')[0].trim().toLowerCase();
    if (requestedUrl.pathname === OFFLINE_URL) return type === 'text/html';
    return /^(?:image\/|font\/)/.test(type)
        || ['text/css', 'text/javascript', 'application/javascript', 'application/font-woff', 'application/vnd.ms-fontobject'].includes(type);
}

async function publicResponse(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);
    if (cached) return cached;
    // Static assets must never be fetched using account cookies or authorization.
    const response = await fetch(new Request(request, { credentials: 'omit' }));
    if (safeResponse(response, new URL(request.url))) await cache.put(request, response.clone());
    return response;
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);
        for (const path of PUBLIC_FILES) {
            const request = new Request(new URL(path, self.location.origin), { credentials: 'omit' });
            const response = await fetch(request);
            if (!safeResponse(response, new URL(request.url))) throw new Error('Invalid public offline asset');
            await cache.put(request, response);
        }
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const name of await caches.keys()) {
            if (name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME) await caches.delete(name);
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (request.mode === 'navigate') {
        // Never persist any page, including login, redirects and authenticated HTML.
        event.respondWith(fetch(new Request(request, { cache: 'no-store' })).catch(async () => {
            const cache = await caches.open(CACHE_NAME);
            return await cache.match(OFFLINE_URL)
                || new Response('Sin conexión. Vuelve a intentarlo.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
        }));
    } else if (!request.headers.has('X-Inertia') && !request.headers.has('Authorization') && publicAsset(url)) {
        event.respondWith(publicResponse(request));
    }
});
