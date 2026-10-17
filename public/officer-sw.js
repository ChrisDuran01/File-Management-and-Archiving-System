/*
 * Service worker for the officer phone app (/app). The app registers it with
 * /app as its scope, so it never handles the website's officer pages or the
 * student page (which has its own worker, sw.js).
 *
 * What it does:
 * 1. Saved files: a request for a file's bytes (/app/files/{id}/raw) is
 *    answered from the copy the app kept on this phone (see app.js), so a
 *    saved file opens with or without a connection.
 * 2. No connection: any screen that can't be loaded is answered with the
 *    saved copy of the Offline files screen, which lists what is on the
 *    phone and opens it.
 * 3. The app's own stylesheet, script, viewer libraries and fonts are kept,
 *    so that screen still looks and works right offline.
 *
 * What it deliberately does not do: keep any other screen. Folder lists,
 * search results and the like show other officers' work and change all the
 * time - offline, only what was saved on purpose is shown.
 *
 * Changing what gets cached? Bump VERSION so phones drop old copies.
 */

const VERSION = 'v2';
const SHELL_CACHE = 'sgo-shell-' + VERSION;   // the Offline files screen + its assets
const FILE_CACHE = 'sgo-files-v1';            // saved files - written by app.js, never cleared here
const NETWORK_TIMEOUT_MS = 8000;              // then show the Offline files screen instead of a blank one

const BASE = new URL('./', self.location.href);          // the site's public root, where this file lives
const OFFLINE_URL = new URL('app/offline', BASE).href;
const RAW_FILE = /\/app\/files\/\d+\/raw$/;

const ASSET_PATHS = ['officer-app/', 'vendor/officer-app/', 'icons/'].map(path => new URL(path, BASE).pathname);
const FONT_HOSTS = ['fonts.googleapis.com', 'fonts.gstatic.com'];

// Fetched up front so the very first offline moment already has them.
const PRECACHE = [
    'officer-app/app.css',
    'officer-app/app.js',
    'vendor/officer-app/pdf.min.js',
    'vendor/officer-app/pdf.worker.min.js',
    'vendor/officer-app/jszip.min.js',
    'vendor/officer-app/docx-preview.min.js',
    'icons/officer-192.png',
].map(path => new URL(path, BASE).href);

self.addEventListener('install', (event) => {
    self.skipWaiting();
    // One failed download must not stop the worker installing.
    event.waitUntil(caches.open(SHELL_CACHE).then(cache => Promise.all(PRECACHE.map(url => cache.add(url).catch(() => {})))));
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names
            .filter(name => name.indexOf('sgo-shell-') === 0 && name !== SHELL_CACHE)
            .map(name => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    const data = event.data || {};

    // Sent by the app once an officer is signed in: keep the Offline files
    // screen as that officer sees it.
    if (data.type === 'cache-shell') {
        event.waitUntil(saveShell(data.url || OFFLINE_URL));
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin === self.location.origin) {
        if (RAW_FILE.test(url.pathname)) {
            event.respondWith(savedFile(request));
            return;
        }

        if (request.mode === 'navigate') {
            event.respondWith(page(event));
            return;
        }

        if (ASSET_PATHS.some(path => url.pathname.indexOf(path) === 0)) {
            event.respondWith(savedFirst(event, request));
        }
        return;
    }

    if (FONT_HOSTS.indexOf(url.hostname) !== -1) {
        event.respondWith(savedFirst(event, request));
    }
});

// A saved file comes from its copy. The app asks with cache: 'no-store' when
// it is replacing an out-of-date copy - that one must reach the server.
async function savedFile(request) {
    if (request.cache !== 'no-store' && request.cache !== 'reload') {
        const saved = await caches.match(request.url, { cacheName: FILE_CACHE, ignoreSearch: true });
        if (saved) return saved;
    }
    return fetch(request);
}

// Every screen comes from the network. When there is none (or it hangs),
// the saved Offline files screen is shown in its place.
async function page(event) {
    const network = fetch(event.request);

    // Whenever the Offline files screen itself loads, refresh its saved copy.
    if (event.request.url === OFFLINE_URL) {
        event.waitUntil(network.then(response => keepShell(response.clone())).catch(() => {}));
    }

    const live = await Promise.race([
        network.then(response => response, () => null),
        new Promise(resolve => setTimeout(resolve, NETWORK_TIMEOUT_MS, null)),
    ]);
    if (live) return live;

    const shell = await caches.match(OFFLINE_URL, { cacheName: SHELL_CACHE });
    if (shell) return shell;

    // Slow rather than offline, and nothing saved yet: wait for the real page.
    try {
        return await network;
    } catch (e) {
        return fallbackPage();
    }
}

async function saveShell(url) {
    try {
        await keepShell(await fetch(url, { credentials: 'same-origin' }));
    } catch (e) { /* offline: the next visit saves it */ }
}

async function keepShell(response) {
    const type = response.headers.get('Content-Type') || '';
    // A redirect means the sign-in screen came back - not worth keeping.
    if (response.status !== 200 || response.redirected || type.indexOf('text/html') === -1) return;

    const cache = await caches.open(SHELL_CACHE);
    await cache.put(OFFLINE_URL, new Response(await response.text(), { headers: { 'Content-Type': type } }));
}

// Styles, scripts and fonts. The app's own files are asked for as
// app.css?v=<when it last changed>, so a saved copy under that exact
// address is the current version and is answered at once. A different
// address means the file changed: fetch it, keep it, and drop the copies
// it replaces. Only with no connection is an older version used - an
// out-of-date stylesheet beats an unstyled screen.
async function savedFirst(event, request) {
    const cache = await caches.open(SHELL_CACHE);

    const exact = await cache.match(request, { ignoreVary: true });
    if (exact) return exact;

    try {
        const response = await fetch(request);

        if (response.status === 200 || response.type === 'opaque') {
            const copy = response.clone();
            const url = new URL(request.url);

            event.waitUntil((async () => {
                for (const key of await cache.keys()) {
                    const other = new URL(key.url);
                    if (other.origin === url.origin && other.pathname === url.pathname && key.url !== request.url) await cache.delete(key);
                }
                await cache.put(request, copy);
            })().catch(() => {}));
        }

        return response;
    } catch (error) {
        const older = await cache.match(request, { ignoreSearch: true, ignoreVary: true });
        if (older) return older;
        throw error;
    }
}

function fallbackPage() {
    const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#ffffff">
<title>QSU SG Officers</title>
<style>
    html { height: 100%; }
    body {
        box-sizing: border-box; min-height: 100%; margin: 0; display: grid; place-items: center;
        padding: 32px 24px; background: #f7f8fc; color: #4b5563; text-align: center; line-height: 1.5;
        font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    }
    main { max-width: 22rem; }
    h1 { margin: 0 0 8px; color: #111827; font-size: 1.3rem; }
    p { margin: 0; }
    button {
        min-height: 48px; margin-top: 24px; padding: 0 28px; border: 0; border-radius: 12px;
        background: #058028; color: #fff; font: inherit; font-weight: 600;
    }
</style>
</head>
<body>
<main>
    <h1>You are offline</h1>
    <p>Sign in once while connected, and the files you keep on this phone will open here without a connection.</p>
    <button type="button" id="retry">Try again</button>
</main>
<script>
    document.getElementById('retry').addEventListener('click', function () { location.reload(); });
    window.addEventListener('online', function () { location.reload(); });
</script>
</body>
</html>`;

    return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
