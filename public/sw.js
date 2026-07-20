/* InfluencerHub Service Worker — آمن ومحافظ:
   - يخزّن فقط الأصول الثابتة (/build/, /icons/, الخطوط) بإستراتيجية cache-first.
   - طلبات التنقّل: network-first مع بديل offline.html — لا يخزّن أبدًا صفحات مُصادَقة أو بيانات.
   - لا يخزّن /api ولا أي محتوى مستأجر/مالي. يمسح الكاش القديم عند التفعيل. */
const VERSION = 'ih-v1';
const STATIC_CACHE = `ih-static-${VERSION}`;
const PRECACHE = ['/offline.html', '/icons/ih-icon.svg', '/manifest.webmanifest'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((k) => k.startsWith('ih-static-') && k !== STATIC_CACHE).map((k) => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

function isCacheableAsset(url) {
  return url.origin === self.location.origin
    ? (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/'))
    : (url.hostname.endsWith('fonts.googleapis.com') || url.hostname.endsWith('fonts.gstatic.com'));
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return; // لا نلمس POST/PUT/DELETE
  const url = new URL(request.url);

  // 1) أصول ثابتة → cache-first (لا بيانات حساسة فيها)
  if (isCacheableAsset(url)) {
    event.respondWith(
      caches.match(request).then((hit) => hit || fetch(request).then((res) => {
        if (res.ok && (res.type === 'basic' || res.type === 'cors')) {
          const copy = res.clone(); caches.open(STATIC_CACHE).then((c) => c.put(request, copy));
        }
        return res;
      }).catch(() => hit))
    );
    return;
  }

  // 2) التنقّل (صفحات) → network-first، بديل offline عند فشل الشبكة فقط. لا تخزين.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // 3) كل ما عدا ذلك (بما فيه /api و/app و/client …) → الشبكة مباشرةً بلا تخزين.
});

// تحديث فوري عند طلب العميل
self.addEventListener('message', (e) => { if (e.data === 'SKIP_WAITING') self.skipWaiting(); });
