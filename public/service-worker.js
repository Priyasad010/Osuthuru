/**
 * Suwoda HerbHub service worker.
 *
 * Only public things are stored: the app's files, herb pictures and ANONYMOUS
 * copies of the herb dictionary (fetched without cookies), so that nothing
 * about a signed-in customer is ever cached.
 */
const VERSION = 'v3';
const SHELL = `suwoda-shell-${VERSION}`;
const DICTIONARY = `suwoda-dictionary-${VERSION}`;
const LANGUAGES = ['en', 'si'];

const SHELL_FILES = [
    '/offline.html',
    '/manifest.webmanifest',
    '/assets/css/style.css',
    '/assets/js/search.js',
    '/assets/js/pwa.js',
    '/assets/js/safety.js',
    '/assets/js/assistant.js',
    '/assets/images/Logo.jpg',
    '/assets/icons/icon-192.png',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2'
];
const CDN_HOSTS = ['cdn.jsdelivr.net', 'fonts.googleapis.com', 'fonts.gstatic.com'];

const isDictionary = path => path === '/dictionary' || /^\/herb\/\d+$/.test(path);
const copyKey = (path, lang) => `${path}?lang=${lang}`;

async function saveAnonymous(cache, path, lang) {
    const response = await fetch(copyKey(path, lang), { credentials: 'omit', cache: 'no-store' });
    if (!response.ok) {
        return null;
    }
    await cache.put(copyKey(path, lang), response.clone());
    return response.text();
}

/** Store the whole dictionary in both languages. */
async function saveDictionary() {
    const cache = await caches.open(DICTIONARY);
    const pictures = new Set();
    for (const lang of LANGUAGES) {
        const index = await saveAnonymous(cache, '/dictionary', lang);
        if (!index) {
            continue;
        }
        const herbs = new Set(index.match(/\/herb\/\d+/g) || []);
        for (const path of herbs) {
            const page = await saveAnonymous(cache, path, lang);
            (page && page.match(/\/(?:uploads|assets\/images\/catalog)\/herbs\/[\w.-]+/g) || []).forEach(p => pictures.add(p));
        }
    }
    const shell = await caches.open(SHELL);
    await Promise.all([...pictures].map(p => shell.add(p).catch(() => null)));
}

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(SHELL);
        // One unreachable file must not stop the installation
        await Promise.all(SHELL_FILES.map(url => cache.add(new Request(url, { mode: 'cors' })).catch(() => null)));
        await saveDictionary().catch(() => null);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter(k => k.startsWith('suwoda-') && k !== SHELL && k !== DICTIONARY).map(k => caches.delete(k)));
        await self.clients.claim();
    })());
});

// The page tells us which language the visitor is reading
let language = 'en';
self.addEventListener('message', event => {
    if (event.data && LANGUAGES.includes(event.data.language)) {
        language = event.data.language;
        event.waitUntil(caches.open(SHELL).then(c => c.put('/__language', new Response(language))));
    }
});

async function offlineCopy(path) {
    const stored = await (await caches.open(SHELL)).match('/__language');
    const preferred = stored ? await stored.text() : language;
    const cache = await caches.open(DICTIONARY);
    for (const lang of [preferred, ...LANGUAGES]) {
        const hit = await cache.match(copyKey(path, lang));
        if (hit) {
            return hit;
        }
    }
    return null;
}

async function navigate(event, url) {
    try {
        const response = await fetch(event.request);
        if (response.ok && isDictionary(url.pathname)) {
            event.waitUntil(caches.open(DICTIONARY)
                .then(c => Promise.all(LANGUAGES.map(l => saveAnonymous(c, url.pathname, l))))
                .catch(() => null));
        }
        return response;
    } catch (error) {
        const copy = isDictionary(url.pathname) ? await offlineCopy(url.pathname) : null;
        return copy || (await caches.match('/offline.html')) || Response.error();
    }
}

/** Serve the stored file at once and refresh it in the background. */
async function staleWhileRevalidate(event) {
    const cache = await caches.open(SHELL);
    const hit = await cache.match(event.request, { ignoreSearch: false });
    const fresh = fetch(event.request).then(response => {
        if (response.ok || response.type === 'opaque') {
            cache.put(event.request, response.clone());
        }
        return response;
    });
    if (hit) {
        event.waitUntil(fresh.catch(() => null));
        return hit;
    }
    // Offline: a version-stamped address may fall back to the unstamped file
    return fresh.catch(async () => (await cache.match(event.request, { ignoreSearch: true })) || Response.error());
}

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);

    if (url.origin === self.location.origin) {
        if (request.mode === 'navigate') {
            event.respondWith(navigate(event, url));
        } else if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/uploads/herbs/')) {
            event.respondWith(staleWhileRevalidate(event));
        }
    } else if (CDN_HOSTS.includes(url.hostname)) {
        event.respondWith(staleWhileRevalidate(event));
    }
});
