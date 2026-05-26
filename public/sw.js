/* Alvora PWA Service Worker — v3: иконки и manifest НЕ кэшируются */
const CACHE_VERSION = 'v3';
const STATIC_CACHE = `alvora-static-${CACHE_VERSION}`;

const PRECACHE_URLS = ['/offline.html'];

const NEVER_CACHE = /^\/(icons\/|manifest\.json|sw\.js)/;
const STATIC_EXTENSIONS = /\.(js|css|woff2?)$/i;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(STATIC_CACHE)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
      .then(() => caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)))
      .then(() => self.clients.claim()),
  );
});

function isNeverCache(pathname) {
  return NEVER_CACHE.test(pathname);
}

async function networkOnly(request) {
  return fetch(request);
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) {
    return cached;
  }

  const response = await fetch(request);
  if (response.ok) {
    const cache = await caches.open(STATIC_CACHE);
    cache.put(request, response.clone());
  }
  return response;
}

async function networkFirst(request) {
  try {
    return await fetch(request);
  } catch {
    if (request.mode === 'navigate') {
      const offline = await caches.match('/offline.html');
      if (offline) {
        return offline;
      }
    }
    throw new Error('offline');
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  if (isNeverCache(url.pathname)) {
    event.respondWith(networkOnly(request));
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirst(request));
    return;
  }

  if (STATIC_EXTENSIONS.test(url.pathname)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  event.respondWith(networkOnly(request));
});

self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
