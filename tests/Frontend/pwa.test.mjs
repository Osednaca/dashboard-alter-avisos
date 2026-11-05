import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '../..');
const origin = 'https://alter.test';
const workerSource = readFileSync(resolve(root, 'public/sw.js'), 'utf8');
const offlineHtml = readFileSync(resolve(root, 'public/offline.html'), 'utf8');

function workerHarness() {
    const listeners = new Map();
    const stores = new Map();
    const requests = [];
    let online = true;
    let account = 'owner';
    const overrides = new Map();
    const key = (input) => new URL(typeof input === 'string' ? input : input.url, origin).href;
    const caches = {
        async keys() { return [...stores.keys()]; },
        async delete(name) { return stores.delete(name); },
        async open(name) {
            if (!stores.has(name)) stores.set(name, new Map());
            const entries = stores.get(name);
            return {
                async match(input) { return entries.get(key(input))?.clone(); },
                async put(input, response) { entries.set(key(input), response.clone()); },
            };
        },
    };
    async function fetch(request) {
        requests.push(request);
        if (!online) throw new TypeError('Network unavailable');
        const url = new URL(request.url);
        const options = overrides.get(url.pathname) ?? {};
        const type = url.pathname === '/offline.html' ? 'text/html'
            : url.pathname.endsWith('.js') ? 'application/javascript'
                : url.pathname.endsWith('.css') ? 'text/css' : 'image/png';
        const body = url.pathname === '/offline.html' ? offlineHtml
            : /^\/(?:admin|business)\//.test(url.pathname) ? `private dashboard for ${account}` : 'public fixture';
        const response = new Response(body, {
            status: options.status ?? 200,
            headers: { 'Content-Type': type, ...options.headers },
        });
        Object.defineProperty(response, 'url', { value: options.url ?? url.href });
        Object.defineProperty(response, 'redirected', { value: options.redirected ?? false });
        return response;
    }
    const self = {
        location: new URL(origin),
        addEventListener: (name, listener) => listeners.set(name, listener),
        skipWaiting: async () => {},
        clients: { claim: async () => {} },
    };
    vm.runInNewContext(workerSource, { self, caches, fetch, URL, Request, Response, Set }, { filename: 'public/sw.js' });
    return {
        stores, requests, overrides, caches,
        setOnline(value) { online = value; },
        setAccount(value) { account = value; },
        async lifecycle(name) {
            let completion;
            listeners.get(name)({ waitUntil: (promise) => { completion = promise; } });
            await completion;
        },
        dispatch(path, options = {}) {
            const { navigate = false, ...requestOptions } = options;
            const request = new Request(new URL(path, origin), requestOptions);
            if (navigate) Object.defineProperty(request, 'mode', { value: 'navigate' });
            let response;
            listeners.get('fetch')({ request, respondWith: (promise) => { response = promise; } });
            return { handled: response !== undefined, response };
        },
        storedUrls() { return [...stores.values()].flatMap((entries) => [...entries.keys()]); },
    };
}

test('installation caches only the public shell; activation removes only obsolete owned versions', async () => {
    const harness = workerHarness();
    await harness.caches.open('alter-public-pwa-obsolete');
    await harness.caches.open('another-app-cache');
    await harness.lifecycle('install');
    assert.deepEqual(harness.storedUrls().map((url) => new URL(url).pathname).sort(), [
        '/brand/alter-logo-20261003.png', '/icons/alter-192-20261003.png', '/icons/alter-512-20261003.png',
        '/icons/alter-maskable-512-20261003.png', '/offline.html',
    ]);
    assert.ok(harness.requests.every((request) => request.credentials === 'omit'));
    await harness.lifecycle('activate');
    assert.deepEqual([...harness.stores.keys()].sort(), ['alter-public-pwa-v2', 'another-app-cache']);
});

test('public hashed assets work offline after one cookie-free network fetch', async () => {
    const harness = workerHarness();
    const asset = '/build/assets/app-AbCd1234.js';
    assert.equal(await (await harness.dispatch(asset).response).text(), 'public fixture');
    assert.equal(harness.requests.at(-1).credentials, 'omit');
    harness.setOnline(false);
    assert.equal(await (await harness.dispatch(asset).response).text(), 'public fixture');
    assert.equal(harness.requests.length, 1);
});

test('private, no-store, user-varying, redirected, HTML and JSON asset responses are never persisted', async () => {
    const cases = [
        { headers: { 'Cache-Control': 'public, no-store' } },
        { headers: { 'Cache-Control': 'private="Set-Cookie", max-age=60' } },
        { headers: { Vary: 'Accept-Encoding, Cookie' } },
        { headers: { Vary: 'Authorization' } },
        { headers: { Vary: '*' } },
        { headers: { 'Content-Type': 'application/json' } },
        { headers: { 'Content-Type': 'text/html' } },
        { redirected: true },
        { url: `${origin}/login` },
        { status: 403 },
    ];
    for (const options of cases) {
        const harness = workerHarness();
        const asset = '/build/assets/app-AbCd1234.js';
        harness.overrides.set(asset, options);
        await harness.dispatch(asset).response;
        assert.deepEqual(harness.storedUrls(), [], JSON.stringify(options));
        harness.setOnline(false);
        await assert.rejects(harness.dispatch(asset).response, /Network unavailable/);
    }
});

test('API, private media, Inertia, authentication, queries, cross-origin and mutations bypass the cache', () => {
    const harness = workerHarness();
    const requests = [
        ['/admin/dashboard'], ['/business/dashboard'], ['/api/v1/devices/manifest'],
        ['/storage/private/secret.jpg'], ['/login'], ['/logout'], ['/build/assets/app.js'],
        ['/build/assets/app-AbCd1234.js?user=1'], ['https://other.test/icons/alter-192-20261003.png'],
        ['/build/assets/app-AbCd1234.js', { headers: { 'X-Inertia': 'true' } }],
        ['/build/assets/app-AbCd1234.js', { headers: { Authorization: 'Bearer fixture' } }],
        ['/icons/alter-192-20261003.png', { method: 'POST', body: 'private fixture' }],
    ];
    for (const [path, options] of requests) assert.equal(harness.dispatch(path, options).handled, false, path);
    assert.deepEqual(harness.storedUrls(), []);
    assert.equal(harness.requests.length, 0);
});

test('navigation never replays authenticated pages across account changes or while offline', async () => {
    const harness = workerHarness();
    await harness.lifecycle('install');
    const publicUrls = harness.storedUrls();
    assert.equal(await (await harness.dispatch('/admin/dashboard', { navigate: true }).response).text(), 'private dashboard for owner');
    harness.setAccount('business');
    assert.equal(await (await harness.dispatch('/business/dashboard', { navigate: true }).response).text(), 'private dashboard for business');
    assert.deepEqual(harness.storedUrls(), publicUrls);
    assert.ok(harness.requests.slice(5).every((request) => request.cache === 'no-store'));
    harness.setOnline(false);
    for (const path of ['/admin/dashboard', '/business/dashboard', '/login', '/logout']) {
        const response = await harness.dispatch(path, { navigate: true }).response;
        const html = await response.text();
        assert.equal(html, offlineHtml);
        assert.doesNotMatch(html, /private dashboard|owner|business/);
    }
    assert.deepEqual(harness.storedUrls(), publicUrls);
});

test('failed initial offline preparation cannot claim a usable installed shell', async () => {
    const harness = workerHarness();
    harness.overrides.set('/offline.html', { headers: { 'Cache-Control': 'no-store' } });
    await assert.rejects(harness.lifecycle('install'), /Invalid public offline asset/);
    harness.setOnline(false);
    const response = await harness.dispatch('/', { navigate: true }).response;
    assert.equal(response.status, 503);
    assert.match(await response.text(), /Sin conexión/);
});

test('manifest and actual PNG icons provide a consistent standalone install identity', () => {
    const manifest = JSON.parse(readFileSync(resolve(root, 'public/manifest.webmanifest'), 'utf8'));
    assert.equal(manifest.id, '/');
    assert.equal(manifest.start_url, '/');
    assert.equal(manifest.scope, '/');
    assert.equal(manifest.display, 'standalone');
    assert.ok(manifest.name.includes('Alter'));
    assert.ok(manifest.icons.some((icon) => icon.sizes === '192x192' && icon.purpose === 'any'));
    assert.ok(manifest.icons.some((icon) => icon.sizes === '512x512' && icon.purpose === 'maskable'));
    for (const icon of manifest.icons) {
        const png = readFileSync(resolve(root, `public${icon.src}`));
        assert.equal(png.subarray(0, 8).toString('hex'), '89504e470d0a1a0a');
        assert.equal(`${png.readUInt32BE(16)}x${png.readUInt32BE(20)}`, icon.sizes);
        assert.equal(icon.type, 'image/png');
    }
    const shell = readFileSync(resolve(root, 'resources/views/app.blade.php'), 'utf8');
    assert.match(shell, /rel="manifest" href="\/manifest.webmanifest"/);
    assert.match(shell, /name="theme-color"/);
    const entry = readFileSync(resolve(root, 'resources/js/app.tsx'), 'utf8');
    assert.match(entry, /registerDashboardPwa\(\)/);
});

function registrationHarness({ production = true, secure = true, supported = true, ready = 'complete', fails = false } = {}) {
    const calls = [];
    const listeners = new Map();
    const navigator = supported ? { serviceWorker: { register: (...args) => {
        calls.push(args);
        return fails ? Promise.reject(new Error('Unavailable')) : Promise.resolve({});
    } } } : {};
    const window = { isSecureContext: secure, addEventListener: (name, callback, options) => listeners.set(name, { callback, options }) };
    const source = readFileSync(resolve(root, 'resources/js/pwa.ts'), 'utf8').replace('import.meta.env.PROD', 'production');
    const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } });
    const exports = {};
    new Function('exports', 'window', 'navigator', 'document', 'production', outputText)(exports, window, navigator, { readyState: ready }, production);
    exports.registerDashboardPwa();
    return { calls, listeners };
}

test('actual registration waits for load and is restricted to supported secure production contexts', async () => {
    for (const options of [{ production: false }, { secure: false }, { supported: false }]) {
        assert.equal(registrationHarness(options).calls.length, 0);
    }
    const ready = registrationHarness();
    assert.deepEqual(ready.calls, [['/sw.js', { scope: '/', updateViaCache: 'none' }]]);
    const loading = registrationHarness({ ready: 'loading' });
    assert.equal(loading.calls.length, 0);
    assert.deepEqual(loading.listeners.get('load').options, { once: true });
    loading.listeners.get('load').callback();
    assert.equal(loading.calls.length, 1);
    const failed = registrationHarness({ fails: true });
    await new Promise((done) => setImmediate(done));
    assert.equal(failed.calls.length, 1);
});
