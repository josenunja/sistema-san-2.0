const VERSION = 'sas-offline-v2';
const FILES = [
    '/offline.html',
    '/offline.js',
    '/offline.css',
    '/offline-icon.svg',
];
self.addEventListener('install', (event) =>
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(FILES))),
);
self.addEventListener('activate', (event) =>
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (k) =>
                                k.startsWith('sas-offline-') && k !== VERSION,
                        )
                        .map((k) => caches.delete(k)),
                ),
            ),
    ),
);
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (
        event.request.method !== 'GET' ||
        url.origin !== location.origin ||
        !FILES.includes(url.pathname)
    )
        return;
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    void caches
                        .open(VERSION)
                        .then((cache) => cache.put(event.request, copy));
                }
                return response;
            })
            .catch(() => caches.match(event.request)),
    );
});
