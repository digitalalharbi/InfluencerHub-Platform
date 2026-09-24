import type { ReactNode } from 'react';
import { useLocale } from '@/lib/i18n';

/**
 * طبقة الرسوم الوحيدة (SVG، بلا مكتبة خارجية) — تدعم RTL/LTR واستجابة وحالة فارغة وتلميحات
 * ووصولًا. دلالة ثابتة: الحلقة/الدونات = تركيب · الشريط = مقارنة · حلقة التقدّم = إكمال ·
 * الخط الصغير = زمن. لا تُختلَق بيانات؛ عند غياب القيَم تُعرَض حالة فارغة صادقة.
 *
 * الألوان من رموز الهوية (إنديغو أساس؛ السماوي لمسة صغيرة فقط؛ أخضر/كهرماني/أحمر دلاليّ).
 */

const TAU = Math.PI * 2;

export interface Segment { label: string; value: number; color: string; }

/** حلقة/دونات — تركيب فئات. القيَم صفرية كلّها ⇒ حالة فارغة. */
export function Donut({
  segments, size = 132, thickness = 16, centerValue, centerLabel, emptyLabel = 'لا بيانات بعد', ariaLabel,
}: {
  segments: Segment[]; size?: number; thickness?: number;
  centerValue?: ReactNode; centerLabel?: string; emptyLabel?: string; ariaLabel?: string;
}) {
  const total = segments.reduce((s, x) => s + Math.max(0, x.value), 0);
  const r = (size - thickness) / 2;
  const cx = size / 2;
  const circ = TAU * r;
  const label = ariaLabel ?? segments.map((s) => `${s.label}: ${s.value}`).join('، ');

  let offset = 0;
  return (
    <div style={{ display: 'inline-grid', placeItems: 'center', position: 'relative', width: size, height: size }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} role="img" aria-label={label} style={{ transform: 'rotate(-90deg)' }}>
        <circle cx={cx} cy={cx} r={r} fill="none" stroke="var(--ih-border, #E8E6F7)" strokeWidth={thickness} />
        {total > 0 && segments.map((s, i) => {
          const frac = Math.max(0, s.value) / total;
          const len = frac * circ;
          const seg = (
            <circle key={i} cx={cx} cy={cx} r={r} fill="none" stroke={s.color} strokeWidth={thickness}
              strokeDasharray={`${len} ${circ - len}`} strokeDashoffset={-offset} strokeLinecap={frac < 1 ? 'butt' : 'round'} />
          );
          offset += len;
          return seg;
        })}
      </svg>
      <div style={{ position: 'absolute', textAlign: 'center', lineHeight: 1.1 }}>
        {total > 0 ? (
          <>
            {centerValue != null && <div style={{ fontWeight: 800, fontSize: size * 0.2, fontVariantNumeric: 'tabular-nums' }}>{centerValue}</div>}
            {centerLabel && <div style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)', marginTop: 2 }}>{centerLabel}</div>}
          </>
        ) : (
          <div style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)', maxWidth: size - thickness * 2 }}>{emptyLabel}</div>
        )}
      </div>
    </div>
  );
}

/** وسيلة إيضاح مضغوطة للدونات (نقطة + تسمية + قيمة). */
export function Legend({ segments }: { segments: Segment[] }) {
  return (
    <div style={{ display: 'grid', gap: '.35rem' }}>
      {segments.map((s, i) => (
        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '.5rem', fontSize: '.82rem' }}>
          <span style={{ width: 10, height: 10, borderRadius: 3, background: s.color, flexShrink: 0 }} />
          <span style={{ color: 'var(--ih-text-muted)', flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.label}</span>
          <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{s.value.toLocaleString('en-US')}</span>
        </div>
      ))}
    </div>
  );
}

/** حلقة تقدّم — إكمال (0–100). */
export function ProgressRing({
  value, size = 108, thickness = 12, label, tone = 'primary',
}: { value: number; size?: number; thickness?: number; label?: string; tone?: 'primary' | 'success' | 'warning' | 'danger' }) {
  const { locale } = useLocale();
  const pctSign = locale === 'ar' ? '٪' : '%';
  const pct = Math.max(0, Math.min(100, value));
  const r = (size - thickness) / 2;
  const cx = size / 2;
  const circ = TAU * r;
  const dash = (pct / 100) * circ;
  const color = tone === 'success' ? 'var(--ih-success, #16a34a)'
    : tone === 'warning' ? 'var(--ih-warning-ink, #B54708)'
      : tone === 'danger' ? 'var(--ih-danger, #D92D20)' : 'var(--ih-primary, #5B45E0)';
  return (
    <div style={{ display: 'inline-grid', placeItems: 'center', position: 'relative', width: size, height: size }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} role="img" aria-label={`${label ?? ''}: ${Math.round(pct)}${pctSign}`} style={{ transform: 'rotate(-90deg)' }}>
        <circle cx={cx} cy={cx} r={r} fill="none" stroke="var(--ih-border, #E8E6F7)" strokeWidth={thickness} />
        <circle className="ih-ring-arc" cx={cx} cy={cx} r={r} fill="none" stroke={color} strokeWidth={thickness}
          strokeDasharray={`${dash} ${circ - dash}`} strokeLinecap="round" />
      </svg>
      <div style={{ position: 'absolute', textAlign: 'center', lineHeight: 1.1 }}>
        <div style={{ fontWeight: 800, fontSize: size * 0.22, fontVariantNumeric: 'tabular-nums' }}>{Math.round(pct)}<span style={{ fontSize: size * 0.12 }}>{pctSign}</span></div>
        {label && <div style={{ fontSize: '.66rem', color: 'var(--ih-text-muted)', marginTop: 2 }}>{label}</div>}
      </div>
    </div>
  );
}

/** أشرطة أفقية — مقارنة فئات (آمنة RTL: التسمية في البداية والشريط يمتدّ منطقيًّا). */
export function Bars({
  bars, emptyLabel = 'لا بيانات بعد',
}: { bars: { label: string; value: number; color?: string }[]; emptyLabel?: string }) {
  const max = Math.max(1, ...bars.map((b) => b.value));
  if (bars.length === 0 || bars.every((b) => b.value === 0)) {
    return <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', padding: '.6rem 0' }}>{emptyLabel}</div>;
  }
  return (
    <div style={{ display: 'grid', gap: '.55rem' }}>
      {bars.map((b, i) => (
        <div key={i} style={{ display: 'grid', gridTemplateColumns: '7rem 1fr auto', alignItems: 'center', gap: '.6rem', fontSize: '.82rem' }}>
          <span style={{ color: 'var(--ih-text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={b.label}>{b.label}</span>
          <span className="ih-bar" style={{ height: 8 }} role="img" aria-label={`${b.label}: ${b.value}`}>
            <span style={{ width: `${(b.value / max) * 100}%`, background: b.color ?? 'var(--ih-primary)' }} />
          </span>
          <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{b.value.toLocaleString('en-US')}</span>
        </div>
      ))}
    </div>
  );
}

/** خطّ صغير (sparkline) — اتّجاه زمنيّ حقيقي فقط. */
export function Sparkline({
  points, width = 120, height = 32, color = 'var(--ih-primary)',
}: { points: number[]; width?: number; height?: number; color?: string }) {
  if (points.length < 2) return null;
  const max = Math.max(...points);
  const min = Math.min(...points);
  const span = max - min || 1;
  const step = width / (points.length - 1);
  const d = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${(i * step).toFixed(1)},${(height - ((p - min) / span) * height).toFixed(1)}`).join(' ');
  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} role="img" aria-label="اتّجاه زمنيّ" style={{ display: 'block' }}>
      <path d={d} fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

/** هيكل تحميل عظميّ (skeleton) — لا قيَم زائفة أثناء الانتظار. */
export function Skeleton({ width = '100%', height = 16, radius = 8 }: { width?: number | string; height?: number; radius?: number }) {
  return <span className="ih-skeleton" style={{ display: 'block', width, height, borderRadius: radius }} aria-hidden="true" />;
}
