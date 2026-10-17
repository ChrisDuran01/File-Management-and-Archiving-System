/*
 * Service worker for the public student page (stuDashboard).
 *
 * 1. Notifications: shows one when the server pushes it (a new SG
 *    announcement or public document) - even if the site isn't open - and
 *    opens the student page when it is tapped.
 * 2. Offline: the page and its pictures always come from the network first,
 *    exactly like the plain website. A copy is kept only as a fallback for
 *    when there is no connection (or it hangs), and the page then says when
 *    that copy was saved. Fonts and icon styles from the CDNs (which never
 *    change) are answered from the saved copy straight away. Documents are
 *    never stored.
 *
 * The page registers this file with its own URL as the scope, so it only
 * ever handles the student page - never the officers' pages. An older
 * registration covering the whole site ("/") may still run this file until
 * the page moves its notifications over; that one does notifications only.
 *
 * Changing what gets cached? Bump CACHE_VERSION so phones drop old copies.
 */

const CACHE_VERSION = 'v1';
const CACHE_PREFIX = 'sg-student-';
const PAGE_CACHE = CACHE_PREFIX + 'page-' + CACHE_VERSION;
const ASSET_CACHE = CACHE_PREFIX + 'assets-' + CACHE_VERSION;
const NETWORK_TIMEOUT_MS = 8000;          // then show the saved copy instead of a blank screen
const MAX_ASSETS = 120;                   // pictures/fonts kept for offline use
const MAX_ASSET_BYTES = 5 * 1024 * 1024;  // never keep anything bigger

const BASE = new URL('./', self.location.href);   // the site's public root, where this file lives
const STUDENT_PAGE = new URL('stuDashboard', BASE);
const IS_STUDENT_SCOPE = self.registration.scope === STUDENT_PAGE.href;
const ICON_URL = new URL('icons/icon-192.png', BASE).href;
const BADGE_URL = new URL('icons/badge-96.png', BASE).href;

// Same-site pictures the student page shows (logo, officer photos,
// announcement photos). Document previews come from cloud storage and
// are left alone.
const IMAGE_PATHS = ['icons/', 'images/', 'storage/', 'announcements/images/'].map(path => new URL(path, BASE).pathname);
// Fonts and icons the page loads from CDNs, so it still looks right offline.
const CDN_ASSETS = [
    { host: 'fonts.googleapis.com', path: '/' },
    { host: 'fonts.gstatic.com', path: '/' },
    { host: 'cdn.jsdelivr.net', path: '/npm/bootstrap-icons@' },
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    if (IS_STUDENT_SCOPE) {
        // The offline screen's icon. A failed download must never stop the
        // worker installing - notifications depend on it.
        event.waitUntil(caches.open(ASSET_CACHE).then(cache => cache.add(ICON_URL)).catch(() => {}));
    }
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        if (IS_STUDENT_SCOPE) {
            try {
                const keep = [PAGE_CACHE, ASSET_CACHE];
                const names = await caches.keys();
                await Promise.all(names
                    .filter(name => name.startsWith(CACHE_PREFIX) && ! keep.includes(name))
                    .map(name => caches.delete(name)));
            } catch (e) { /* old copies stay until next time */ }
        }
        await self.clients.claim();
    })());
});

/* ------------------------------ Notifications ------------------------------ */

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'QSU Student Government';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: data.body || '',
            icon: ICON_URL,
            badge: BADGE_URL, // Android status bar: white "SG" on transparent
            tag: data.tag || 'sg-announcement', // same tag replaces instead of stacking
            data: { url: data.url || STUDENT_PAGE.href },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // Always open this site, whatever host the link was built with (the
    // server's APP_URL can differ, e.g. when testing through a tunnel),
    // keeping its ?via=push marker and #section.
    const link = new URL((event.notification.data && event.notification.data.url) || STUDENT_PAGE.href, STUDENT_PAGE);
    const target = new URL(link.pathname + link.search + link.hash, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            // Reuse a window already on the student page (the installed app or
            // a browser tab) - never an officer's admin tab.
            const open = windows.find(win => new URL(win.url).pathname === STUDENT_PAGE.pathname);
            if (open) {
                return open.focus()
                    .then(win => (win || open).navigate(target).catch(() => null))
                    .catch(() => self.clients.openWindow(target));
            }
            return self.clients.openWindow(target);
        })
    );
});

/* --------------------------------- Offline --------------------------------- */

if (IS_STUDENT_SCOPE) {
    self.addEventListener('fetch', (event) => {
        const request = event.request;
        if (request.method !== 'GET') return;
        const url = new URL(request.url);

        if (request.mode === 'navigate') {
            if (url.origin === STUDENT_PAGE.origin && url.pathname === STUDENT_PAGE.pathname) handlePage(event);
            return;
        }

        if (url.origin === self.location.origin) {
            if (request.destination === 'image' && IMAGE_PATHS.some(path => url.pathname.startsWith(path))) {
                event.respondWith(networkFirst(event, request));
            }
            return;
        }

        if (CDN_ASSETS.some(cdn => url.hostname === cdn.host && url.pathname.startsWith(cdn.path))) {
            event.respondWith(savedFirst(event, request));
        }
    });

    // The page asks for a copy right after this worker starts, so the very
    // first visit already works offline next time.
    self.addEventListener('message', (event) => {
        if (event.data && event.data.type === 'save-page') {
            event.waitUntil(saveCopyIfMissing());
        }
    });
}

function handlePage(event) {
    const network = fetch(event.request).then(response => ({ response, copy: response.clone() }));
    let answeredWithCopy = false;

    // Keep every good answer for next time - even one that arrives after the
    // saved copy was already shown, in which case the open page is told so it
    // can offer a refresh.
    extend(event, network
        .then(({ copy }) => savePage(copy))
        .then(saved => { if (saved && answeredWithCopy) return tellPages({ type: 'page-updated' }); }));

    event.respondWith((async () => {
        const live = await Promise.race([
            network.then(({ response }) => response, () => null),
            new Promise(resolve => setTimeout(resolve, NETWORK_TIMEOUT_MS, null)),
        ]);
        if (live) return live; // online: the real page, whatever its status

        answeredWithCopy = true;
        const saved = await savedPage();
        if (saved) return saved;

        // Nothing saved yet: a slow page still beats the offline screen.
        try {
            return (await network).response;
        } catch (e) {
            return offlinePage();
        }
    })());
}

async function savePage(response) {
    const type = response.headers.get('Content-Type') || '';
    if (response.status !== 200 || response.type !== 'basic' || response.redirected || ! type.includes('text/html')) {
        return false;
    }
    // The server marks pages that carry one visitor's own details (a contact
    // form result) no-store: those never become the offline copy.
    if (/no-store/i.test(response.headers.get('Cache-Control') || '')) {
        return false;
    }

    const body = await response.text();
    const cache = await caches.open(PAGE_CACHE);
    await cache.put(STUDENT_PAGE.href, new Response(body, {
        headers: { 'Content-Type': type, 'X-Saved-At': new Date().toISOString() },
    }));
    return true;
}

async function saveCopyIfMissing() {
    try {
        const cache = await caches.open(PAGE_CACHE);
        if (await cache.match(STUDENT_PAGE.href)) return;
        await savePage(await fetch(STUDENT_PAGE.href, { credentials: 'same-origin' }));
    } catch (e) { /* offline or refused: the next visit saves it */ }
}

async function savedPage() {
    const cache = await caches.open(PAGE_CACHE);
    const saved = await cache.match(STUDENT_PAGE.href);
    if (! saved) return null;

    // Mark it as the saved copy, and from when, so the page can say so.
    const savedAt = (saved.headers.get('X-Saved-At') || '').replace(/[^0-9TZ:.\-]/g, '');
    const html = (await saved.text()).replace('</head>', '<meta name="sg-saved-copy" content="' + savedAt + '">\n</head>');

    return new Response(html, {
        headers: { 'Content-Type': saved.headers.get('Content-Type') || 'text/html; charset=utf-8' },
    });
}

async function networkFirst(event, request) {
    try {
        const response = await fetch(request);
        if (response.status === 200 || response.type === 'opaque') {
            extend(event, storeAsset(request, response.clone()));
        }
        return response;
    } catch (error) {
        const saved = await caches.match(request, { cacheName: ASSET_CACHE, ignoreVary: true });
        if (saved) return saved;
        throw error;
    }
}

// Fonts and icon styles from the CDNs never change at a given address, and
// the page can't draw until its stylesheets arrive - so a hanging connection
// would leave even the saved copy blank. Answer from the saved copy at once
// and refresh it in the background.
async function savedFirst(event, request) {
    const saved = await caches.match(request, { cacheName: ASSET_CACHE, ignoreVary: true });
    const network = fetch(request).then((response) => {
        if (response.status === 200 || response.type === 'opaque') {
            extend(event, storeAsset(request, response.clone()));
        }
        return response;
    });
    if (saved) {
        extend(event, network);
        return saved;
    }
    return network;
}

async function storeAsset(request, response) {
    if (Number(response.headers.get('Content-Length') || 0) > MAX_ASSET_BYTES) return;

    const cache = await caches.open(ASSET_CACHE);
    await cache.put(request, response);

    // Oldest first (a re-saved item moves to the end): drop the extras so
    // photos never pile up on the phone. The offline screen's icon stays.
    const keys = (await cache.keys()).filter(key => key.url !== ICON_URL);
    await Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_ASSETS)).map(key => cache.delete(key)));
}

async function tellPages(message) {
    const windows = await self.clients.matchAll({ type: 'window' });
    windows.forEach(win => win.postMessage(message));
}

// Keeps the worker alive until a background save finishes. Best effort: a
// browser that refuses the late call still runs it while the page loads.
function extend(event, promise) {
    const settled = promise.catch(() => {});
    try {
        event.waitUntil(settled);
    } catch (e) { /* see above */ }
}

function offlinePage() {
    const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0f3d1f">
<title>QSU Student Government</title>
<style>
    html { height: 100%; }
    body {
        box-sizing: border-box; min-height: 100%; margin: 0; display: grid; place-items: center;
        padding: max(32px, env(safe-area-inset-top)) 24px max(32px, env(safe-area-inset-bottom));
        background: #fbfaf7; color: #3d4641; text-align: center; line-height: 1.6;
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        -webkit-font-smoothing: antialiased;
    }
    main { max-width: 22rem; }
    img { display: block; width: 88px; height: 88px; margin: 0 auto 24px; border-radius: 22px; box-shadow: 0 8px 24px rgba(15, 30, 20, .16); }
    h1 { margin: 0 0 8px; color: #16241b; font-size: 1.4rem; letter-spacing: -.02em; }
    p { margin: 0; color: #5f6b64; }
    button {
        min-height: 48px; margin-top: 28px; padding: 0 28px; border: 0; border-radius: 999px;
        background: #0f3d1f; color: #fff; font: inherit; font-weight: 600; cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
</style>
</head>
<body>
<main>
    <img src="${ICON_URL}" alt="">
    <h1>You're offline</h1>
    <p>Connect to the internet to see the latest from the Student Government. This page reloads by itself once you're back online.</p>
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
