import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Icon, type IconName } from '@/Components/Icon';

/**
 * مكتبة مكوّنات RADICAL-IDENTITY-01 المشتركة — نظام تصميم واحد لكل الصفحات.
 * هوية Eduplex: حبر/كحلي + أوف-وايت دافئ + أبيض + ليموني حمضي. لا إعادة اختراع صفحةً صفحة.
 */

export type Tone = 'neutral' | 'primary' | 'warning' | 'danger' | 'success' | 'accent';

/** مؤشّر مضغوط: رقم كبير + شريحة أيقونة + حالة دقيقة. */
export function MetricTile({ label, value, icon, tone = 'neutral', hint, href }: {
  label: string; value: ReactNode; icon: IconName; tone?: Tone; hint?: ReactNode; href?: string;
}) {
  const body = (
    <>
      <span className={`ih-tile__icon ih-tile__icon--${tone}`}><Icon name={icon} size={18} /></span>
      <span className="ih-tile__label">{label}</span>
      <span className="ih-tile__value">{value}</span>
      {hint != null && <span className="ih-tile__hint">{hint}</span>}
    </>
  );
  return href ? <Link href={href} className="ih-tile ih-tile--link">{body}</Link> : <div className="ih-tile">{body}</div>;
}

/** بطاقة محتواها كتلة بصرية (رسم/قائمة)، برأس عنوان + رابط اختياري. */
export function ChartCard({ title, sub, icon, action, children, className }: {
  title: string; sub?: string; icon?: IconName; action?: { href: string; label: string }; children: ReactNode; className?: string;
}) {
  return (
    <section className={`ih-vcard${className ? ` ${className}` : ''}`}>
      <div className="ih-vcard__head">
        <div>
          <h2 className="ih-vcard__title">{icon && <Icon name={icon} size={16} />}{title}</h2>
          {sub && <div className="ih-vcard__sub">{sub}</div>}
        </div>
        {action && <Link href={action.href} className="ih-vcard__link">{action.label}<Icon name="chevron-left" size={13} /></Link>}
      </div>
      {children}
    </section>
  );
}

/** شريط عنوان قسم — أيقونة + عنوان + عدّاد/رابط اختياري. */
export function SectionBar({ title, icon, count, action }: {
  title: string; icon?: IconName; count?: number; action?: { href: string; label: string };
}) {
  return (
    <div className="ih-sectionbar">
      <h2 className="ih-sectionbar__title">{icon && <Icon name={icon} size={18} />} {title}{count != null && count > 0 && <span className="ih-sectionbar__count">{count}</span>}</h2>
      {action && <Link href={action.href} className="ih-vcard__link">{action.label}<Icon name="chevron-left" size={13} /></Link>}
    </div>
  );
}

/** بطاقة بيضاء لعمود السياق المدمج (اليوم/السياق/الإجراءات). */
export function RailCard({ title, icon, children }: { title: string; icon: IconName; children: ReactNode }) {
  return (
    <div className="ih-rail__group">
      <div className="ih-rail__title"><Icon name={icon} size={14} /> {title}</div>
      {children}
    </div>
  );
}
