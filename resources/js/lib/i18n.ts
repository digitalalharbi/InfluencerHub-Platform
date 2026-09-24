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

/** استبدال العناصر النائبة على نمط Laravel: «:name» → القيمة. */
function fill(text: string, params?: Record<string, string | number>): string {
  if (!params) return text;
  return text.replace(/:(\w+)/g, (m, k) => (k in params ? String(params[k]) : m));
}

/**
 * خطّاف الترجمة — يُعيد دالّة t مربوطة بحزمة الصفحة الحالية.
 * t(key) أو t(key, fallback) أو t(key, params) أو t(key, fallback, params).
 */
export function useT(): (key: string, fallbackOrParams?: string | Record<string, string | number>, params?: Record<string, string | number>) => string {
  const props = usePage().props as unknown as SharedI18n;
  const tr = props.translations ?? {};
  return (key, fallbackOrParams, params) => {
    const fallback = typeof fallbackOrParams === 'string' ? fallbackOrParams : undefined;
    const p = typeof fallbackOrParams === 'object' ? fallbackOrParams : params;
    return fill(lookup(tr, key) ?? fallback ?? key, p);
  };
}

/** لغة الواجهة الحالية واتّجاهها. */
export function useLocale(): { locale: string; dir: 'rtl' | 'ltr'; isRtl: boolean } {
  const props = usePage().props as unknown as SharedI18n;
  const locale = props.locale ?? 'ar';
  const dir = props.dir ?? (locale === 'ar' ? 'rtl' : 'ltr');
  return { locale, dir, isRtl: dir === 'rtl' };
}
