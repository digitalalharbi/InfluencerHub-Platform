import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Row {
  id: number; number: string; title: string; type: string; platform: string | null;
  creator: string | null; campaign: string | null; status: string; statusLabel: string; statusTone: string;
  mediaUrl: string | null; version: number; publishedAt: string | null; awaiting: boolean;
}
interface Props { clientName: string; items: Paginated<Row>; awaiting: number; canReview: boolean }

export default function ClientContentIndex({ clientName, items, awaiting }: Props) {
  const t = useT();
  return (
    <AppShell heading={t('client_portal.content_title')} nav={clientNav} portal="client" wsName={clientName} wsPlan={t('client_portal.ws_plan')}>
      <Head title={t('client_portal.content_title')} />

      <ListHead eyebrow={t('client_portal.eyebrow')} title={t('client_portal.content_title')}
        sub={t('client_portal.content_sub')} />

      <div className="ih-kpis">
        <Kpi label={t('client_portal.c_kpi_awaiting')} icon="clipboard-check" tone={awaiting ? 'warning' : 'success'}
          value={awaiting.toLocaleString('en-US')} sub={awaiting ? t('client_portal.c_kpi_awaiting_sub') : t('client_portal.c_kpi_none')} />
        <Kpi label={t('client_portal.c_kpi_total')} icon="image" value={items.total.toLocaleString('en-US')} sub={t('client_portal.c_kpi_total_sub')} />
      </div>

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>{t('client_portal.content_empty')}</div>
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
                    {it.awaiting && <span className="btn btn-xs btn-primary" style={{ pointerEvents: 'none' }}>{t('client_portal.review')}</span>}
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
