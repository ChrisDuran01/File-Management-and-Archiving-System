/*
 * Officer phone app (/app) - runs on every screen.
 *
 * 1. Offline files: copies of files are kept on this phone, in the browser's
 *    own storage (Cache Storage for the bytes, localStorage for the list).
 *    Some are saved automatically (the server names them - see
 *    OfficerAppController::sync()), any other one when the officer taps
 *    "Keep offline". The service worker (officer-sw.js) answers a saved
 *    file's address from that copy, so it opens with or without a connection.
 * 2. The viewer: shows a PDF, Word document, picture or text file inside the
 *    app, from the network or from a saved copy alike.
 * 3. Small screen behaviours (install card, add-a-file form, sign out).
 *
 * Copies leave the phone when the officer signs out, when the server says
 * they may no longer open a file, when another officer signs in here, or
 * when the phone has not checked in for too long (expire_days) - which is
 * what ends offline access for an account that can no longer sign in.
 */
(function () {
    'use strict';

    const C = window.SGO || {};
    const URLS = C.urls || {};
    const FILE_CACHE = 'sgo-files-v1';   // officer-sw.js reads the same cache
    const DAY = 86400000;
    const SYNC_EVERY = 5 * 60000;        // don't ask the server on every screen
    const MAX_PDF_PAGES = 300;           // pages are drawn only near the screen - see lazyPdfPages()

    // Cache Storage and service workers only exist on https (or localhost).
    const canStore = 'caches' in window && 'serviceWorker' in navigator;

    const ls = {
        get(key, fallback) {
            try {
                const value = localStorage.getItem(key);
                return value === null ? fallback : JSON.parse(value);
            } catch (e) { return fallback; }
        },
        set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* storage full or blocked */ } },
        del(key) { try { localStorage.removeItem(key); } catch (e) { /* ignore */ } },
    };

    const $ = (selector, root) => (root || document).querySelector(selector);
    const $$ = (selector, root) => Array.from((root || document).querySelectorAll(selector));

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    // Which tint a file's tile gets - the same table as OfficerAppController::toneOf().
    function toneOf(ext) {
        ext = String(ext || '').toLowerCase();
        if (ext === 'pdf') return 'pdf';
        if (ext === 'doc' || ext === 'docx') return 'doc';
        if (ext === 'xls' || ext === 'xlsx' || ext === 'csv') return 'sheet';
        if (ext === 'ppt' || ext === 'pptx') return 'slide';
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1) return 'image';
        return '';
    }

    function toast(message) {
        let box = $('#sgo-toast');
        if (! box) {
            box = el('div');
            box.id = 'sgo-toast';
            box.setAttribute('role', 'status');
            box.style.cssText = 'position:fixed;left:20px;right:20px;bottom:calc(96px + env(safe-area-inset-bottom));z-index:60;padding:13px 18px;border-radius:16px;background:#0f172a;color:#fff;font-size:14px;font-weight:500;text-align:center;box-shadow:0 10px 30px rgba(15,23,42,.28)';
            document.body.appendChild(box);
        }
        box.textContent = message;
        box.hidden = false;
        clearTimeout(box.timer);
        box.timer = setTimeout(() => { box.hidden = true; }, 3500);
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': C.csrf || '' },
            body: JSON.stringify(body || {}),
        });
    }

    /* ------------------------------ Whose copies ------------------------------ */

    // The saved list belongs to one officer. The offline screen can be a
    // saved copy of the page, so it reads back who that was.
    const previousUser = ls.get('sgo:user', null);
    const userId = C.user || previousUser;

    const indexKey = () => 'sgo:index:' + userId;
    const loadIndex = () => ls.get(indexKey(), null) || { files: {}, lastCheck: 0 };
    const saveIndex = (index) => ls.set(indexKey(), index);

    function changed() {
        document.dispatchEvent(new CustomEvent('sgo:offline-changed'));
    }

    /* ------------------------------ Activity log ------------------------------ */

    // Saving a file on a phone is written to the activity log. What still
    // has to be reported is kept here until the server has acknowledged it,
    // so a dropped request - or the officer leaving the screen - can't lose
    // the record. (A lost acknowledgement means it is reported twice, which
    // is the safer mistake for an audit trail.)
    // Sending a copy to another app (Bluetooth, Quick Share, Messenger...) is
    // kept here the same way - it often happens with no connection - along
    // with when it happened, since the log line is only written on arrival.
    const MAX_UNLOGGED_SENDS = 200;

    function unlogged() {
        const pending = ls.get('sgo:unlogged', null) || {};
        return { auto: pending.auto || 0, kept: pending.kept || [], sent: pending.sent || [] };
    }

    function noteSave(meta, manual) {
        const pending = unlogged();
        if (! manual) pending.auto++;
        else if (pending.kept.indexOf(meta.id) === -1) pending.kept.push(meta.id);
        ls.set('sgo:unlogged', pending);
    }

    function noteSent(meta) {
        const pending = unlogged();
        pending.sent.push({ id: meta.id, at: Date.now() });
        pending.sent = pending.sent.slice(-MAX_UNLOGGED_SENDS);
        ls.set('sgo:unlogged', pending);
    }

    let reporting = null;

    function report() {
        if (reporting) return reporting;
        if (! C.user || ! navigator.onLine || ! URLS.saved) return Promise.resolve();

        const batch = unlogged();
        if (! batch.auto && ! batch.kept.length && ! batch.sent.length) return Promise.resolve();

        reporting = post(URLS.saved, { count: batch.auto, kept: batch.kept, sent: batch.sent }).then((response) => {
            if (! response.ok) return;
            const now = unlogged();
            ls.set('sgo:unlogged', {
                auto: Math.max(0, now.auto - batch.auto),
                kept: now.kept.filter(id => batch.kept.indexOf(id) === -1),
                // Sends are only ever added at the end, so the ones reported are the first ones.
                sent: now.sent.slice(batch.sent.length),
            });
        }).catch(() => { /* stays pending - sent with the next one */ }).then(() => { reporting = null; });

        return reporting;
    }

    const offline = {
        has(id) { return !! loadIndex().files[id]; },

        list() {
            return Object.values(loadIndex().files).sort((a, b) => (b.savedAt || 0) - (a.savedAt || 0));
        },

        async save(meta, manual) {
            const response = await fetch(meta.url, { credentials: 'same-origin', cache: 'no-store' });

            // A redirect means the sign-in screen came back instead of the file.
            if (! response.ok || response.redirected) {
                throw new Error('unavailable');
            }

            await (await caches.open(FILE_CACHE)).put(meta.url, response);

            const index = loadIndex();
            const before = index.files[meta.id];

            index.files[meta.id] = {
                id: meta.id, name: meta.name, ext: meta.ext, kind: meta.kind,
                size: meta.size, size_label: meta.size_label, folder: meta.folder,
                version: meta.version, url: meta.url, page: meta.page,
                savedAt: Date.now(),
                manual: !! manual || !! (before && before.manual),
            };
            // The server just handed this over, so the session is good:
            // that counts as checking in.
            index.lastCheck = Date.now();
            saveIndex(index);
            noteSave(meta, manual);
            changed();
        },

        async remove(id) {
            const index = loadIndex();
            const meta = index.files[id];
            if (! meta) return;

            delete index.files[id];
            saveIndex(index);
            try { await (await caches.open(FILE_CACHE)).delete(meta.url); } catch (e) { /* already gone */ }
            changed();
        },

        async wipe() {
            try { await caches.delete(FILE_CACHE); } catch (e) { /* nothing stored */ }
            try {
                Object.keys(localStorage).filter(key => key.indexOf('sgo:index:') === 0).forEach(ls.del);
            } catch (e) { /* ignore */ }
            ls.del('sgo:lastSync');
            ls.del('sgo:unlogged');
            changed();
        },
    };

    /* --------------------------- Start-up housekeeping --------------------------- */

    const ready = (async function startUp() {
        if (! canStore) return;

        // A different officer signed in on this phone: the last one's copies go.
        if (C.user && previousUser && previousUser !== C.user) {
            await offline.wipe();
        }
        if (C.user) ls.set('sgo:user', C.user);

        // Too long since this phone last checked in with the server.
        const index = loadIndex();
        const days = index.expireDays || C.expireDays || 30;
        if (Object.keys(index.files).length && index.lastCheck && Date.now() - index.lastCheck > days * DAY) {
            await offline.wipe();
            ls.set('sgo:expired', true);
        }
    })();

    if (canStore && URLS.sw) {
        navigator.serviceWorker.register(URLS.sw, { scope: URLS.scope }).then(() => {
            // Keep a copy of the Offline files screen itself, to show when
            // there is no connection.
            if (C.user) {
                navigator.serviceWorker.ready.then((registration) => {
                    if (registration.active) registration.active.postMessage({ type: 'cache-shell', url: URLS.offline });
                });
            }
        }).catch(() => { /* the app still works online */ });
    }

    /* ------------------------------ Automatic saving ------------------------------ */

    // 'on' | 'off' | null (never asked)
    const autoPref = () => ls.get('sgo:auto', null);
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    // Only some phones say what kind of connection they are on (Android does, iPhone doesn't).
    const knowsConnection = !! (connection && connection.type);

    function autoAllowed() {
        const pref = autoPref();
        if (pref === 'off') return false;
        if (connection && connection.saveData) return false;
        if (knowsConnection) return connection.type !== 'cellular';   // never on mobile data
        return pref === 'on';                                         // can't tell: only if the officer said yes
    }

    let running = null;

    function sync(force) {
        if (! C.user || ! canStore || ! navigator.onLine || ! URLS.sync) return Promise.resolve(null);
        // A check asked for on purpose ("Check now", or automatic saving just
        // turned on) must not be swallowed by one already under way that was
        // started under the old setting: run again once that one finishes.
        if (running) return force ? running.then(() => sync(true)) : running;
        if (! force && Date.now() - ls.get('sgo:lastSync', 0) < SYNC_EVERY) return Promise.resolve(null);

        running = (async () => {
            await ready;

            const response = await post(URLS.sync, { have: Object.keys(loadIndex().files).map(Number) });
            if (! response.ok) return { ok: false };

            const data = await response.json();
            if (data.user !== C.user) return { ok: false };

            const index = loadIndex();
            index.lastCheck = Date.now();
            index.expireDays = data.expire_days;
            saveIndex(index);
            ls.del('sgo:expired');

            // 1. Files this officer may no longer open.
            for (const id of data.revoked) await offline.remove(id);

            // 2. Automatic copies that are no longer in the automatic set
            //    (stored under a past year, or their folder got restricted).
            //    Ones the officer chose by hand stay.
            const wanted = new Map(data.files.map(file => [file.id, file]));
            for (const meta of offline.list()) {
                if (! meta.manual && ! wanted.has(meta.id)) await offline.remove(meta.id);
            }

            // 3. Copies that are out of date are always refreshed; files not
            //    on the phone yet are fetched only when automatic saving
            //    is allowed right now (Wi-Fi, or the officer said yes).
            const allowed = autoAllowed();
            const held = loadIndex().files;
            const missing = data.files.filter(file => ! held[file.id]);
            const todo = data.files.filter(file => held[file.id] && held[file.id].version !== file.version)
                .concat(allowed ? missing : []);

            let saved = 0, failed = 0, full = false;
            for (let i = 0; i < todo.length && ! full; i++) {
                document.dispatchEvent(new CustomEvent('sgo:sync-progress', { detail: { done: i, total: todo.length } }));

                // A single download can fail on a shaky connection: try it
                // once more before moving on. Whatever still fails is simply
                // missing, so the next check asks for it again.
                let done = false;
                for (let attempt = 0; attempt < 2 && ! done && ! full; attempt++) {
                    try {
                        await offline.save(todo[i], false);
                        done = true;
                    } catch (e) {
                        if (e && e.name === 'QuotaExceededError') full = true;   // the phone is out of space
                        else if (attempt === 0) await new Promise(resolve => setTimeout(resolve, 1000));
                    }
                }

                if (done) saved++; else failed++;
            }

            report();

            // Only a check that got to the end counts as done: if the
            // officer moves to another screen while files are still
            // coming down, the next screen picks up where this stopped
            // instead of waiting out SYNC_EVERY. (A full phone does count -
            // asking again at once would just fail again.)
            if (! failed || full) ls.set('sgo:lastSync', Date.now());

            return { ok: true, saved: saved, failed: failed, pending: allowed ? 0 : missing.length, allowed: allowed };
        })().catch(() => ({ ok: false })).then((result) => {
            running = null;
            document.dispatchEvent(new CustomEvent('sgo:synced', { detail: result }));
            return result;
        });

        return running;
    }

    /* ---------------------------------- Viewer ---------------------------------- */

    const loading = {};

    function loadScript(src) {
        if (! loading[src]) {
            loading[src] = new Promise((resolve, reject) => {
                const script = el('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = () => { delete loading[src]; reject(new Error('script')); };
                document.head.appendChild(script);
            });
        }
        return loading[src];
    }

    async function fetchFile(meta) {
        const response = await fetch(meta.url, { credentials: 'same-origin' });
        if (! response.ok || response.redirected) throw new Error('unavailable');
        return response;
    }

    // One download per file per screen, shared by the viewer and by sending
    // the file. A phone opens its share menu only within a few seconds of a
    // tap, so the file has to be in hand already - downloading it after the
    // tap (over a slow connection) loses that window.
    const blobs = {}, blobReady = {};

    function fileBlob(meta) {
        if (! blobs[meta.url]) {
            blobs[meta.url] = fetchFile(meta).then(response => response.blob());
            blobs[meta.url].then(() => { blobReady[meta.url] = true; }, () => { delete blobs[meta.url]; });
        }
        return blobs[meta.url];
    }

    function status(box, title, text) {
        const wrap = el('div', 'viewer-status');
        wrap.appendChild(el('strong', '', title));
        if (text) wrap.appendChild(el('span', '', text));
        box.replaceChildren(wrap);
        return wrap;
    }

    // Draws a PDF page only when it comes near the screen, and lets go of
    // pages scrolled far away. Drawing every page up front cost about 3 MB of
    // memory per page on a phone - the reason long PDFs used to stop at 30.
    function lazyPdfPages(pdf, slots) {
        const drawn = new Set();
        let queue = Promise.resolve(); // one page at a time keeps scrolling smooth

        function draw(slot) {
            if (drawn.has(slot)) return;
            drawn.add(slot);

            queue = queue.then(async () => {
                if (! drawn.has(slot)) return; // scrolled far away before its turn

                const page = await pdf.getPage(Number(slot.dataset.page));
                const base = page.getViewport({ scale: 1 });
                const scale = ((slot.clientWidth || 358) / base.width) * Math.min(window.devicePixelRatio || 1, 2);
                const viewport = page.getViewport({ scale: scale });
                const canvas = el('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                slot.style.aspectRatio = base.width + ' / ' + base.height; // this page's own shape

                await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;

                if (drawn.has(slot)) {
                    slot.replaceChildren(canvas);
                } else {
                    canvas.width = canvas.height = 0;
                }
            }).catch(() => drawn.delete(slot)); // try again next time it comes near
        }

        function release(slot) {
            if (! drawn.delete(slot)) return;
            const canvas = slot.querySelector('canvas');
            if (canvas) {
                canvas.width = canvas.height = 0; // frees the pixels now, not whenever memory is collected
                canvas.remove();
            }
        }

        const near = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting) draw(entry.target); });
        }, { rootMargin: '150% 0px' });
        const far = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (! entry.isIntersecting) release(entry.target); });
        }, { rootMargin: '600% 0px' });

        slots.forEach(slot => { near.observe(slot); far.observe(slot); });
    }

    async function renderFile(box, meta) {
        status(box, 'Opening…');

        const libs = C.libs || {};

        try {
            if (meta.kind === 'image') {
                const blob = await fileBlob(meta);
                const img = new Image();
                img.alt = meta.name;
                img.src = URL.createObjectURL(blob);
                box.replaceChildren(img);
            } else if (meta.kind === 'pdf') {
                await loadScript(libs.pdf);
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = libs.pdfWorker;

                const data = await (await fileBlob(meta)).arrayBuffer();
                const pdf = await window.pdfjsLib.getDocument({ data: data }).promise;
                const pages = Math.min(pdf.numPages, MAX_PDF_PAGES);
                const first = (await pdf.getPage(1)).getViewport({ scale: 1 });

                // A light placeholder per page, sized like page 1 (most PDFs
                // keep one page size), so the length and scroll bar are right
                // from the start; each is drawn when it comes near.
                box.replaceChildren();
                const slots = [];
                for (let n = 1; n <= pages; n++) {
                    const slot = el('div', 'viewer-page');
                    slot.style.aspectRatio = first.width + ' / ' + first.height;
                    slot.dataset.page = String(n);
                    slot.setAttribute('role', 'img');
                    slot.setAttribute('aria-label', 'Page ' + n + ' of ' + pdf.numPages);
                    box.appendChild(slot);
                    slots.push(slot);
                }
                lazyPdfPages(pdf, slots);

                if (pdf.numPages > pages) {
                    box.appendChild(el('div', 'viewer-more', 'Showing the first ' + pages + ' of ' + pdf.numPages + ' pages. Open it in another app to read the rest.'));
                }
            } else if (meta.kind === 'docx') {
                await loadScript(libs.jszip);
                await loadScript(libs.docx);

                const blob = await fileBlob(meta);
                const doc = el('div', 'viewer-doc');
                box.replaceChildren(doc);
                // Reflow to the phone's width instead of a fixed paper size.
                await window.docx.renderAsync(blob, doc, null, { inWrapper: false, ignoreWidth: true, ignoreHeight: true, breakPages: false });
            } else if (meta.kind === 'text') {
                const text = await (await fileBlob(meta)).text();
                box.replaceChildren(el('pre', '', text.slice(0, 50000)));
            } else {
                status(box, 'No preview for this file type', 'Open it in another app on this phone.');
            }
        } catch (e) {
            status(box, navigator.onLine ? 'This file could not be shown here' : 'You are offline',
                navigator.onLine ? 'Try opening it in another app.' : 'This file is not saved on this phone.');
        }
    }

    // Hands the file to whatever the phone has for it (Word, a PDF reader, the
    // gallery, Messenger...). That is a copy leaving the app, so it goes in
    // the activity log like a download does on the website.
    //
    // Two things the phone decides, not us: the share menu opens only within
    // a few seconds of a tap (so a file still downloading asks for a second
    // tap), and which kinds of file a web app may share at all (canShare -
    // it varies by browser and version). A file it refuses is saved to the
    // phone instead, to send from the Files app.
    async function openElsewhere(meta) {
        if (! blobReady[meta.url]) toast('Getting the file ready…');

        let blob;
        try {
            blob = await fileBlob(meta);
        } catch (e) {
            toast(navigator.onLine ? 'This file could not be loaded.' : 'You are offline and this file is not saved on this phone.');
            return;
        }

        const file = new File([blob], meta.name, { type: blob.type || 'application/octet-stream' });

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            try {
                await navigator.share({ files: [file], title: meta.name });
                logSent(meta);
                toast('Sent.');
                return;
            } catch (e) {
                if (e && e.name === 'AbortError') return;   // the officer closed the share menu
                if (e && e.name === 'NotAllowedError') {    // too long since the tap
                    toast('The file is ready. Tap again to send it.');
                    return;
                }
            }
        }

        const link = el('a');
        link.href = URL.createObjectURL(blob);
        link.download = meta.name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 60000);
        logSent(meta);
        toast(navigator.canShare
            ? 'This phone can\'t send this kind of file from the app, so it was saved to Downloads. Send it from your Files app.'
            : 'Saved to this phone\'s Downloads.');
    }

    // Recorded now if there is a connection, otherwise as soon as there is one.
    function logSent(meta) {
        noteSent(meta);
        report();
    }

    /* ----------------------------- "Keep offline" buttons ----------------------------- */

    function metaOf(node) {
        try { return JSON.parse(node.getAttribute('data-file')); } catch (e) { return null; }
    }

    function paintKeep(button) {
        const meta = metaOf(button);
        if (! meta) return;

        const on = offline.has(meta.id);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        button.classList.toggle('is-on', on);
        button.classList.toggle('muted', ! on && button.classList.contains('icon-btn'));
        button.setAttribute('aria-label', on ? 'Kept offline. Remove the offline copy of ' + meta.name : 'Keep ' + meta.name + ' offline');

        const off = $('[data-icon-off]', button), onIcon = $('[data-icon-on]', button), label = $('[data-keep-label]', button);
        // The icons are SVG elements, which have no .hidden property - only
        // the attribute works on them.
        if (off) off.toggleAttribute('hidden', on);
        if (onIcon) onIcon.toggleAttribute('hidden', ! on);
        if (label) label.textContent = on ? 'Kept offline' : 'Keep offline';
    }

    function wireKeep() {
        $$('[data-keep]').forEach((button) => {
            if (! canStore) { button.hidden = true; return; }

            paintKeep(button);

            button.addEventListener('click', async () => {
                const meta = metaOf(button);
                if (! meta || button.disabled) return;

                button.disabled = true;
                try {
                    if (offline.has(meta.id)) {
                        await offline.remove(meta.id);
                        toast('Removed from this phone.');
                    } else {
                        await offline.save(meta, true);
                        report();
                        toast('Saved on this phone.');
                    }
                } catch (e) {
                    toast(e && e.name === 'QuotaExceededError' ? 'This phone is out of space.' : 'Could not save this file. Check your connection.');
                }
                button.disabled = false;
            });
        });

        document.addEventListener('sgo:offline-changed', () => $$('[data-keep]').forEach(paintKeep));
    }

    // The small check beside a file that has a copy on this phone.
    function paintSavedMarks() {
        $$('[data-saved-mark]').forEach((mark) => {
            mark.toggleAttribute('hidden', ! canStore || ! offline.has(mark.getAttribute('data-saved-mark')));
        });
    }

    function paintCounts() {
        const count = offline.list().length;
        $$('[data-offline-count]').forEach((node) => {
            node.textContent = count === 0
                ? 'Nothing saved on this phone yet'
                : count + (count === 1 ? ' file' : ' files') + ' saved on this phone';
        });
    }

    /* ------------------------------ Offline files screen ------------------------------ */

    function wireOfflineScreen() {
        const screen = $('[data-offline-screen]');
        if (! screen) return;

        const list = $('[data-offline-list]', screen);
        const banner = $('[data-offline-banner]', screen);
        const expired = $('[data-offline-expired]', screen);
        const statusLine = $('[data-offline-status]', screen);
        const toggle = $('[data-auto-toggle]', screen);
        const syncNow = $('[data-sync-now]', screen);
        const subtitle = $('[data-offline-subtitle]');

        function draw() {
            const files = offline.list();

            if (subtitle) subtitle.textContent = files.length + (files.length === 1 ? ' file' : ' files') + ' saved on this phone';
            if (banner) banner.hidden = navigator.onLine;
            if (expired) expired.hidden = ! ls.get('sgo:expired', false);

            if (! files.length) {
                const empty = el('div', 'empty');
                empty.appendChild(el('strong', '', 'No files saved on this phone yet'));
                empty.appendChild(document.createTextNode(navigator.onLine
                    ? 'Open a file and tap Keep offline, or turn on automatic saving below.'
                    : 'Connect to the internet to save files for next time.'));
                list.replaceChildren(empty);
                return;
            }

            list.replaceChildren.apply(list, files.map((meta) => {
                const row = el('div', 'row');

                const open = el('button', 'row-main');
                open.type = 'button';
                open.style.cssText = 'border:0;background:transparent;text-align:left;padding:0';
                const chip = el('span', 'chip ' + toneOf(meta.ext), meta.ext || 'file');
                const text = el('span', 'row-text');
                text.appendChild(el('span', 'row-name', meta.name));
                text.appendChild(el('span', 'row-meta', meta.folder + ' · Saved ' + new Date(meta.savedAt).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })));
                open.append(chip, text);
                open.addEventListener('click', () => openOverlay(meta));

                const remove = el('button', 'icon-btn muted');
                remove.type = 'button';
                remove.setAttribute('aria-label', 'Remove the offline copy of ' + meta.name);
                remove.innerHTML = '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"></path><path d="M9 7V4h6v3"></path><path d="M6 7l1 13h10l1-13"></path></svg>';
                remove.addEventListener('click', () => offline.remove(meta.id));

                row.append(open, remove);
                return row;
            }));
        }

        function describeAuto() {
            if (! statusLine) return;
            const last = loadIndex().lastCheck;
            const when = last ? 'Last checked ' + new Date(last).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) + '.' : 'Not checked yet.';
            statusLine.textContent = when;
        }

        if (toggle) {
            toggle.checked = autoPref() !== 'off' && (knowsConnection || autoPref() === 'on');
            toggle.addEventListener('change', () => {
                ls.set('sgo:auto', toggle.checked ? 'on' : 'off');
                if (toggle.checked) runSync();
            });
        }

        function runSync() {
            if (! navigator.onLine) { toast('You are offline.'); return; }
            if (syncNow) syncNow.disabled = true;
            if (statusLine) statusLine.textContent = 'Checking…';
            sync(true);
        }

        if (syncNow) syncNow.addEventListener('click', runSync);

        document.addEventListener('sgo:sync-progress', (event) => {
            if (statusLine) statusLine.textContent = 'Saving ' + (event.detail.done + 1) + ' of ' + event.detail.total + '…';
        });

        document.addEventListener('sgo:synced', (event) => {
            if (syncNow) syncNow.disabled = false;
            const result = event.detail;
            if (! result || ! result.ok) { describeAuto(); return; }
            describeAuto();
            if (result.pending && statusLine) {
                statusLine.textContent += ' ' + result.pending + (result.pending === 1 ? ' file is' : ' files are') + ' waiting: automatic saving is off or you are on mobile data.';
            }
            if (result.failed && statusLine) {
                statusLine.textContent += ' ' + result.failed + (result.failed === 1 ? ' file' : ' files') + ' could not be saved and will be tried again.';
            }
            draw();
        });

        document.addEventListener('sgo:offline-changed', draw);
        window.addEventListener('online', draw);
        window.addEventListener('offline', draw);

        ready.then(() => { draw(); describeAuto(); });
    }

    // A saved file opened from the Offline files screen: shown over the list,
    // since no other screen can be loaded without a connection.
    function openOverlay(meta) {
        const overlay = el('div', 'overlay');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-label', meta.name);

        const bar = el('header', 'app-bar has-back');
        const back = el('button', 'icon-btn');
        back.type = 'button';
        back.setAttribute('aria-label', 'Back to Offline files');
        back.innerHTML = '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"></path><path d="m11 6-6 6 6 6"></path></svg>';
        const text = el('div', 'app-bar-text');
        text.appendChild(el('div', 'app-bar-title', meta.name));
        text.appendChild(el('div', 'app-bar-sub', meta.folder + ' · Saved on this phone'));
        bar.append(back, text);

        const main = el('main', 'app-main');
        const viewer = el('div', 'viewer');
        const other = el('button', 'btn block', 'Send or open in another app');
        other.type = 'button';
        other.addEventListener('click', () => openElsewhere(meta));
        main.append(viewer, other);

        overlay.append(bar, main);
        document.body.appendChild(overlay);

        const close = () => { overlay.remove(); window.removeEventListener('popstate', close); };
        back.addEventListener('click', () => history.back());
        history.pushState({ sgoOverlay: true }, '');
        window.addEventListener('popstate', close);

        renderFile(viewer, meta);
    }

    /* -------------------------------- Other screens -------------------------------- */

    function wireFileScreen() {
        const viewer = $('[data-viewer]');
        if (viewer) {
            const meta = metaOf(viewer);
            if (meta) renderFile(viewer, meta);
        }

        $$('[data-open-elsewhere]').forEach((button) => {
            button.addEventListener('click', () => {
                const meta = metaOf(button);
                if (meta) openElsewhere(meta);
            });
        });
    }

    // The Share sheet on a file's screen: a link anyone can open without an
    // account (made by ShareLinkController, as from the website's Share
    // dialog), or a copy of the file itself through the phone's share menu.
    function wireShare() {
        const opener = $('[data-share-open]'), scrim = $('[data-share-sheet]');
        if (! opener || ! scrim) return;

        const sheet = $('.sheet', scrim);
        const meta = metaOf(sheet);
        const linksUrl = sheet.getAttribute('data-links');
        const notice = $('[data-share-status]', sheet);
        const choose = $('[data-share-choose]', sheet), made = $('[data-share-made]', sheet);
        const linkBtn = $('[data-share-link]', sheet), urlBox = $('[data-share-url]', sheet);
        const existing = $('[data-share-existing]', sheet), list = $('[data-share-list]', sheet);
        const sendFileBtn = $('[data-share-copy-file]', sheet), sendFileNote = $('.row-meta', sendFileBtn);
        let current = null;   // the link just made

        function say(message, tone) {
            notice.className = 'notice ' + (tone || 'green');
            $('.notice-text', notice).textContent = message;
            notice.hidden = false;
        }

        function request(url, options) {
            return fetch(url, Object.assign({
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': C.csrf || '' },
            }, options)).then(response => response.json().catch(() => ({})).then((body) => {
                if (! response.ok) throw new Error(body.message || 'That did not work. Please try again.');
                return body;
            }));
        }

        function open() {
            notice.hidden = true;
            made.hidden = true;
            choose.hidden = false;
            current = null;
            scrim.hidden = false;
            document.body.style.overflow = 'hidden';
            // The phone's back button closes the sheet, not the screen.
            history.pushState({ sgoSheet: true }, '');
            window.addEventListener('popstate', hide);
            $('[data-share-close]', sheet).focus();
            loadLinks();
            // Have the file in hand before "Send the file" is tapped (the
            // viewer has usually fetched it already), and say up front when
            // this phone won't take it straight to an app.
            if (meta) {
                fileBlob(meta).then((blob) => {
                    const file = new File([blob], meta.name, { type: blob.type || 'application/octet-stream' });
                    const direct = navigator.canShare && navigator.canShare({ files: [file] });
                    sendFileNote.textContent = direct
                        ? 'A copy. It can\'t be taken back.'
                        : 'This phone can\'t send this kind of file from the app. Tap to save it to Downloads, then send it from your Files app.';
                }, () => { /* reported if they tap it */ });
            }
        }

        function hide() {
            scrim.hidden = true;
            document.body.style.overflow = '';
            window.removeEventListener('popstate', hide);
            opener.focus();
        }

        const close = () => history.back();   // pops what open() pushed, which hides the sheet

        function loadLinks() {
            existing.hidden = true;
            if (! navigator.onLine) return;
            request(linksUrl).then(data => drawLinks(data.links)).catch(() => { /* the list is a nicety */ });
        }

        function drawLinks(links) {
            list.replaceChildren();
            links.forEach((link) => {
                const row = el('div', 'row');
                const text = el('span', 'row-text');
                text.appendChild(el('span', 'row-name', link.mine ? 'Made by you' : 'Made by ' + (link.made_by || 'a former officer')));
                text.appendChild(el('span', 'row-meta', 'Until ' + link.until + ' · opened ' + link.opened + (link.opened === 1 ? ' time' : ' times')));
                row.appendChild(text);

                if (link.can_turn_off) {
                    const off = el('button', 'btn small', 'Turn off');
                    off.type = 'button';
                    off.addEventListener('click', () => turnOff(link, off));
                    row.appendChild(off);
                }
                list.appendChild(row);
            });
            existing.hidden = ! links.length;
        }

        function turnOff(link, button) {
            if (! window.confirm('Turn off this link? Anyone who has it will no longer be able to open the file.')) return;
            button.disabled = true;
            request(link.turn_off_url, { method: 'DELETE' })
                .then(() => {
                    if (current && current.id === link.id) { current = null; made.hidden = true; choose.hidden = false; }
                    say('Link turned off.');
                    loadLinks();
                })
                .catch((e) => { button.disabled = false; say(e.message, 'red'); });
        }

        function chosenDays() {
            const pressed = $('[data-share-days][aria-pressed="true"]', sheet);
            return pressed ? Number(pressed.getAttribute('data-share-days')) : 7;
        }

        $$('[data-share-days]', sheet).forEach((chip) => {
            chip.addEventListener('click', () => {
                $$('[data-share-days]', sheet).forEach(other => other.setAttribute('aria-pressed', String(other === chip)));
            });
        });

        function shareLink(straightAfterMaking) {
            if (! current) return;
            if (! navigator.share) {
                if (! straightAfterMaking) copyLink();
                return;
            }
            navigator.share({ title: meta.name, url: current.url }).catch((e) => {
                // Straight after making the link some phones refuse (too long
                // since the tap) - the Share link button is there for that.
                if (! straightAfterMaking && e && e.name !== 'AbortError') copyLink();
            });
        }

        function copyLink() {
            if (! current) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(current.url).then(() => say('Link copied. Paste it in a message.'), selectLink);
            } else {
                selectLink();
            }
        }

        // No clipboard on a plain http address: select the link so a long
        // press copies it.
        function selectLink() {
            const range = document.createRange();
            range.selectNodeContents(urlBox);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            say('Press and hold the link to copy it.', 'amber');
        }

        if (linkBtn) {
            linkBtn.addEventListener('click', () => {
                if (! navigator.onLine) { say('You are offline. Making a link needs a connection.', 'amber'); return; }
                linkBtn.disabled = true;
                notice.hidden = true;
                request(linksUrl, { method: 'POST', body: JSON.stringify({ days: chosenDays() }) })
                    .then((data) => {
                        current = data.link;
                        urlBox.textContent = current.url;
                        choose.hidden = true;
                        made.hidden = false;
                        loadLinks();
                        shareLink(true);
                    })
                    .catch(e => say(e.message, 'red'))
                    .finally(() => { linkBtn.disabled = false; });
            });
        }

        sendFileBtn.addEventListener('click', () => { if (meta) openElsewhere(meta); });
        $('[data-share-send]', sheet).addEventListener('click', () => shareLink(false));
        $('[data-share-copy]', sheet).addEventListener('click', copyLink);
        $('[data-share-close]', sheet).addEventListener('click', close);
        scrim.addEventListener('click', (e) => { if (e.target === scrim) close(); });
        scrim.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
        opener.addEventListener('click', open);
    }

    function wireAddForm() {
        const form = $('[data-add-form]');
        if (! form) return;

        const camera = $('[data-camera]', form), picker = $('[data-picker]', form);
        const section = $('[data-chosen-section]', form), list = $('[data-chosen]', form);
        // The Upload button sits in the screen's footer, outside the form
        // element (it submits it through its form="" attribute).
        const upload = $('[data-upload]'), label = $('span', upload);

        const chosen = () => Array.from(camera.files || []).concat(Array.from(picker.files || []));

        function size(bytes) {
            return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }

        function draw() {
            const files = chosen();

            section.hidden = ! files.length;
            upload.disabled = ! files.length;
            label.textContent = files.length ? 'Upload ' + files.length + (files.length === 1 ? ' file' : ' files') : 'Upload';

            list.replaceChildren.apply(list, files.map((file) => {
                const row = el('div', 'row');
                const main = el('div', 'row-main');
                const ext = (file.name.split('.').pop() || 'file').slice(0, 4);
                main.appendChild(el('span', 'chip ' + toneOf(ext), ext));
                const text = el('span', 'row-text');
                text.appendChild(el('span', 'row-name', file.name));
                text.appendChild(el('span', 'row-meta', size(file.size)));
                main.appendChild(text);
                row.appendChild(main);
                return row;
            }));
        }

        $('[data-take]', form).addEventListener('click', () => camera.click());
        $('[data-choose]', form).addEventListener('click', () => picker.click());
        $('[data-clear]', form).addEventListener('click', () => { camera.value = ''; picker.value = ''; draw(); });
        camera.addEventListener('change', draw);
        picker.addEventListener('change', draw);

        form.addEventListener('submit', (event) => {
            if (! navigator.onLine) {
                event.preventDefault();
                toast('You are offline. Uploading needs a connection.');
                return;
            }
            // An empty file input would post an empty entry the server can't use.
            if (! camera.files.length) camera.disabled = true;
            if (! picker.files.length) picker.disabled = true;
            upload.disabled = true;
            label.textContent = 'Uploading…';
        });

        draw();
    }

    function wireSignOut() {
        $$('[data-signout]').forEach((form) => {
            let cleared = false;

            form.addEventListener('submit', (event) => {
                if (cleared) return;
                event.preventDefault();

                const count = offline.list().length;
                if (count && ! window.confirm('Signing out removes the ' + count + (count === 1 ? ' file' : ' files') + ' saved on this phone. Sign out?')) return;

                // Send any save not yet in the activity log before the
                // record of it is wiped along with the copies.
                report().then(() => offline.wipe()).then(() => {
                    ls.del('sgo:user');
                    cleared = true;
                    form.submit();
                });
            });
        });
    }

    function wireInstall() {
        const card = $('[data-install]');
        if (! card) return;

        // Home's banner can be dismissed for good, and gives way to the
        // automatic-saving question (one prompt at a time); the entry on
        // the Account screen is always offered.
        const always = card.hasAttribute('data-install-always');
        const installed = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
        if (installed) return;
        if (! always && (ls.get('sgo:install-dismissed', false) || ($('[data-auto-ask]') && ! $('[data-auto-ask]').hidden))) return;

        const button = $('[data-install-btn]', card), iosHint = $('[data-install-ios]', card);
        let prompt = null;

        // Android: hold the browser's own prompt for our button.
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            prompt = event;
            card.hidden = false;
            if (button) button.hidden = false;
        });

        // iPhone has no prompt - it is a manual step from the Share menu.
        if (/iPhone|iPod/.test(navigator.userAgent) && iosHint) {
            card.hidden = false;
            iosHint.hidden = false;
        }

        if (button) {
            button.addEventListener('click', () => {
                if (! prompt) return;
                prompt.prompt();
                prompt.userChoice.then(() => { card.hidden = true; });
            });
        }

        const dismiss = $('[data-install-dismiss]', card);
        if (dismiss) dismiss.addEventListener('click', () => { card.hidden = true; ls.set('sgo:install-dismissed', true); });
    }

    // Phones that can't tell Wi-Fi from mobile data are asked once.
    function wireAutoAsk() {
        const card = $('[data-auto-ask]');
        if (! card || ! canStore || knowsConnection || autoPref() !== null) return;

        card.hidden = false;
        $('[data-auto-yes]', card).addEventListener('click', () => { ls.set('sgo:auto', 'on'); card.hidden = true; sync(true); });
        $('[data-auto-no]', card).addEventListener('click', () => { ls.set('sgo:auto', 'off'); card.hidden = true; });
    }

    function wireNotifications() {
        $$('[data-notification]').forEach((link) => {
            link.addEventListener('click', () => {
                if (URLS.read) post(URLS.read, { id: link.getAttribute('data-notification') }).catch(() => {});
            });
        });

        const all = $('[data-read-all]');
        if (all) {
            all.addEventListener('click', () => {
                post(URLS.read, {}).then(() => location.reload()).catch(() => {});
            });
        }
    }

    /* ----------------------------------- Start ----------------------------------- */

    ready.then(() => {
        // No secure connection, no storage: say so instead of offering it.
        $$('[data-needs-secure]').forEach((node) => { node.hidden = canStore; });
        $$('[data-keep-note], [data-keep-hint]').forEach((node) => { node.hidden = ! canStore; });

        wireKeep();
        wireOfflineScreen();
        wireFileScreen();
        wireShare();
        wireAddForm();
        wireSignOut();
        wireAutoAsk();   // before the install banner, which gives way to it
        wireInstall();
        wireNotifications();

        paintCounts();
        paintSavedMarks();
        document.addEventListener('sgo:offline-changed', () => { paintCounts(); paintSavedMarks(); });

        report();   // anything left unreported from last time
        sync(false);
        window.addEventListener('online', () => { report(); sync(false); });
    });
})();
