/* Service worker for the admin dashboard.
 * Goal: the app opens instantly and still opens with no network. Data is never
 * cached — a stale order list is worse than an error — so only the shell and
 * the build output live here. Bump VERSION to retire every old cache.
 */
const VERSION = 'v1'
const SHELL = `sahel-shell-${VERSION}`
const STATIC = `sahel-static-${VERSION}`
const SHELL_URL = '/index.html'

// Same origin, but not ours to touch: the API, uploaded files, the API docs,
// the Reverb socket, and the customer app, which is a separate build.
const BYPASS = [/^\/api\//, /^\/storage\//, /^\/docs/, /^\/telescope/, /^\/app\//, /^\/customer\//]

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL)
      .then((cache) => cache.addAll([SHELL_URL, '/manifest.webmanifest', '/favicon.svg']))
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== SHELL && key !== STATIC).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  )
})

async function networkFirstShell(request) {
  try {
    const response = await fetch(request)
    const cache = await caches.open(SHELL)
    await cache.put(SHELL_URL, response.clone())
    return response
  } catch {
    const cached = await caches.match(SHELL_URL)
    return cached ?? Response.error()
  }
}

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request)
  if (cached) return cached
  const response = await fetch(request)
  if (response.ok) {
    const cache = await caches.open(cacheName)
    await cache.put(request, response.clone())
  }
  return response
}

self.addEventListener('fetch', (event) => {
  const { request } = event
  if (request.method !== 'GET') return

  const url = new URL(request.url)
  if (url.origin !== self.location.origin) return
  if (BYPASS.some((pattern) => pattern.test(url.pathname))) return

  // A navigation goes to the network first so a new deploy is picked up, and
  // falls back to the cached shell when there is none.
  if (request.mode === 'navigate') {
    event.respondWith(networkFirstShell(request))
    return
  }

  // Build output is content-hashed, so a name that matches is the right file.
  if (url.pathname.startsWith('/assets/') || /\.(?:woff2?|png|svg|ico|webmanifest)$/.test(url.pathname)) {
    event.respondWith(cacheFirst(request, STATIC))
  }
})
