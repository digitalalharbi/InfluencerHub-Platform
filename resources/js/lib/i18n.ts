import { usePage } from '@inertiajs/react';

/**
 * ترجمة الواجهة (React) — تقرأ حزمة `translations` المشتركة من Inertia (تُحلّ باللغة
 * الحالية في الخادم). مصدر واحد للحقيقة مع Blade؛ لا مكتبة i18n منفصلة ولا ازدواج.
 *
 * t('navigation.items.dashboard') → السلسلة باللغة الحالية، وإلا fallback، وإلا المفتاح.
 */

type Dict = Record<string, unknown>;

interface SharedI18n {
  locale?: string;
  dir?: 'rtl' | 'ltr';
  translations?: Record<string, Dict>;
}

function lookup(translations: Record<string, Dict>, key: string): string | undefined {
  let cur: unknown = translations;
  for (const part of key.split('.')) {
    if (cur !== null && typeof cur === 'object' && part in (cur as Dict)) {
      cur = (cur as Dict)[part];
    } else {
      return undefined;
    }
  }
  return typeof cur === 'string' ? cur : undefined;
}

/** خطّاف الترجمة — يُعيد دالّة t مربوطة بحزمة الصفحة الحالية. */
export function useT(): (key: string, fallback?: string) => string {
  const props = usePage().props as unknown as SharedI18n;
  const tr = props.translations ?? {};
  return (key, fallback) => lookup(tr, key) ?? fallback ?? key;
}

/** لغة الواجهة الحالية واتّجاهها. */
export function useLocale(): { locale: string; dir: 'rtl' | 'ltr'; isRtl: boolean } {
  const props = usePage().props as unknown as SharedI18n;
  const locale = props.locale ?? 'ar';
  const dir = props.dir ?? (locale === 'ar' ? 'rtl' : 'ltr');
  return { locale, dir, isRtl: dir === 'rtl' };
}
