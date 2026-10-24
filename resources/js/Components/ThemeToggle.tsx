import { useEffect, useState } from 'react';
import { useT } from '@/lib/i18n';

/**
 * مبدّل ثيم الواجهة — فاتح/داكن/نظام. موحّد للنظام كله عبر data-theme على <html>،
 * فالشريط واللوحة والبطاقات تتبع نفس الوضع. يُحفظ في localStorage، ويُضبط قبل
 * أوّل طلاء من سكربت الإقلاع في القالب (لا وميض).
 */
type Mode = 'light' | 'dark' | 'system';
const KEY = 'ih.theme';

function readMode(): Mode {
  try {
    const v = localStorage.getItem(KEY);
    return v === 'dark' || v === 'light' || v === 'system' ? v : 'light';
  } catch { return 'light'; }
}
function applyMode(m: Mode) {
  document.documentElement.setAttribute('data-theme', m);
  // لون chrome المتصفّح/PWA يتبع الثيم الحالي (لا بنفسجي)
  const darkPref = typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const dark = m === 'dark' || (m === 'system' && darkPref);
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute('content', dark ? '#11131A' : '#F5F6FA');
}

export function ThemeToggle() {
  const t = useT();
  const [mode, setMode] = useState<Mode>('light');
  useEffect(() => { setMode(readMode()); }, []);

  const choose = (m: Mode) => {
    setMode(m);
    try { localStorage.setItem(KEY, m); } catch { /* ignore */ }
    applyMode(m);
  };

  const opts: { id: Mode; label: string }[] = [
    { id: 'light', label: t('nav.theme_light', 'فاتح') },
    { id: 'dark', label: t('nav.theme_dark', 'داكن') },
    { id: 'system', label: t('nav.theme_system', 'النظام') },
  ];

  return (
    <div className="ih-themetoggle" role="radiogroup" aria-label={t('nav.theme', 'المظهر')}>
      {opts.map((o) => (
        <button
          key={o.id}
          type="button"
          role="radio"
          aria-checked={mode === o.id}
          className={`ih-themetoggle__opt${mode === o.id ? ' is-on' : ''}`}
          onClick={(e) => { e.stopPropagation(); choose(o.id); }}
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}

export default ThemeToggle;
