import { Link, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';

/**
 * الهوية البصرية الرسمية لـ InfluencerHub — «الجسر (H)»: عمودان مستديران يمثّلان
 * طرفَي التعاون، ونقطة سماوية بينهما تمثّل المنصّة، داخل بلاطة إنديغو مستديرة.
 *
 * مصدر واحد للشعار في كل النظام (لا وسم <img> ولا رموز متناثرة). SVG للواجهة
 * (لا تحوّل تخطيط، أحجام دقيقة). الألوان والهندسة من حزمة الهوية الرسمية:
 *   البلاطة #5B45E0 · الأعمدة #FFFFFF · النقطة #22D3EE دائمًا.
 * قاعدة صارمة: لا يُلوَّن «هب/Hub» بالسماوي أبدًا — السماوي للنقطة فقط.
 */

// 'auto' يتبع سمة التطبيق (فاتح/داكن) عبر متغيّرات CSS — لِـchrome التطبيق ذي الوضعين.
// البقيّة ألوان ثابتة لأسطح معروفة (تسويق/بريد/تدرّج).
type Surface = 'light' | 'dark' | 'gradient' | 'auto';
type Lang = 'ar' | 'en';

// IDENTITY-02 — product UI lockup: monochrome ink/white mark + tiny chartreuse dot.
// (No purple in the application UI.) Names kept so the markup below is unchanged.
const CYAN = '#3FAE86';       // نقطة زمرّد صغيرة — اللمسة الوحيدة
const INDIGO = '#191A1F';     // مربّع العلامة على سطح فاتح = حبر
const NAVY = '#18191D';
const INDIGO_LIGHT = '#FFFFFF'; // على سطح داكن = أبيض

/**
 * اللغة الحالية من حمولة Inertia المشتركة (locale) — حتميّة وتتبع التبديل فورًا.
 * fallback إلى جذر المستند ثم العربية إن غابت الحمولة (سياق نادر خارج الصفحة).
 */
function useLang(): Lang {
  const locale = (usePage().props as { locale?: string }).locale;
  if (locale) return locale.toLowerCase().startsWith('en') ? 'en' : 'ar';
  if (typeof document !== 'undefined' && document.documentElement.lang.toLowerCase().startsWith('en')) return 'en';
  return 'ar';
}

/** بلاطة أيقونة التطبيق: مربّع إنديغو مستدير + رمز أبيض بنسبة 60٪. زخرفية (aria-hidden). */
export function BrandTile({ size = 40, inverted = false, auto = false }: { size?: number; inverted?: boolean; auto?: boolean }) {
  // auto: العلامة تتبع الثيم عبر التوكنز (المربّع = نصّ المحتوى، الأعمدة = خلفية السطح)،
  // فتبدو حبرية على سطح فاتح وبورسلين على سطح داكن دون منطق JS.
  const square = auto ? 'var(--ih-text)' : inverted ? '#FFFFFF' : INDIGO;
  const cols = auto ? 'var(--ih-nav-bg, var(--ih-surface, #FFFFFF))' : inverted ? INDIGO : '#FFFFFF';
  return (
    <svg width={size} height={size} viewBox="0 0 100 100" aria-hidden="true" focusable="false" style={{ display: 'block', flexShrink: 0 }}>
      <rect width="100" height="100" rx="23.33" fill={square} />
      <g transform="translate(20 20) scale(.6)">
        <rect x="14" y="12" width="22" height="76" rx="7" fill={cols} />
        <rect x="64" y="12" width="22" height="76" rx="7" fill={cols} />
        <circle cx="50" cy="50" r="11" fill={CYAN} />
      </g>
    </svg>
  );
}

interface BrandLogoProps {
  height?: number;
  surface?: Surface;
  lang?: Lang;          // افتراضيًّا يتبع لغة الواجهة
  symbolOnly?: boolean; // البلاطة وحدها (شريط مطويّ/جوال)
  className?: string;
}

/**
 * الشعار الكامل (بلاطة + كلمة). ارتفاع الكلمة = height / 1.2.
 * فاتح: نصّ نيليّ + «هب» إنديغو · داكن: نصّ أبيض + «هب» بنفسجي فاتح · تدرّج: أبيض بالكامل.
 */
export function BrandLogo({ height = 34, surface = 'light', lang, symbolOnly = false, className }: BrandLogoProps) {
  const detected = useLang();
  const l = lang ?? detected;
  const onGradient = surface === 'gradient';

  if (symbolOnly) {
    return (
      <span role="img" aria-label="InfluencerHub" className={className} style={{ display: 'inline-flex' }}>
        <BrandTile size={height} inverted={onGradient || surface === 'dark'} auto={surface === 'auto'} />
      </span>
    );
  }

  const fs = height / 1.2;
  const text = surface === 'auto' ? 'var(--ih-text, #14123A)' : surface === 'light' ? NAVY : '#FFFFFF';
  const hub = onGradient ? '#FFFFFF'
    : surface === 'auto' ? 'var(--ih-text, #191A1F)'
      : surface === 'light' ? INDIGO : INDIGO_LIGHT;
  const wrap: CSSProperties = {
    display: 'inline-flex', alignItems: 'center', gap: fs * 0.42, lineHeight: 1,
    fontSize: fs, color: text, direction: l === 'ar' ? 'rtl' : 'ltr', whiteSpace: 'nowrap',
  };

  // الكلمة نصّ حقيقي يُنطَق بقارئ الشاشة، فلا نضيف aria-label على الغلاف (تفاديًا للتكرار).
  return (
    <span className={className} style={wrap}>
      <BrandTile size={height} inverted={onGradient || surface === 'dark'} auto={surface === 'auto'} />
      {l === 'en' ? (
        <span style={{ fontFamily: "'IBM Plex Sans', sans-serif", fontWeight: 600, letterSpacing: '-0.02em' }}>
          Influencer<span style={{ color: hub }}>Hub</span>
        </span>
      ) : (
        <span style={{ fontFamily: "'IBM Plex Sans Arabic', sans-serif", fontWeight: 700 }}>
          إنفلونسر <span style={{ color: hub }}>هب</span>
        </span>
      )}
    </span>
  );
}

/**
 * شعار قابل للنقر يقود إلى وجهة السياق (لوحة البوابة/الرئيسية) — رابط حقيقي
 * (لا div/onClick) ليكون متاحًا بلوحة المفاتيح. `href` تحدّده مساحة الاستدعاء.
 */
export function BrandLink({ href, label = 'InfluencerHub — الرئيسية', title, ...logo }: BrandLogoProps & { href: string; label?: string; title?: string }): ReactNode {
  return (
    <Link href={href} aria-label={label} title={title} style={{ textDecoration: 'none', display: 'inline-flex', alignItems: 'center' }}>
      <BrandLogo {...logo} />
    </Link>
  );
}
