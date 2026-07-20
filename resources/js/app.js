import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// PWA — تسجيل Service Worker آمن (أصول ثابتة + بديل offline فقط، بلا تخزين بيانات).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then((reg) => {
            // إشعار تحديث هادئ عند توفّر نسخة جديدة
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
    });
}
