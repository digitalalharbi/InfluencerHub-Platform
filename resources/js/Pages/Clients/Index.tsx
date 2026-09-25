import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Bar, Field, Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { ExportButtons } from '@/Components/ExportButtons';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface ClientRow {
  id: number; name: string; number: string; sector: string | null; manager: string | null;
  brands: number; status: string; statusLabel: string; statusTone: string;
  revenueMinor: number; activeCampaigns: number; completion: number; isVip: boolean; needsAction: number;
}
interface Summary {
  total: number; active: number; inactive: number; complete: number; incomplete: number;
  vip: number; needs_action: number; with_active_campaigns: number;
}
interface Operational { revenue_minor: number; active_campaigns: number; pending_payouts: number; avg_completion: number }
interface Filters { q?: string; status?: string; sector?: string; manager?: string; seg?: string }
interface Props {
  clients: Paginated<ClientRow>; summary: Summary; operational: Operational; filters: Filters;
  sectors: string[]; managers: { id: number; name: string }[]; canCreate: boolean;
}

const STATUS_KEYS = ['lead', 'qualified', 'active', 'inactive', 'suspended'];
const TYPE_KEYS = ['company', 'brand_owner', 'government', 'nonprofit', 'agency', 'individual', 'other'];
const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };
function kfmt(minor: number): string {
  const v = minor / 100;
  if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
  if (v >= 1000) return Math.round(v / 1000) + 'K';
  return v.toLocaleString('en-US');
}
function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function ClientsIndex({ clients, summary, operational, filters, sectors, managers, canCreate }: Props) {
  const t = useT();
  const [q, setQ] = useState(filters.q ?? '');
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ display_name: '', type: 'company', status: 'lead', sector: '', email: '', phone: '' });
  const [errs, setErrs] = useState<Record<string, string>>({});
  const openCreate = () => { setErrs({}); setCreateOpen(true); };
  const submitCreate = () => {
    if (!form.display_name.trim()) return;
    setBusy(true);
    // كانت النافذة تبتلع أخطاء التحقق/الحدود بصمت: عند رفض الإنشاء (مثلًا بلوغ حدّ
    // الخطة) لا تظهر أي رسالة. نعرض الخطأ الآن كما تفعل صفحة تفصيل العميل.
    router.post(u('/clients'), form, {
      onFinish: () => setBusy(false),
      onError: (e) => setErrs(e as Record<string, string>),
      onSuccess: () => { setErrs({}); setCreateOpen(false); },
    });
  };
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/clients'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/clients'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || filters.status || filters.sector || filters.manager || seg);
  const segments: [string, string, number][] = [
    ['', t('clients.seg_all'), summary.total], ['active', t('clients.seg_active'), summary.active], ['inactive', t('clients.seg_inactive'), summary.inactive],
    ['complete', t('clients.seg_complete'), summary.complete], ['incomplete', t('clients.seg_incomplete'), summary.incomplete],
    ['vip', t('clients.seg_vip'), summary.vip], ['needs_action', t('clients.seg_needs_action'), summary.needs_action],
    ['with_active_campaigns', t('clients.seg_with_active_campaigns'), summary.with_active_campaigns],
  ];

  return (
    <AppShell heading={t('clients.title')}>
      <Head title={t('clients.title')} />

      <ListHead eyebrow={t('clients.eyebrow')} title={t('clients.title')}
        sub={t('clients.sub')}
        actions={<span style={{ display: 'inline-flex', gap: '.4rem', alignItems: 'center' }}>
          <ExportButtons path="/clients/export" filters={filters as Record<string, string>} />
          {canCreate && <button onClick={openCreate} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> {t('clients.new_client')}</button>}
        </span>} />

      <div className="ih-kpis">
        <Kpi label={t('clients.kpi_revenue')} icon="wallet" tone="success" value={<>{kfmt(operational.revenue_minor)} <small>ر.س</small></>} sub={t('clients.kpi_revenue_sub', { vip: summary.vip })} />
        <Kpi label={t('clients.kpi_active_campaigns')} icon="megaphone" tone="accent" value={operational.active_campaigns.toLocaleString('en-US')} sub={t('clients.kpi_active_campaigns_sub', { n: summary.with_active_campaigns })} />
        <Kpi label={t('clients.kpi_pending_payouts')} icon="wallet" tone="warning" value={operational.pending_payouts.toLocaleString('en-US')} sub={t('clients.kpi_pending_payouts_sub')} />
        <div className="ih-kpi">
          <div className="ih-kpi__top"><span className="ih-kpi__label">{t('clients.kpi_completion')}</span><span className="ih-kpi__icon"><Icon name="bar-chart-3" size={18} /></span></div>
          <div className="ih-kpi__value">{operational.avg_completion}<small>%</small></div>
          <div style={{ marginTop: '.5rem' }}><Bar pct={operational.avg_completion} /></div>
        </div>
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('clients.search_placeholder')} />
        </label>
        <select className="field" style={{ maxWidth: 130 }} value={filters.status ?? ''} onChange={(e) => update({ status: e.target.value })}>
          <option value="">{t('clients.all_statuses')}</option>
          {STATUS_KEYS.map((k) => <option key={k} value={k}>{t(`clients.s_${k}`)}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 140 }} value={filters.sector ?? ''} onChange={(e) => update({ sector: e.target.value })}>
          <option value="">{t('clients.all_sectors')}</option>
          {sectors.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 150 }} value={filters.manager ?? ''} onChange={(e) => update({ manager: e.target.value })}>
          <option value="">{t('clients.all_managers')}</option>
          {managers.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
        </select>
      </div>

      {clients.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="building-2" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">{t('clients.empty_filtered_title')}</div><div className="ih-empty__text">{t('clients.empty_filtered_text')}</div><a href={u("/clients")} className="btn btn-sm btn-outline">{t('clients.clear_filters')}</a></>
          ) : (
            <><div className="ih-empty__title">{t('clients.empty_title')}</div><div className="ih-empty__text">{t('clients.empty_text')}</div>{canCreate && <button onClick={openCreate} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> {t('clients.new_client')}</button>}</>
          )}
        </div></div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr>
                  <th>{t('clients.th_client')}</th><th>{t('clients.th_sector')}</th><th>{t('clients.th_brands')}</th><th>{t('clients.th_manager')}</th><th>{t('clients.th_campaigns')}</th>
                  <th style={{ minWidth: 130 }}>{t('clients.th_completion')}</th><th>{t('clients.th_status')}</th><th>{t('clients.th_revenue')}</th><th></th>
                </tr></thead>
                <tbody>
                  {clients.data.map((c) => (
                    <tr key={c.id}>
                      <td>
                        <a href={u(`/clients/${c.id}`)} className="ih-idc" style={{ textDecoration: 'none' }}>
                          <span className="ih-idc__av">{c.name.slice(0, 1)}</span>
                          <span className="ih-idc__main">
                            <span className="ih-idc__name">{c.name} {c.isVip && <span className="badge" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.58rem' }}>VIP</span>}</span>
                            <span className="ih-idc__sub" style={{ direction: 'ltr', textAlign: 'right' }}>{c.number}</span>
                          </span>
                        </a>
                      </td>
                      <td>{c.sector ? <span className="ih-tag">{c.sector}</span> : '—'}</td>
                      <td className="ih-dt__num">{c.brands}</td>
                      <td style={{ fontSize: '.82rem' }}>{c.manager ?? '—'}</td>
                      <td>{c.activeCampaigns > 0 ? <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{t('clients.active_count', { n: c.activeCampaigns })}</span> : <span style={{ color: 'var(--ih-text-muted)' }}>—</span>}</td>
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}>
                          <div className="ih-bar" style={{ flex: 1 }}><span style={{ width: `${c.completion}%` }} /></div>
                          <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', fontVariantNumeric: 'tabular-nums' }}>{c.completion}%</span>
                        </div>
                      </td>
                      <td><StatusBadge tone={c.statusTone} label={c.statusLabel} /></td>
                      <td className="ih-dt__num">{kfmt(c.revenueMinor)}</td>
                      <td style={{ textAlign: 'end' }}>
                        <span className="ih-dt__row-actions">
                          {c.needsAction > 0 && <span className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.6rem' }} title={t('clients.needs_action_tooltip')}>● {c.needsAction}</span>}
                          <a href={u(`/clients/${c.id}`)} className="btn btn-xs btn-outline">{t('clients.open')}</a>
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot">
                <span>{t('clients.count_item', { n: clients.total })}{hasFilters ? t('clients.filtered_suffix') : ''}</span>
                <Pagination links={clients.links} />
              </div>
            </div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {clients.data.map((c) => (
                <a key={c.id} href={u(`/clients/${c.id}`)} className="ih-mcard">
                  <div className="ih-mcard__top">
                    <span className="ih-idc__av" style={{ width: 42, height: 42 }}>{c.name.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{c.name} {c.isVip && <span className="badge" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.56rem' }}>VIP</span>}</div>
                      <div className="ih-idc__sub">{c.sector ?? '—'} · {c.number}</div>
                    </div>
                    <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                  </div>
                  <div className="ih-mcard__grid">
                    <div className="ih-metric"><span className="ih-metric__v">{c.activeCampaigns}</span><span className="ih-metric__k">{t('clients.m_active_campaigns')}</span></div>
                    <div className="ih-metric"><span className="ih-metric__v" style={{ direction: 'ltr' }}>{kfmt(c.revenueMinor)}</span><span className="ih-metric__k">{t('clients.m_revenue')}</span></div>
                    <div className="ih-metric"><span className="ih-metric__v">{c.completion}%</span><span className="ih-metric__k">{t('clients.m_completion')}</span></div>
                  </div>
                  {c.needsAction > 0 && <div style={{ marginTop: '.6rem', fontSize: '.76rem', color: 'var(--ih-danger-ink)', fontWeight: 600 }}>● {t('clients.needs_action_note', { n: c.needsAction })}</div>}
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={clients.links} /></div>
          </div>
        </>
      )}
      {createOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setCreateOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 520 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{t('clients.new_client')}</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label={t('clients.f_name')} labelStyle={LBL}>
                <input value={form.display_name} onChange={(e) => setForm({ ...form, display_name: e.target.value })} className="field" style={{ width: '100%' }} autoFocus />
                {errs.display_name && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.74rem', marginTop: '.25rem' }}>{errs.display_name}</div>}
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label={t('clients.f_type')} labelStyle={LBL}>
                  <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="field" style={{ width: '100%' }}>
                    {TYPE_KEYS.map((v) => <option key={v} value={v}>{t(`clients.type_${v}`)}</option>)}
                  </select>
                </Field>
                <Field label={t('clients.f_status')} labelStyle={LBL}>
                  <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className="field" style={{ width: '100%' }}>
                    {STATUS_KEYS.map((v) => <option key={v} value={v}>{t(`clients.s_${v}`)}</option>)}
                  </select>
                </Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label={t('clients.f_sector')} labelStyle={LBL}>
                  <input value={form.sector} onChange={(e) => setForm({ ...form, sector: e.target.value })} className="field" style={{ width: '100%' }} />
                </Field>
                <Field label={t('clients.f_email')} labelStyle={LBL}>
                  <input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
              </div>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.display_name.trim()} onClick={submitCreate} className="btn btn-primary">{t('clients.create')}</button>
              <button disabled={busy} onClick={() => setCreateOpen(false)} className="btn btn-ghost">{t('clients.cancel')}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
