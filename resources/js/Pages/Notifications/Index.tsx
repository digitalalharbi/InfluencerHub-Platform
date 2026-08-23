import { Head, Link, router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Pagination } from '@/Components/Pagination';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import type { SharedProps } from '@/types';

interface Delivery { channel: string; label: string; tone: string }
interface Item {
  id: number; title: string; body: string | null; category: string; actionUrl: string | null;
  read: boolean; at: string | null; delivery: Delivery[];
}
interface Props { items: { data: Item[]; links: { url: string | null; label: string; active: boolean }[] }; unread: number }

export default function NotificationsIndex({ items, unread }: Props) {
  const flash = usePage<SharedProps>().props.flash;
  const open = (it: Item) => router.post(u(`/notifications/${it.id}/read`), {}, { preserveScroll: true });
  const readAll = () => router.post(u('/notifications/read-all'), {}, { preserveScroll: true });

  return (
    <AppShell heading="الإشعارات">
      <Head title="الإشعارات" />
      {flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{flash.ok}</div>}

      <ListHead eyebrow="التواصل" title="الإشعارات" sub={unread > 0 ? `${unread} غير مقروء` : 'كل الإشعارات مقروءة'}
        actions={<div style={{ display: 'flex', gap: '.5rem' }}>
          <Link href={u('/notifications/preferences')} className="btn btn-sm btn-outline"><Icon name="settings" size={14} /> التفضيلات</Link>
          {unread > 0 && <button onClick={readAll} className="btn btn-sm">تحديد الكل كمقروء</button>}
        </div>} />

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2.6rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>
          <Icon name="message-circle" size={26} /><div style={{ marginTop: '.6rem', fontWeight: 700 }}>لا إشعارات بعد</div>
        </div>
      ) : (
        <div style={{ display: 'grid', gap: '.5rem' }}>
          {items.data.map((it) => (
            <div key={it.id} className="card" style={{ padding: '.8rem 1rem', display: 'flex', gap: '.8rem', alignItems: 'flex-start', borderInlineStart: `3px solid ${it.read ? 'transparent' : 'var(--ih-primary)'}` }}>
              <span style={{ width: 9, height: 9, borderRadius: '50%', marginTop: 6, flexShrink: 0, background: it.read ? 'var(--ih-border)' : 'var(--ih-primary)' }} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.6rem', alignItems: 'baseline' }}>
                  <span style={{ fontWeight: it.read ? 500 : 700, fontSize: '.9rem' }}>{it.title}</span>
                  <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr', whiteSpace: 'nowrap' }}>{it.at}</span>
                </div>
                {it.body && <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', marginTop: '.2rem', lineHeight: 1.6 }}>{it.body}</div>}
                <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', alignItems: 'center', marginTop: '.5rem' }}>
                  <span className="ih-tag" style={{ fontSize: '.62rem' }}>{it.category}</span>
                  {/* حالة التسليم لكل قناة — شفافية بلا أخطاء تقنية */}
                  {it.delivery.map((d, i) => (
                    <span key={i} style={{ display: 'inline-flex', alignItems: 'center', gap: '.25rem' }}>
                      <span style={{ fontSize: '.66rem', color: 'var(--ih-text-muted)' }}>{d.channel}</span>
                      <StatusBadge tone={d.tone} label={d.label} />
                    </span>
                  ))}
                  {it.actionUrl && <button onClick={() => open(it)} className="btn btn-xs btn-outline" style={{ marginInlineStart: 'auto' }}>فتح</button>}
                  {!it.actionUrl && !it.read && <button onClick={() => open(it)} className="btn btn-xs btn-ghost" style={{ marginInlineStart: 'auto' }}>تحديد كمقروء</button>}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <div style={{ marginTop: '1rem' }}><Pagination links={items.links} /></div>
    </AppShell>
  );
}
