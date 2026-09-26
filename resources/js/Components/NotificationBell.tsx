import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Notif { id: number; title: string; body: string | null; actionUrl: string | null; read: boolean; at: string | null }

/**
 * جرس الإشعارات مع قائمة منسدلة — اطّلاع سريع وتعليم مقروء دون مغادرة الصفحة.
 * البيانات مشتركة من HandleInertiaRequests (recentNotifications + unreadNotifications).
 * النقر على إشعار يستدعي مسار «read» الذي يعلّمه مقروءًا ثم يحوّل إلى وجهته.
 */
export function NotificationBell() {
  const t = useT();
  const props = usePage().props as { unreadNotifications?: number; recentNotifications?: Notif[] };
  const unread = props.unreadNotifications ?? 0;
  const items = props.recentNotifications ?? [];
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

  const openItem = (n: Notif) => { setOpen(false); router.post(u(`/notifications/${n.id}/read`), {}, { preserveScroll: true }); };
  const markAll = () => router.post(u('/notifications/read-all'), {}, { preserveScroll: true });

  return (
    <div ref={ref} style={{ position: 'relative', marginInlineEnd: '.4rem' }}>
      <button
        type="button"
        className="ih-bell"
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={t('common.notifications')}
        title={t('common.notifications')}
        style={{ position: 'relative', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 34, height: 34, borderRadius: 9, color: 'var(--ih-text-muted)', background: 'none', border: 0, cursor: 'pointer' }}
      >
        <Icon name="message-circle" size={18} />
        {unread > 0 && (
          <span style={{ position: 'absolute', top: 2, insetInlineEnd: 2, minWidth: 16, height: 16, padding: '0 4px', borderRadius: 8, background: 'var(--ih-danger)', color: '#fff', fontSize: '.62rem', fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', direction: 'ltr' }}>
            {unread > 99 ? '99+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div role="menu" className="card ih-notifpanel">
          <div className="ih-notifpanel__head">
            <span style={{ fontWeight: 800, fontSize: '.92rem' }}>{t('common.notifications')}</span>
            {unread > 0 && <button type="button" className="ih-notifpanel__markall" onClick={markAll}>{t('common.notif_mark_all')}</button>}
          </div>
          <div className="ih-notifpanel__list">
            {items.length === 0 ? (
              <div className="ih-notifpanel__empty">{t('common.notif_empty')}</div>
            ) : items.map((n) => (
              <button key={n.id} type="button" role="menuitem" className={`ih-notifitem${n.read ? '' : ' is-unread'}`} onClick={() => openItem(n)}>
                <span className="ih-notifitem__dot" aria-hidden="true" />
                <span className="ih-notifitem__main">
                  <span className="ih-notifitem__title">{n.title}</span>
                  {n.body && <span className="ih-notifitem__body">{n.body}</span>}
                  {n.at && <span className="ih-notifitem__at">{n.at}</span>}
                </span>
              </button>
            ))}
          </div>
          <Link href={u('/notifications')} className="ih-notifpanel__foot" onClick={() => setOpen(false)}>
            {t('common.notif_view_all')} <Icon name="chevron-left" size={14} />
          </Link>
        </div>
      )}
    </div>
  );
}
