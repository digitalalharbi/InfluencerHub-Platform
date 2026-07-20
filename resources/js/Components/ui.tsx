import {
  Children, Fragment, cloneElement, isValidElement, useId,
  type CSSProperties, type ReactElement, type ReactNode,
} from 'react';
import { Icon, type IconName } from '@/Components/Icon';

/** تنسيق مبلغ من الوحدات الصغرى (هللة) إلى نص مختصر. */
export function sarShort(minor: number | null | undefined): string {
  const v = (minor ?? 0) / 100;
  if (Math.abs(v) >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
  if (Math.abs(v) >= 1000) return Math.round(v / 1000).toLocaleString('en-US') + 'K';
  return Math.round(v).toLocaleString('en-US');
}

export function numFmt(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1000) return Math.round(n / 1000) + 'K';
  return n.toLocaleString('en-US');
}

const TONE: Record<string, { bg: string; fg: string }> = {
  draft: { bg: 'var(--ih-gray-100)', fg: 'var(--ih-gray-600)' },
  submitted: { bg: 'var(--ih-info-soft)', fg: 'var(--ih-info-ink)' },
  under_review: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
  changes_requested: { bg: '#FFEDD5', fg: '#C2410C' },
  approved: { bg: 'var(--ih-success-soft)', fg: 'var(--ih-success-ink)' },
  active: { bg: 'var(--ih-primary-100)', fg: 'var(--ih-primary-800)' },
  paused: { bg: 'var(--ih-gray-200)', fg: 'var(--ih-gray-700)' },
  rejected: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  completed: { bg: '#D1FAE5', fg: '#047857' },
  archived: { bg: 'var(--ih-gray-100)', fg: 'var(--ih-gray-500)' },
};

export function StatusBadge({ tone, label }: { tone: string; label: string }) {
  const t = TONE[tone] ?? TONE.draft;
  return <span className="badge" style={{ background: t.bg, color: t.fg }}>{label}</span>;
}

export function Bar({ pct, over }: { pct: number; over?: boolean }) {
  return (
    <div className={`ih-bar${over ? ' ih-bar--over' : ''}`}>
      <span style={{ width: `${Math.max(0, Math.min(100, pct))}%` }} />
    </div>
  );
}

export function Kpi({
  label, value, sub, icon, tone, href,
}: {
  label: string; value: ReactNode; sub?: ReactNode; icon: IconName;
  tone?: 'accent' | 'success' | 'warning' | 'danger'; href?: string;
}) {
  const body = (
    <>
      <div className="ih-kpi__top">
        <span className="ih-kpi__label">{label}</span>
        <span className={`ih-kpi__icon${tone ? ` ih-kpi__icon--${tone}` : ''}`}><Icon name={icon} size={18} /></span>
      </div>
      <div className="ih-kpi__value">{value}</div>
      {sub && <div className="ih-kpi__sub">{sub}</div>}
    </>
  );
  if (href) return <a href={href} className="ih-kpi ih-kpi--link" style={{ textDecoration: 'none' }}>{body}</a>;
  return <div className="ih-kpi">{body}</div>;
}

export function ListHead({
  eyebrow, title, sub, actions,
}: { eyebrow: string; title: string; sub?: string; actions?: ReactNode }) {
  return (
    <div className="ih-listhead">
      <div>
        <div className="ih-listhead__eyebrow">{eyebrow}</div>
        <h1 className="ih-listhead__title">{title}</h1>
        {sub && <div className="ih-listhead__sub">{sub}</div>}
      </div>
      {actions && <div className="ih-listhead__actions">{actions}</div>}
    </div>
  );
}

export function Sec({
  title, icon, link, children,
}: { title: string; icon?: IconName; link?: { href: string; label: string }; children: ReactNode }) {
  return (
    <div className="ih-sec">
      <div className="ih-sec__head">
        <span className="ih-sec__title">{icon && <Icon name={icon} size={17} />} {title}</span>
        {link && <a href={link.href} className="ih-sec__link">{link.label}</a>}
      </div>
      {children}
    </div>
  );
}

/* ============================================================================
   حقل نموذج موصول بتسميته
   ============================================================================ */

const CONTROL_TAGS = new Set(['input', 'select', 'textarea']);
const ERR_STYLE: CSSProperties = { color: 'var(--ih-danger-ink)', fontSize: '.74rem', marginTop: '.25rem' };
const HINT_STYLE: CSSProperties = { color: 'var(--ih-text-muted)', fontSize: '.74rem', marginTop: '.25rem' };

/**
 * التسمية المجرّدة (بلا htmlFor) تجعل الحقل «بلا اسم» لدى قارئ الشاشة، كما تُلغي
 * إمكانية النقر على النص للتركيز على الحقل — وكلاهما عائق حقيقي في نماذجنا الطويلة.
 * لذلك يتولّى هذا المكوّن توليد مُعرّف ثابت وربط التسمية والتلميح ورسالة الخطأ به،
 * فتبقى المسؤولية في مكان واحد بدل تكرارها في كل نموذج.
 *
 * الأبناء إمّا عناصر عادية (يُحقن المعرّف تلقائيًا في أول input/select/textarea)،
 * أو دالة حين يكون الحقل مركّبًا (مجموعة خيارات، مكوّن مخصّص). في الحالة المركّبة
 * لا ينفع htmlFor لأن الحاوية ليست عنصر إدخال، لذا تُمرَّر خصائص جاهزة للنشر
 * تربط المجموعة بتسميتها عبر aria-labelledby — وهو المسار الوحيد الذي يُنطق فعليًا.
 */
export interface FieldGroupProps {
  id: string;
  'aria-labelledby': string;
  'aria-describedby': string | undefined;
}

export function Field({
  label, labelStyle, hint, error, required, style, className, children,
}: {
  label: ReactNode;
  labelStyle?: CSSProperties;
  hint?: ReactNode;
  error?: string | null | false;
  required?: boolean;
  style?: CSSProperties;
  className?: string;
  children: ReactNode | ((props: FieldGroupProps) => ReactNode);
}) {
  const id = useId();
  const labelId = `${id}-label`;
  const composite = typeof children === 'function';
  const hintId = hint ? `${id}-hint` : null;
  const errId = error ? `${id}-err` : null;
  const describedBy = [hintId, errId].filter(Boolean).join(' ') || undefined;

  // يُربط أول عنصر تحكّم فقط؛ ما بعده (رسائل، أزرار مساعدة) يبقى كما هو.
  let wired = false;
  const wire = (node: ReactNode): ReactNode => {
    if (wired || !isValidElement(node)) return node;
    if (node.type === Fragment) {
      const inner = (node.props as { children?: ReactNode }).children;
      return <Fragment key={node.key}>{Children.map(inner, wire)}</Fragment>;
    }
    if (typeof node.type !== 'string' || !CONTROL_TAGS.has(node.type)) return node;
    wired = true;
    const props = node.props as Record<string, unknown>;
    return cloneElement(node as ReactElement<Record<string, unknown>>, {
      id: props.id ?? id,
      'aria-required': required || undefined,
      'aria-describedby': describedBy,
    });
  };

  return (
    <div style={style} className={className}>
      <label htmlFor={composite ? undefined : id} id={composite ? labelId : undefined} style={labelStyle}>
        {label}
        {required && <span aria-hidden style={{ color: 'var(--ih-danger-ink)' }}> *</span>}
      </label>
      {composite
        ? children({ id, 'aria-labelledby': labelId, 'aria-describedby': describedBy })
        : Children.map(children, wire)}
      {hint && <div id={hintId ?? undefined} style={HINT_STYLE}>{hint}</div>}
      {error && <div id={errId ?? undefined} role="alert" style={ERR_STYLE}>{error}</div>}
    </div>
  );
}

export function Avatar({ name, round }: { name: string; round?: boolean }) {
  return <span className={`ih-idc__av${round ? ' ih-idc__av--round' : ''}`}>{(name ?? '؟').slice(0, 1)}</span>;
}

export function WorkspaceHeader({
  eyebrow, title, statusTone, statusLabel, back, backLabel, meta, actions,
}: {
  eyebrow?: string; title: string; statusTone?: string; statusLabel?: string;
  back?: string; backLabel?: string; meta?: [string, string][]; actions?: ReactNode;
}) {
  return (
    <div className="ih-ws-header">
      <div className="ih-ws-header__main">
        {back && <a href={back} className="ih-ws-header__back"><span aria-hidden>→</span> {backLabel ?? 'رجوع'}</a>}
        {eyebrow && <div className="ih-ws-header__eyebrow">{eyebrow}</div>}
        <div className="ih-ws-header__title-row">
          <h1 className="ih-ws-header__title">{title}</h1>
          {statusTone && statusLabel && <StatusBadge tone={statusTone} label={statusLabel} />}
        </div>
        {meta && meta.length > 0 && (
          <div className="ih-ws-header__meta">
            {meta.map(([k, v]) => <span key={k} className="ih-ws-header__fact"><span className="ih-ws-header__fact-k">{k}</span> {v}</span>)}
          </div>
        )}
      </div>
      {actions && <div className="ih-ws-header__actions">{actions}</div>}
    </div>
  );
}

export interface SummaryItem { label: string; value: ReactNode; tone?: 'success' | 'warning' | 'danger' | 'primary'; icon?: IconName }
export function SummaryStrip({ items }: { items: SummaryItem[] }) {
  return (
    <div className="ih-summary">
      {items.map((it, i) => (
        <div key={i} className="ih-summary__cell">
          <div className="ih-summary__label">{it.icon && <Icon name={it.icon} size={15} />} {it.label}</div>
          <div className={`ih-summary__value${it.tone ? ` ih-summary__value--${it.tone}` : ''}`}>{it.value}</div>
        </div>
      ))}
    </div>
  );
}

export function Tabs({ tabs, active, onChange }: { tabs: [string, string][]; active: string; onChange: (k: string) => void }) {
  return (
    <div className="ih-chips" style={{ margin: '1.2rem 0 1rem', overflowX: 'auto', flexWrap: 'nowrap', paddingBottom: '.2rem' }}>
      {tabs.map(([key, label]) => (
        <button key={key} onClick={() => onChange(key)} className={`ih-chip${active === key ? ' active' : ''}`}>{label}</button>
      ))}
    </div>
  );
}

export interface WorkTab { key: string; label: string; icon: IconName; count?: number }
/**
 * تنقّل داخلي للمساحات التشغيلية — لاصق عند التمرير، أيقونات، عدّادات صغيرة،
 * تمرير أفقي منظّم، ولا قص للتسميات. العدّاد يظهر فقط عند وجود قيمة.
 */
export function WorkTabs({ tabs, active, onChange }: { tabs: WorkTab[]; active: string; onChange: (k: string) => void }) {
  return (
    <div className="ih-worktabs">
      <div className="ih-worktabs__scroll" role="tablist">
        {tabs.map((t) => {
          const on = active === t.key;
          return (
            <button key={t.key} role="tab" aria-selected={on} onClick={() => onChange(t.key)}
              className={`ih-worktab${on ? ' active' : ''}`}>
              <Icon name={t.icon} size={16} className="ih-worktab__icon" />
              <span className="ih-worktab__label">{t.label}</span>
              {t.count != null && t.count > 0 && <span className="ih-worktab__count">{t.count}</span>}
            </button>
          );
        })}
      </div>
    </div>
  );
}

/* ============================================================================
   شارتات خفيفة (SVG أصلي، بلا مكتبات) — تُستخدم فقط عندما يكون للرسم معنى فعلي
   ============================================================================ */

export interface SeriesPoint { label: string; value: number }

/** مخطط أعمدة أفقي/رأسي بسيط — يعرض القيم الفعلية ولا يخترع نقاطًا. */
export function BarChart({ points, height = 132, format }: { points: SeriesPoint[]; height?: number; format?: (v: number) => string }) {
  const max = Math.max(...points.map((p) => p.value), 1);
  if (points.every((p) => p.value === 0)) {
    return <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.84rem' }}>لا بيانات في هذه الفترة.</div>;
  }
  return (
    <div style={{ display: 'flex', alignItems: 'flex-end', gap: '.5rem', height, padding: '.4rem .2rem 0' }}>
      {points.map((p, i) => {
        const h = Math.max(2, Math.round((p.value / max) * (height - 34)));
        return (
          <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '.3rem', minWidth: 0 }}>
            <span style={{ fontSize: '.64rem', color: 'var(--ih-text-muted)', direction: 'ltr', whiteSpace: 'nowrap' }}>
              {p.value > 0 ? (format ? format(p.value) : p.value.toLocaleString('en-US')) : ''}
            </span>
            <div title={`${p.label}: ${p.value}`} style={{
              width: '100%', height: h, borderRadius: '5px 5px 2px 2px',
              background: p.value === max ? 'var(--ih-primary)' : 'var(--ih-primary-200, var(--ih-primary-soft))',
              transition: 'height var(--ih-motion) var(--ih-ease)',
            }} />
            <span style={{ fontSize: '.66rem', color: 'var(--ih-text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '100%' }}>{p.label}</span>
          </div>
        );
      })}
    </div>
  );
}

/** حلقة توزيع — للنِسَب عندما تكون الفئات قليلة ولها معنى. */
export function DonutChart({ slices, size = 128, centerLabel, centerValue }: {
  slices: { label: string; value: number; color: string }[]; size?: number; centerLabel?: string; centerValue?: string;
}) {
  const total = slices.reduce((t, s) => t + s.value, 0);
  if (total === 0) return <div style={{ padding: '1.4rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.84rem' }}>لا بيانات.</div>;
  const r = size / 2 - 10, c = 2 * Math.PI * r;
  let acc = 0;
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap', justifyContent: 'center' }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ flexShrink: 0 }} role="img" aria-label="توزيع">
        <g transform={`rotate(-90 ${size / 2} ${size / 2})`}>
          {slices.filter((s) => s.value > 0).map((s, i) => {
            const frac = s.value / total, dash = frac * c, gap = c - dash, offset = -acc * c;
            acc += frac;
            return <circle key={i} cx={size / 2} cy={size / 2} r={r} fill="none" stroke={s.color} strokeWidth={14}
              strokeDasharray={`${dash} ${gap}`} strokeDashoffset={offset} />;
          })}
        </g>
        {centerValue && <text x="50%" y="47%" textAnchor="middle" style={{ fontSize: 19, fontWeight: 800, fill: 'var(--ih-text)' }}>{centerValue}</text>}
        {centerLabel && <text x="50%" y="62%" textAnchor="middle" style={{ fontSize: 10, fill: 'var(--ih-text-muted)' }}>{centerLabel}</text>}
      </svg>
      <div style={{ display: 'grid', gap: '.35rem', minWidth: 130 }}>
        {slices.filter((s) => s.value > 0).map((s, i) => (
          <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '.45rem', fontSize: '.8rem' }}>
            <span style={{ width: 9, height: 9, borderRadius: 3, background: s.color, flexShrink: 0 }} />
            <span style={{ flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.label}</span>
            <span style={{ fontWeight: 700, direction: 'ltr' }}>{s.value}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

/**
 * لوحة الانتظار — تقول من الكرة في ملعبه وما ينتظره.
 *
 * الحالات التي تنتظر الطرف الآخر كانت تعرض قائمة إجراءات فارغة بلا تفسير،
 * فتبدو عطلًا أو نقص صلاحية. الانتظار مشروع، لكنه يُعلَن لا يُستنتَج.
 */
export function WaitingNotice({
  waiting,
}: {
  waiting: { party: string; expects: string; canRemind: boolean } | null
}) {
  if (!waiting) return null

  return (
    <div
      role="status"
      style={{
        display: 'flex', alignItems: 'flex-start', gap: '.7rem',
        padding: '.85rem 1.05rem', marginBottom: '1rem',
        borderRadius: 'var(--ih-radius-sm)',
        background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)',
        borderInlineStart: '3px solid var(--ih-info)', fontSize: '.85rem', lineHeight: 1.75,
      }}
    >
      <Icon name="clipboard-check" size={16} />
      <span>
        الدور الآن على <b>{waiting.party}</b> — بانتظار {waiting.expects}.
        {!waiting.canRemind && ' لا إجراء مطلوب منك حاليًّا.'}
      </span>
    </div>
  )
}
