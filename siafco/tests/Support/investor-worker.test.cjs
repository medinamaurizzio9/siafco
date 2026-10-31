const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function worker(file, offline = false) {
    const listeners = {};
    const deleted = [], written = [], network = [];
    const names = ['SIAFCO-INVESTMENTS-PWA-v1', 'SIAFCO-INVESTMENTS-PWA-v2',
        'siafco-investor-shell-v1', 'siafco-static-v0', 'siafco-static-v1', 'affiliate-private-shell'];
    const cache = {addAll: async urls => written.push(...urls), match: async () => 'offline-shell'};
    vm.runInNewContext(readFileSync(path.join(__dirname, '../../public', file), 'utf8'), {
        self: {location: {origin: 'https://local.test'}, addEventListener: (name, fn) => listeners[name] = fn,
            skipWaiting: async () => {}, clients: {claim: async () => {}}},
        caches: {keys: async () => names, delete: async key => deleted.push(key), open: async () => cache},
        URL, Response, fetch: async (request, options) => {
            network.push({request, options});
            if (offline) throw Error('offline');
            return 'private-network-response';
        },
    });
    return {listeners, deleted, written, network};
}

test('activation deletes only obsolete investor caches; general worker preserves investor caches', async () => {
    const portal = worker('investor-pwa/sw.js');
    let pending;
    portal.listeners.activate({waitUntil: promise => pending = promise}); await pending;
    assert.deepEqual(portal.deleted.sort(), ['SIAFCO-INVESTMENTS-PWA-v1', 'siafco-investor-shell-v1']);
    const general = worker('sw.js');
    general.listeners.activate({waitUntil: promise => pending = promise}); await pending;
    assert.deepEqual(general.deleted, ['siafco-static-v0']);
});

test('install caches only public shell; all private GET requests are network-only', async () => {
    const portal = worker('investor-pwa/sw.js'); let pending;
    portal.listeners.install({waitUntil: promise => pending = promise}); await pending;
    assert.equal(portal.written.length, 3);
    assert.ok(portal.written.every(url => url.startsWith('/pwa/') || url === '/investor-pwa/offline.html'));
    for (const url of ['/mi-inversion/', '/mi-inversion/contratos/1/documentos/2', '/mi-inversion/perfil/foto']) {
        portal.listeners.fetch({request: {url: 'https://local.test' + url, method: 'GET', mode: 'navigate'}, respondWith: promise => pending = promise});
        assert.equal(await pending, 'private-network-response');
        assert.equal(portal.network.at(-1).options.cache, 'no-store');
    }
    assert.equal(portal.written.length, 3);
});

test('unrelated routes, cross-origin and mutations are not intercepted', () => {
    const portal = worker('investor-pwa/sw.js');
    for (const [url, method] of [['https://local.test/mi-inversion-ajena', 'GET'],
        ['https://local.test/mi-perfil', 'GET'], ['https://other.test/mi-inversion/', 'GET'],
        ['https://local.test/mi-inversion/salir', 'POST']]) {
        portal.listeners.fetch({request: {url, method}, respondWith: () => assert.fail('Unexpected interception')});
    }
});

test('offline navigation receives only neutral shell, documents cannot receive cached private content', async () => {
    const portal = worker('investor-pwa/sw.js', true); let pending;
    portal.listeners.fetch({request: {url: 'https://local.test/mi-inversion/', method: 'GET', mode: 'navigate'}, respondWith: promise => pending = promise});
    assert.equal(await pending, 'offline-shell');
    portal.listeners.fetch({request: {url: 'https://local.test/mi-inversion/contratos/1/documentos/2', method: 'GET', mode: 'cors'}, respondWith: promise => pending = promise});
    assert.equal((await pending).type, 'error');
});
