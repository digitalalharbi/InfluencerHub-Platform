import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; name: string; number: string; client: string | null; brand: string | null; budgetMinor: number;
  hasShortlist: boolean; version: number | null; slStatus: string | null; slLabel: string; slTone: string; pending: number;
}
interface Props { campaigns: Paginated<Row>; filters: { q: string | null }; summary: { total: number; awaitingClient: number } }

const money = (m: number) => (m / 100).toLocaleString('en-US') + ' ر.س';

export default function ShortlistingIndex({ campaigns, filters, summary }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const search = () => router.get(u('/shortlisting'), { q: q || undefined }, { preserveState: true, replace: true });

  return (
    <AppShell heading="الترشيحات">
      <Head title="الترشيحات" />
      <ListHead eyebrow="الحملات" title="الترشيحات" sub="اختر حملة لبدء اختيار المؤثرين أو متابعة قرار العميل." />

      <div className="ih-kpis">
        <Kpi label="الحملات" icon="megaphone" value={summary.total.toLocaleString('en-US')} sub="قابلة للترشيح" />
        <Kpi label="بانتظار العميل" icon="clipboard-check" tone={summary.awaitingClient ? 'warning' : 'success'}
          value={summary.awaitingClient.toLocaleString('en-US')} sub="قوائم مُرسَلة" />
      </div>

      <div className="ih-filterbar" style={{ marginBottom: '1rem' }}>
        <div className="ih-search">
          <Icon name="search" size={15} />
          <input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && search()} placeholder="ابحث بالحملة أو العميل…" />
        </div>
        <button onClick={search} className="btn btn-sm">بحث</button>
      </div>

      {campaigns.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا حملات مطابقة.</div>
      ) : (
        <>
          {/* بطاقات اختيار — حالة الترشيح لكل حملة والإجراء المباشر */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '.9rem', marginBottom: '1.1rem' }}>
            {campaigns.data.map((c) => (
              <div key={c.id} className="ih-wcard" style={{ cursor: 'default' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', alignItems: 'flex-start' }}>
                  <div style={{ minWidth: 0 }}>
                    <div className="ih-wcard__title">{c.name}</div>
                    <div className="ih-wcard__meta">{c.client ?? '—'}{c.brand ? ` · ${c.brand}` : ''}</div>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.35rem', flexShrink: 0 }}>
                    <StatusBadge tone={c.slTone} label={c.version ? `${c.slLabel} · v${c.version}` : c.slLabel} />
                    {c.pending > 0 && <span className="ih-nav__badge">{c.pending}</span>}
                  </div>
                </div>
                <div className="ih-wcard__row">
                  <span style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
                    الميزانية <b style={{ direction: 'ltr', color: 'var(--ih-text)' }}>{c.budgetMinor ? money(c.budgetMinor) : '—'}</b>
                  </span>
                  <Link href={u(`/campaigns/${c.id}/shortlist`)} className={`btn btn-xs ${c.hasShortlist ? 'btn-outline' : 'btn-primary'}`}>
                    {c.hasShortlist ? 'فتح الترشيح' : 'بدء ترشيح'}
                  </Link>
                </div>
                {c.pending > 0 && <div className="ih-wcard__risk" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>{c.pending} مؤثر بانتظار قرار العميل</div>}
              </div>
            ))}
          </div>
          <Pagination links={campaigns.links} />
        </>
      )}
    </AppShell>
  );
}
