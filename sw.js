// BeSix Board service worker — online-only mode.
// Účel: splnit PWA install criteria (manifest + active SW) bez offline cache.
// Všechen provoz teče přes síť, žádné staré soubory v cache → vždy aktuální verze.

const VERSION = 'v1';

self.addEventListener('install', (event) => {
  // Nová verze SW přebírá kontrolu okamžitě (jinak by čekala na zavření tabů).
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    // Vyčisti staré cache, kdyby zbyly z předchozí experimentální verze.
    const keys = await caches.keys();
    await Promise.all(keys.map(k => caches.delete(k)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  // Network-only. SW musí mít fetch handler, jinak ho Chrome nepovažuje
  // za "controlling" a install prompt se nezobrazí.
  event.respondWith(fetch(event.request));
});
