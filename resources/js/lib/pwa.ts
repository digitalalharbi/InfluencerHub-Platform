/**
 * تسجيل Service Worker آمن للإنتاج — يُستدعى من تطبيق Inertia وصفحات Blade.
 *
 * مبادئ الأمان (مطابقة لـsw.js): التنقّل network-first مع بديل offline فقط؛
 * لا تُخزَّن صفحات مُصادَقة ولا بيانات API/مستأجر. فشل التسجيل لا يعطّل التطبيق.
 * عند توفّر نسخة جديدة يُطلَق حدث `ih:update-available` ليعرض التطبيق تنبيهًا هادئًا.
 */
export function registerServiceWorker(): void {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;
  // لا تسجّل على http غير المحلي (SW يتطلّب سياقًا آمنًا)
  if (typeof window !== 'undefined' && window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') return;

  const register = () => {
    navigator.serviceWorker.register('/sw.js').then((reg) => {
      reg.addEventListener('updatefound', () => {
        const sw = reg.installing;
        if (!sw) return;
        sw.addEventListener('statechange', () => {
          if (sw.state === 'installed' && navigator.serviceWorker.controller) {
            window.dispatchEvent(new CustomEvent('ih:update-available'));
          }
        });
      });
    }).catch(() => { /* التسجيل اختياري؛ لا يعطّل التطبيق */ });
  };

  if (document.readyState === 'complete') register();
  else window.addEventListener('load', register, { once: true });
}
