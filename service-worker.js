/* ================================================================
   SoulServe Service Worker v1.0
   - Cache-first for static assets
   - Network-first for API calls
   - Offline fallback page
================================================================ */

const CACHE_NAME   = 'soulserve-v1';
const OFFLINE_URL  = '/offline.html';

// Static assets to precache
const PRECACHE = [
  '/',
  '/index.html',
  '/offline.html',
  '/css/style.css',
  '/css/dashboard.css',
  '/js/script.js',
  '/js/dashboard.js',
  '/assets/logo.png',
  '/manifest.json'
];

/* ── INSTALL: precache static shell ── */
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(PRECACHE.map(url => new Request(url, {cache: 'reload'}))))
      .then(() => self.skipWaiting())
  );
});

/* ── ACTIVATE: delete old caches ── */
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

/* ── FETCH strategy ── */
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET and cross-origin
  if (request.method !== 'GET') return;
  if (url.origin !== self.location.origin) return;

  // API calls → Network first, fallback to cached JSON
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .then(res => {
          if (res.ok) {
            const clone = res.clone();
            caches.open(CACHE_NAME).then(c => c.put(request, clone));
          }
          return res;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  // Static assets (css, js, images) → Cache first
  if (url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|webp|woff2?)$/)) {
    event.respondWith(
      caches.match(request).then(cached => cached || fetch(request).then(res => {
        const clone = res.clone();
        caches.open(CACHE_NAME).then(c => c.put(request, clone));
        return res;
      }))
    );
    return;
  }

  // HTML pages → Network first, offline fallback
  event.respondWith(
    fetch(request)
      .then(res => {
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE_NAME).then(c => c.put(request, clone));
        }
        return res;
      })
      .catch(() =>
        caches.match(request).then(cached => cached || caches.match(OFFLINE_URL))
      )
  );
});

/* ── BACKGROUND SYNC for offline donations ── */
self.addEventListener('sync', event => {
  if (event.tag === 'sync-donations') {
    event.waitUntil(syncPendingDonations());
  }
});

async function syncPendingDonations() {
  try {
    const db = await openIDB();
    const pending = await getAllPending(db);
    for (const item of pending) {
      try {
        await fetch(item.url, { method: 'POST', body: item.formData });
        await deleteFromIDB(db, item.id);
      } catch (e) { /* retry next sync */ }
    }
  } catch (e) {}
}

function openIDB() {
  return new Promise((res, rej) => {
    const req = indexedDB.open('soulserve-offline', 1);
    req.onupgradeneeded = e => e.target.result.createObjectStore('pending', { keyPath: 'id', autoIncrement: true });
    req.onsuccess = e => res(e.target.result);
    req.onerror   = e => rej(e);
  });
}
function getAllPending(db) {
  return new Promise((res, rej) => {
    const tx = db.transaction('pending', 'readonly');
    const req = tx.objectStore('pending').getAll();
    req.onsuccess = e => res(e.target.result);
    req.onerror   = e => rej(e);
  });
}
function deleteFromIDB(db, id) {
  return new Promise((res, rej) => {
    const tx = db.transaction('pending', 'readwrite');
    tx.objectStore('pending').delete(id).onsuccess = res;
  });
}
