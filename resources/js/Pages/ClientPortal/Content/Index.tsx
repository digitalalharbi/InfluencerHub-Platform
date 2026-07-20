import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; number: string; title: string; type: string; platform: string | null;
  creator: string | null; campaign: string | null; status: string; statusLabel: string; statusTone: string;
  mediaUrl: string | null; version: number; publishedAt: string | null; awaiting: boolean;
}
interface Props { clientName: string; items: Paginated<Row>; awaiting: number; canReview: boolean }

export default function ClientContentIndex({ clientName, items, awaiting }: Props) {
  return (
    <AppShell heading="المحتوى" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title="المحتوى" />

      <ListHead eyebrow="بوابة العميل" title="المحتوى"
        sub="راجع المحتوى المُرسَل لك واعتمده أو اطلب تعديلًا." />

      <div className="ih-kpis">
        <Kpi label="بانتظار اعتمادك" icon="clipboard-check" tone={awaiting ? 'warning' : 'success'}
          value={awaiting.toLocaleString('en-US')} sub={awaiting ? 'يحتاج مراجعتك' : 'لا شيء معلّق'} />
        <Kpi label="إجمالي المحتوى" icon="image" value={items.total.toLocaleString('en-US')} sub="في نطاقك" />
      </div>

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا محتوى بعد.</div>
      ) : (
        <>
          {/* معرض مراجعة — العميل يرى المحتوى قبل أن يقرّر */}
          <div className="ih-gallery">
            {items.data.map((it) => (
              <Link key={it.id} href={u(`/content/${it.id}`)} className="ih-gtile" style={{ textDecoration: 'none', color: 'inherit' }}>
                <div className="ih-gtile__thumb">
                  {it.mediaUrl ? <img src={it.mediaUrl} alt="" loading="lazy" /> : <Icon name="image" size={26} />}
                  <span className="ih-gtile__badge"><StatusBadge tone={it.statusTone} label={it.statusLabel} /></span>
                  {it.version > 1 && <span className="ih-gtile__ver">v{it.version}</span>}
                </div>
                <div className="ih-gtile__body">
                  <div className="ih-gtile__title">{it.title}</div>
                  <div className="ih-gtile__meta">{it.creator ?? '—'}{it.platform ? ` · ${it.platform}` : ''}</div>
                  {it.campaign && <div className="ih-gtile__meta" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{it.campaign}</div>}
                  <div style={{ marginTop: 'auto', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '.4rem', paddingTop: '.4rem' }}>
                    <span style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{it.publishedAt ?? it.number}</span>
                    {it.awaiting && <span className="btn btn-xs btn-primary" style={{ pointerEvents: 'none' }}>مراجعة</span>}
                  </div>
                </div>
              </Link>
            ))}
          </div>
          <div style={{ marginTop: '1rem' }} />
          <Pagination links={items.links} />
        </>
      )}
    </AppShell>
  );
}
