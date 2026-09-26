import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';
import { useT } from '@/lib/i18n';

/**
 * تذييل التطبيق العام — عنصر واحد قابل لإعادة الاستخدام يظهر أسفل كل بوّابة
 * مُصادَق عليها (الوكالة/العميل/صانع المحتوى/الشريك/الإدارة). يقرأ الهوية من
 * الخصائص المشتركة (Brand) فلا تُكتب القيم يدويًّا. تنقّل بالتبويب نفسه، أوّليّ
 * الطرف. يبقى في التدفّق الطبيعي أسفل المحتوى — لا يطفو ولا يغطّي الجداول/النماذج.
 */
export default function AppFooter() {
  const t = useT();
  const { brand } = usePage<SharedProps>().props;
  // مسارات نسبية على نفس المضيف (influencerhub.io) → تُطابق روابط الموقع العامّة.
  const links = [
    { label: t('common.privacy'), href: brand.privacyPath },
    { label: t('common.terms'), href: brand.termsPath },
    { label: t('common.help'), href: brand.helpPath },
  ];

  return (
    <footer className="ih-appfoot" role="contentinfo">
      <nav className="ih-appfoot__links" aria-label={t('common.footer_links')}>
        {links.map((l) => (
          <a key={l.href} href={l.href} className="ih-appfoot__link">{l.label}</a>
        ))}
      </nav>
      <span className="ih-appfoot__copy">
        © {brand.year} {brand.name}
        <span className="ih-appfoot__dot" aria-hidden="true"> · </span>
        <a href={brand.url} className="ih-appfoot__link ih-appfoot__domain" target="_blank" rel="noopener noreferrer">{brand.domain}</a>
      </span>
    </footer>
  );
}
