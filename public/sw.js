const CACHE_NAME = 'mishwar-v1';
const TILE_CACHE_NAME = 'mishwar-map-tiles-v1';

const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/images/sample-ajala.svg',
    'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap',
    'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css',
    'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => console.warn('PWA preload cache notice:', err));
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME && key !== TILE_CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // 1. OpenStreetMap Tile Caching Strategy: Cache-First with Network Fallback
    if (url.hostname.includes('tile.openstreetmap.org')) {
        event.respondWith(
            caches.open(TILE_CACHE_NAME).then(async (cache) => {
                const cachedResponse = await cache.match(event.request);
                if (cachedResponse) {
                    return cachedResponse;
                }
                try {
                    const networkResponse = await fetch(event.request);
                    if (networkResponse.ok) {
                        cache.put(event.request, networkResponse.clone());
                    }
                    return networkResponse;
                } catch (error) {
                    // Offline fallback: return cached tile if available
                    return cachedResponse || new Response('', { status: 408 });
                }
            })
        );
        return;
    }

    // 2. Gazetteer and Static Asset Caching: Stale-While-Revalidate
    if (url.pathname.includes('/api/gazetteer') || url.pathname.includes('/images/')) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async (cache) => {
                const cachedResponse = await cache.match(event.request);
                const fetchPromise = fetch(event.request).then((networkResponse) => {
                    if (networkResponse.ok) {
                        cache.put(event.request, networkResponse.clone());
                    }
                    return networkResponse;
                }).catch(() => cachedResponse);

                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // 3. Default: Network first, fall back to cache for HTML pages
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                return await cache.match('/') || new Response('Offline in Gaza', { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
            })
        );
    }
});
