import { useEffect, useRef, useState } from 'react';
import { Icon, type IconName } from '@/Components/Icon';

export interface OverflowItem {
  label: string;
  icon?: IconName;
  onClick?: () => void;
  href?: string;
  danger?: boolean;
}

/**
 * قائمة إجراءات ثانوية («المزيد») — تجمع الأزرار الأقلّ أهميّة تحت زرّ واحد
 * فيبقى في الترويسة إجراء رئيسيّ واحد واضح (نظام PA/Phase B).
 *
 * تعكس نمط قائمة المستخدم المُجرَّب في AppShell: إغلاق بالنقر خارجها وبمفتاح Esc،
 * وضع مطلق آمن للـRTL (insetInlineEnd)، ووصول (role=menu/menuitem, aria-*).
 */
export function OverflowMenu({ items, label = 'المزيد', size = 'sm' }: { items: OverflowItem[]; label?: string; size?: 'sm' | 'xs' }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => { if (!ref.current?.contains(e.target as Node)) setOpen(false); };
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onKey); };
  }, [open]);

  if (items.length === 0) return null;

  return (
    <div ref={ref} style={{ position: 'relative', display: 'inline-block' }}>
      <button
        type="button"
        className={`btn btn-${size} btn-outline`}
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={label}
      >
        {label} <Icon name="chevron-down" size={14} />
      </button>
      {open && (
        <div
          role="menu"
          className="card"
          style={{ position: 'absolute', insetInlineEnd: 0, top: 'calc(100% + 6px)', minWidth: 200, padding: '.35rem', zIndex: 60, boxShadow: 'var(--ih-shadow-lg, 0 12px 32px rgba(16,24,40,.16))' }}
        >
          {items.map((it, i) => {
            const cls = 'ih-menuitem';
            const style: React.CSSProperties = {
              display: 'flex', alignItems: 'center', gap: '.55rem', width: '100%',
              padding: '.55rem .6rem', borderRadius: 8, fontSize: '.85rem', textAlign: 'start',
              background: 'none', border: 0, cursor: 'pointer',
              color: it.danger ? 'var(--ih-danger-ink)' : 'var(--ih-text)', textDecoration: 'none',
            };
            const content = <>{it.icon && <Icon name={it.icon} size={16} />} {it.label}</>;
            const close = () => setOpen(false);
            return it.href ? (
              <a key={i} href={it.href} role="menuitem" className={cls} style={style} onClick={close}>{content}</a>
            ) : (
              <button key={i} type="button" role="menuitem" className={cls} style={style}
                onClick={() => { close(); it.onClick?.(); }}>{content}</button>
            );
          })}
        </div>
      )}
    </div>
  );
}
