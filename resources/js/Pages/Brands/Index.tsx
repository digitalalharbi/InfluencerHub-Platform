import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface BrandRow {
  id: number; name: string; client: string | null; sector: string | null; version: number;
  status: string; statusLabel: string; statusTone: string; submittedAt: string | null; needsReview: boolean;
}
interface Summary {
  total: number; needs_review: number; submitted: number; under_review: number;
  changes_requested: number; approved: number; suspended: number; draft: number;
}
interface Filters { q?: string; seg?: string }
/** من أين تُنشأ العلامة — تُحسب في الخادم لأنها تعتمد على عدد العملاء. */
interface CreateHint { clientsCount: number; href: string; label: string }
interface Props { brands: Paginated<BrandRow>; filters: Filters; summary: Summary; createHint: CreateHint }

function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function BrandsIndex({ brands, filters, summary, createHint }: Props) {
  const t = useT();
  const [q, setQ] = useState(filters.q ?? '');
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/brands'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/brands'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || seg);
  const segments: [string, string, number][] = [
    ['', t('brands.seg_all'), summary.total], ['needs_review', t('brands.seg_needs_review'), summary.needs_review],
    ['submitted', t('brands.seg_submitted'), summary.submitted], ['under_review', t('brands.seg_under_review'), summary.under_review],
    ['changes_requested', t('brands.seg_changes_requested'), summary.changes_requested], ['approved', t('brands.seg_approved'), summary.approved],
    ['suspended', t('brands.seg_suspended'), summary.suspended], ['draft', t('brands.seg_draft'), summary.draft],
  ];

  return (
    <AppShell heading={t('brands.title')}>
      <Head title={t('brands.title')} />

      <ListHead eyebrow={t('brands.eyebrow')} title={t('brands.title')}
        sub={t('brands.sub')} />

      <div className="ih-kpis">
        <Kpi label={t('brands.kpi_total')} icon="bookmark" value={summary.total.toLocaleString('en-US')} sub={t('brands.kpi_total_sub', { approved: summary.approved })} />
        <Kpi label={t('brands.kpi_needs_review')} icon="shield-check" tone={summary.needs_review ? 'warning' : undefined} value={summary.needs_review.toLocaleString('en-US')} sub={t('brands.kpi_needs_review_sub', { submitted: summary.submitted, under_review: summary.under_review })} />
        <Kpi label={t('brands.kpi_changes')} icon="clipboard-check" value={summary.changes_requested.toLocaleString('en-US')} sub={t('brands.kpi_changes_sub')} />
        <Kpi label={t('brands.kpi_suspended')} icon="shield-check" tone={summary.suspended ? 'danger' : undefined} value={summary.suspended.toLocaleString('en-US')} sub={t('brands.kpi_suspended_sub')} />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('brands.search_placeholder')} />
        </label>
      </div>

      {brands.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="bookmark" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">{t('brands.empty_filtered_title')}</div><div className="ih-empty__text">{t('brands.empty_filtered_text')}</div><a href={u("/brands")} className="btn btn-sm btn-outline">{t('brands.clear_filters')}</a></>
          ) : (
            <><div className="ih-empty__title">{t('brands.empty_title')}</div>
              <div className="ih-empty__text">
                {createHint.clientsCount === 0
                  ? t('brands.empty_text_no_clients')
                  : t('brands.empty_text_has_clients')}
              </div>
              <a href={createHint.clientsCount === 0 ? u('/clients') : createHint.href} className="btn btn-sm">
                {createHint.clientsCount === 0 ? t('brands.add_client') : createHint.label}
              </a></>
          )}
        </div></div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>{t('brands.th_brand')}</th><th>{t('brands.th_client')}</th><th>{t('brands.th_sector')}</th><th>{t('brands.th_version')}</th><th>{t('brands.th_submitted')}</th><th>{t('brands.th_status')}</th><th></th></tr></thead>
                <tbody>
                  {brands.data.map((b) => (
                    <tr key={b.id}>
                      <td>
                        <a href={u(`/brands/${b.id}`)} className="ih-idc" style={{ textDecoration: 'none' }}>
                          <span className="ih-idc__av" style={{ borderRadius: 8 }}>{b.name.slice(0, 1)}</span>
                          <span className="ih-idc__main"><span className="ih-idc__name">{b.name}</span></span>
                        </a>
                      </td>
                      <td>{b.client ?? '—'}</td>
                      <td>{b.sector ? <span className="ih-tag">{b.sector}</span> : '—'}</td>
                      <td className="ih-dt__num">v{b.version}</td>
                      <td style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>{b.submittedAt ?? '—'}</td>
                      <td><StatusBadge tone={b.statusTone} label={b.statusLabel} /></td>
                      <td style={{ textAlign: 'end' }}>
                        <span className="ih-dt__row-actions">
                          {b.needsReview && <span className="badge" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.6rem' }}>{t('brands.needs_review')}</span>}
                          <a href={u(`/brands/${b.id}`)} className="btn btn-xs btn-outline">{t('brands.open')}</a>
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot"><span>{t('brands.count_item', { n: brands.total })}{hasFilters ? t('brands.filtered_suffix') : ''}</span><Pagination links={brands.links} /></div>
            </div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {brands.data.map((b) => (
                <a key={b.id} href={u(`/brands/${b.id}`)} className="ih-mcard">
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
                    <span className="ih-idc__av" style={{ width: 42, height: 42, borderRadius: 8 }}>{b.name.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{b.name}</div>
                      <div className="ih-idc__sub">{b.client ?? '—'} · v{b.version}</div>
                    </div>
                    <StatusBadge tone={b.statusTone} label={b.statusLabel} />
                  </div>
                  {b.needsReview && <div style={{ marginTop: '.6rem', fontSize: '.76rem', color: 'var(--ih-warning-ink)', fontWeight: 600 }}>{t('brands.needs_your_review')}</div>}
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={brands.links} /></div>
          </div>
        </>
      )}
    </AppShell>
  );
}
