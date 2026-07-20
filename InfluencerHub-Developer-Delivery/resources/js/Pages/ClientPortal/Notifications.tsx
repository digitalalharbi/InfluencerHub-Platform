import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { clientNav } from '@/lib/nav';
import { u } from '@/lib/href';

interface Item {
  id: number; title: string; body: string | null; category: string | null;
  actionUrl: string | null; read: boolean; at: string | null;
}
interface Props { items: Paginated<Item>; unread: number }

export default function ClientNotifications({ items, unread }: Props) {
  const open = (n: Item) => router.post(u(`/notifications/${n.id}/read`));

  return (
    <AppShell heading="الإشعارات" nav={clientNav} portal="client">
      <Head title="الإشعارات" />

      <ListHead eyebrow="بوابة العميل" title="الإشعارات"
        sub="تنبيهات المحتوى والعقود والترشيحات والطلبات الخاصة بحسابك"
        actions={unread > 0
          ? <button onClick={() => router.post(u('/notifications/read-all'))} className="btn btn-sm btn-outline">
              تعليم الكل كمقروء ({unread})
            </button>
          : undefined} />

      {items.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="inbox" size={26} /></span>
          <div className="ih-empty__title">لا إشعارات</div>
          <div className="ih-empty__text">تصلك هنا تنبيهات ما يحتاج انتباهك: محتوى بانتظار اعتمادك، عقد للتوقيع، أو ردّ على طلب.</div>
        </div></div>
      ) : (
        <>
          <div style={{ display: 'grid', gap: '.5rem' }}>
            {items.data.map((n) => (
              <button key={n.id} onClick={() => open(n)} className="card"
                style={{
                  padding: '.8rem 1rem', textAlign: 'start', cursor: 'pointer', border: 0, font: 'inherit',
                  display: 'flex', alignItems: 'flex-start', gap: '.7rem',
                  borderInlineStart: `3px solid ${n.read ? 'transparent' : 'var(--ih-primary)'}`,
                  background: n.read ? 'var(--ih-surface)' : 'var(--ih-primary-soft)',
                }}>
                <Icon name="inbox" size={16} />
                <div style={{ minWidth: 0, flex: 1 }}>
                  <div style={{ fontWeight: n.read ? 500 : 700, fontSize: '.88rem' }}>{n.title}</div>
                  {n.body && <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', marginTop: '.2rem' }}>{n.body}</div>}
                </div>
                <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', whiteSpace: 'nowrap' }}>{n.at ?? ''}</span>
              </button>
            ))}
          </div>
          <div style={{ marginTop: '1rem' }}><Pagination links={items.links} /></div>
        </>
      )}
    </AppShell>
  );
}
