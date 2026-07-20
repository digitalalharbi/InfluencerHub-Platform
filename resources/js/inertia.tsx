import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';
import { setBase } from '@/lib/href';

const appName = 'إنفلونسر هَب';

/**
 * إعلان عطل عامّ — منطقة حيّة يقرأها قارئ الشاشة أيضًا.
 * يُبنى بجافاسكربت لا في الصفحة لأنه يعلو كل صفحة ولا يخصّ واحدة.
 */
function announceFailure(message: string): void {
  const id = 'ih-global-error';
  let box = document.getElementById(id);
  if (!box) {
    box = document.createElement('div');
    box.id = id;
    box.setAttribute('role', 'alert');
    box.setAttribute('aria-live', 'assertive');
    box.className = 'ih-global-error';
    document.body.appendChild(box);
  }
  box.textContent = message;
  box.classList.add('is-visible');
  window.setTimeout(() => box?.classList.remove('is-visible'), 8000);
}

createInertiaApp({
  title: (title) => (title ? `${title} — ${appName}` : appName),
  // Page-level code splitting: each Pages/*.tsx becomes its own lazy chunk.
  resolve: (name) =>
    resolvePageComponent<{ default: ComponentType }>(
      `./Pages/${name}.tsx`,
      import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx'),
    ).then((m) => m.default),
  setup({ el, App, props }) {
    // بادئة التركيب تأتي من الخادم وتتغيّر مع كل تنقّل (/beta ↔ /app أثناء التحويل).
    setBase(props.initialPage.props.base);
    router.on('navigate', (e) => setBase(e.detail.page.props.base));

    // عطل الخادم كان يمرّ صامتًا: يضغط المستخدم «حفظ» فلا يحدث شيء ولا يُقال
    // له لماذا، فيظنّ الزرّ معطّلًا أو عمله محفوظًا. إعلان واحد هنا يغطّي كل
    // نموذج في التطبيق بدل معالجة متكرّرة في كل صفحة.
    // أحداث DOM لا خريطة الأنواع: `exception` و`invalid` ليسا فيها في v3
    window.addEventListener('inertia:exception', () =>
      announceFailure('تعذّر إتمام العملية لعطل في الخادم. لم يُفقد ما أدخلته — أعد المحاولة.'));
    window.addEventListener('inertia:invalid', () =>
      announceFailure('ردّ الخادم بشكل غير متوقّع. أعد تحميل الصفحة ثم حاول مجدّدًا.'));
    if (el) createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#6252E5', showSpinner: false },
});
