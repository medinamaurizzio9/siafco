const PREFIX = 'SIAFCO-INVESTMENTS-PWA-';
const CACHE = `${PREFIX}v2`;
const OFFLINE = '/investor-pwa/offline.html';
self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE).then(cache => cache.addAll([
        OFFLINE, '/pwa/icon-192.svg', '/pwa/icon-maskable.svg',
    ])).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys
        .filter(key => (key.startsWith(PREFIX) && key !== CACHE) || key === 'siafco-investor-shell-v1')
        .map(key => caches.delete(key))))
        .then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin || request.method !== 'GET'
        || !(url.pathname === '/mi-inversion' || url.pathname.startsWith('/mi-inversion/'))) return;
    // Private responses are network-only, including documents and photos.
    event.respondWith(fetch(request, {cache: 'no-store'}).catch(async () => {
        if (request.mode !== 'navigate') return Response.error();
        const cache = await caches.open(CACHE);
        return await cache.match(OFFLINE) || Response.error();
    }));
});
