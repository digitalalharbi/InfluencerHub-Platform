import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Bar, Field, Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

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

const STATUS_LABELS: Record<string, string> = { lead: 'مهتم', qualified: 'مؤهّل', active: 'نشط', inactive: 'غير نشط', suspended: 'موقوف' };
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
  const [q, setQ] = useState(filters.q ?? '');
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ display_name: '', type: 'company', status: 'lead', sector: '', email: '', phone: '' });
  const submitCreate = () => {
    if (!form.display_name.trim()) return;
    setBusy(true);
    router.post(u('/clients'), form, { onFinish: () => setBusy(false), onSuccess: () => setCreateOpen(false) });
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
    ['', 'الكل', summary.total], ['active', 'نشط', summary.active], ['inactive', 'غير نشط', summary.inactive],
    ['complete', 'مكتمل الملف', summary.complete], ['incomplete', 'غير مكتمل', summary.incomplete],
    ['vip', 'VIP', summary.vip], ['needs_action', 'يحتاج إجراءً', summary.needs_action],
    ['with_active_campaigns', 'لديه حملات نشطة', summary.with_active_campaigns],
  ];

  return (
    <AppShell heading="العملاء">
      <Head title="العملاء" />

      <ListHead eyebrow="إدارة العلاقات" title="العملاء"
        sub="حسابات العملاء وملفاتهم وحملاتهم ومتابعتهم المالية في بيئة تشغيل موحّدة"
        actions={canCreate ? <button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> عميل جديد</button> : undefined} />

      <div className="ih-kpis">
        <Kpi label="الإيراد الكلي" icon="wallet" tone="success" value={<>{kfmt(operational.revenue_minor)} <small>ر.س</small></>} sub={`${summary.vip} عميل VIP`} />
        <Kpi label="الحملات الجارية" icon="megaphone" tone="accent" value={operational.active_campaigns.toLocaleString('en-US')} sub={`${summary.with_active_campaigns} عميل نشط الحملات`} />
        <Kpi label="مستحقات معلّقة" icon="wallet" tone="warning" value={operational.pending_payouts.toLocaleString('en-US')} sub="بانتظار الاعتماد أو الصرف" />
        <div className="ih-kpi">
          <div className="ih-kpi__top"><span className="ih-kpi__label">اكتمال الملفات</span><span className="ih-kpi__icon"><Icon name="bar-chart-3" size={18} /></span></div>
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
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالاسم أو السجل…" />
        </label>
        <select className="field" style={{ maxWidth: 130 }} value={filters.status ?? ''} onChange={(e) => update({ status: e.target.value })}>
          <option value="">كل الحالات</option>
          {Object.entries(STATUS_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 140 }} value={filters.sector ?? ''} onChange={(e) => update({ sector: e.target.value })}>
          <option value="">كل القطاعات</option>
          {sectors.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 150 }} value={filters.manager ?? ''} onChange={(e) => update({ manager: e.target.value })}>
          <option value="">كل المدراء</option>
          {managers.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
        </select>
      </div>

      {clients.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="building-2" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">لا عملاء مطابقون</div><div className="ih-empty__text">لا نتائج للبحث أو الفلاتر الحالية.</div><a href={u("/clients")} className="btn btn-sm btn-outline">مسح الفلاتر</a></>
          ) : (
            <><div className="ih-empty__title">ابدأ بإضافة أول عميل</div><div className="ih-empty__text">أنشئ ملف عميل لتتابع علاماته وحملاته ومستحقاته من مكان واحد.</div>{canCreate && <button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> عميل جديد</button>}</>
          )}
        </div></div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr>
                  <th>العميل</th><th>القطاع</th><th>العلامات</th><th>مدير الحساب</th><th>الحملات</th>
                  <th style={{ minWidth: 130 }}>اكتمال الملف</th><th>الحالة</th><th>الإيراد</th><th></th>
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
                      <td>{c.activeCampaigns > 0 ? <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{c.activeCampaigns} نشطة</span> : <span style={{ color: 'var(--ih-text-muted)' }}>—</span>}</td>
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
                          {c.needsAction > 0 && <span className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.6rem' }} title="عناصر تحتاج إجراءً">● {c.needsAction}</span>}
                          <a href={u(`/clients/${c.id}`)} className="btn btn-xs btn-outline">فتح</a>
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot">
                <span>{clients.total} عميل{hasFilters ? ' · مُرشَّح' : ''}</span>
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
                    <div className="ih-metric"><span className="ih-metric__v">{c.activeCampaigns}</span><span className="ih-metric__k">حملة نشطة</span></div>
                    <div className="ih-metric"><span className="ih-metric__v" style={{ direction: 'ltr' }}>{kfmt(c.revenueMinor)}</span><span className="ih-metric__k">الإيراد</span></div>
                    <div className="ih-metric"><span className="ih-metric__v">{c.completion}%</span><span className="ih-metric__k">اكتمال</span></div>
                  </div>
                  {c.needsAction > 0 && <div style={{ marginTop: '.6rem', fontSize: '.76rem', color: 'var(--ih-danger-ink)', fontWeight: 600 }}>● {c.needsAction} عنصر يحتاج إجراءً</div>}
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
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>عميل جديد</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="اسم العميل" labelStyle={LBL}>
                <input value={form.display_name} onChange={(e) => setForm({ ...form, display_name: e.target.value })} className="field" style={{ width: '100%' }} autoFocus />
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="النوع" labelStyle={LBL}>
                  <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="field" style={{ width: '100%' }}>
                    {[['company', 'شركة'], ['brand_owner', 'مالك علامة'], ['government', 'جهة حكومية'], ['nonprofit', 'غير ربحية'], ['agency', 'وكالة'], ['individual', 'فرد'], ['other', 'أخرى']].map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                  </select>
                </Field>
                <Field label="الحالة" labelStyle={LBL}>
                  <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className="field" style={{ width: '100%' }}>
                    {[['lead', 'مهتم'], ['qualified', 'مؤهّل'], ['active', 'نشط'], ['inactive', 'غير نشط'], ['suspended', 'موقوف']].map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                  </select>
                </Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="القطاع" labelStyle={LBL}>
                  <input value={form.sector} onChange={(e) => setForm({ ...form, sector: e.target.value })} className="field" style={{ width: '100%' }} />
                </Field>
                <Field label="البريد" labelStyle={LBL}>
                  <input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
              </div>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.display_name.trim()} onClick={submitCreate} className="btn btn-primary">إنشاء العميل</button>
              <button disabled={busy} onClick={() => setCreateOpen(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
