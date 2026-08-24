import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

/**
 * تذييل التطبيق العام — عنصر واحد قابل لإعادة الاستخدام يظهر أسفل كل بوّابة
 * مُصادَق عليها (الوكالة/العميل/صانع المحتوى/الشريك/الإدارة). يقرأ الهوية من
 * الخصائص المشتركة (Brand) فلا تُكتب القيم يدويًّا. تنقّل بالتبويب نفسه، أوّليّ
 * الطرف. يبقى في التدفّق الطبيعي أسفل المحتوى — لا يطفو ولا يغطّي الجداول/النماذج.
 */
export default function AppFooter() {
  const { brand } = usePage<SharedProps>().props;
  // مسارات نسبية على نفس المضيف (influencerhub.io) → تُطابق روابط الموقع العامّة.
  const links = [
    { label: 'الخصوصية', href: brand.privacyPath },
    { label: 'الشروط', href: brand.termsPath },
    { label: 'المساعدة', href: brand.helpPath },
  ];

  return (
    <footer className="ih-appfoot" role="contentinfo">
      <nav className="ih-appfoot__links" aria-label="روابط المنتج">
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
