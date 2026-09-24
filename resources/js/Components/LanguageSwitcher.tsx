import { router } from '@inertiajs/react';
import { useLocale, useT } from '@/lib/i18n';

/**
 * مبدّل لغة الواجهة (عربي/إنجليزي). ينشر إلى POST /locale الذي يحفظ الاختيار
 * (الجلسة للضيف · users.locale للمُصادَق) ويعيد للصفحة الحالية بلا فقدان سياق.
 * تحديث الاتّجاه (rtl/ltr) يجري تلقائيًّا من الحمولة المشتركة (انظر inertia.tsx).
 */
export function LanguageSwitcher({ className = '' }: { className?: string }) {
  const { locale } = useLocale();
  const t = useT();
  const go = (l: 'ar' | 'en') => {
    if (l !== locale) router.post('/locale', { locale: l }, { preserveScroll: true });
  };

  return (
    <div className={`ih-langswitch ${className}`} role="group" aria-label={t('common.language', 'اللغة')}>
      <button type="button" onClick={() => go('ar')} aria-pressed={locale === 'ar'} className={locale === 'ar' ? 'is-active' : ''} lang="ar">العربية</button>
      <button type="button" onClick={() => go('en')} aria-pressed={locale === 'en'} className={locale === 'en' ? 'is-active' : ''} lang="en">English</button>
    </div>
  );
}
