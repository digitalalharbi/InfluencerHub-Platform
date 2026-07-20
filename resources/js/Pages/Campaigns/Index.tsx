import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Bar, Field, Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface CampaignRow {
  id: number; name: string; client: string | null; brand: string | null;
  status: string; statusLabel: string; statusTone: string;
  budgetMinor: number; endDate: string | null; progress: number;
  creators: number; deliverables: number; platforms: string[]; isLate: boolean; awaitingClient: number;
  startDate: string | null; stage: string;
}
interface Summary {
  total: number; active: number; planning: number; awaiting_client: number;
  late: number; completed: number; paused: number; draft: number;
}
interface Filters { q?: string; status?: string; seg?: string }
interface ClientOpt { id: number; name: string; brands: { id: number; name: string }[] }
interface Props { campaigns: Paginated<CampaignRow>; summary: Summary; filters: Filters; canCreate: boolean; clients: ClientOpt[] }

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

export default function CampaignsIndex({ campaigns, summary, filters, canCreate, clients }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ client_id: '', brand_id: '', name: '', objective: '', budget: '', start_date: '', end_date: '' });
  const brandOpts = clients.find((c) => String(c.id) === form.client_id)?.brands ?? [];
  const submitCreate = () => {
    if (!form.name.trim() || !form.client_id) return;
    setBusy(true);
    router.post(u('/campaigns'), {
      client_id: form.client_id, brand_id: form.brand_id || null, name: form.name, objective: form.objective || null,
      budget_minor: form.budget ? Math.round(parseFloat(form.budget) * 100) : null, currency: 'SAR',
      start_date: form.start_date || null, end_date: form.end_date || null,
    }, { onFinish: () => setBusy(false), onSuccess: () => setCreateOpen(false) });
  };
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/campaigns'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/campaigns'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || filters.status || seg);
  const segments: [string, string, number][] = [
    ['', 'الكل', summary.total], ['active', 'نشطة', summary.active], ['planning', 'قيد الترشيح', summary.planning],
    ['awaiting_client', 'بانتظار العميل', summary.awaiting_client], ['late', 'متأخرة', summary.late],
    ['completed', 'مكتملة', summary.completed], ['paused', 'متوقفة', summary.paused], ['draft', 'مسودة', summary.draft],
  ];

  return (
    <AppShell heading="الحملات">
      <Head title="الحملات" />

      <ListHead eyebrow="التشغيل" title="الحملات"
        sub="حملات المؤثرين ومخرجاتها وميزانياتها وتقدّمها ومخاطرها في لوحة واحدة"
        actions={canCreate ? <button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> حملة جديدة</button> : undefined} />

      <div className="ih-kpis">
        <Kpi label="إجمالي الحملات" icon="megaphone" value={summary.total.toLocaleString('en-US')} sub={`${summary.planning} قيد الترشيح · ${summary.draft} مسودة`} />
        <Kpi label="نشطة الآن" icon="rocket" tone="accent" value={summary.active.toLocaleString('en-US')} sub={`${summary.completed} مكتملة`} />
        <Kpi label="بانتظار العميل" icon="clipboard-check" tone="warning" value={summary.awaiting_client.toLocaleString('en-US')} sub="اعتماد أو مراجعة معلّقة" />
        <Kpi label="متأخرة" icon="bar-chart-3" tone={summary.late ? 'danger' : undefined} value={summary.late.toLocaleString('en-US')} sub="تجاوزت موعد الانتهاء" />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالاسم أو الرقم أو العميل…" />
        </label>
        <span style={{ marginInlineStart: 'auto', color: 'var(--ih-text-muted)', fontSize: '.82rem', alignSelf: 'center' }}>{campaigns.total} حملة</span>
      </div>

      {campaigns.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="megaphone" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">لا حملات مطابقة</div><div className="ih-empty__text">لا نتائج للبحث أو الشريحة الحالية.</div><a href={u("/campaigns")} className="btn btn-sm btn-outline">مسح الفلاتر</a></>
          ) : (
            <><div className="ih-empty__title">أطلق أول حملة</div><div className="ih-empty__text">أنشئ حملة لتتابع مخرجاتها وترشيحاتها ومحتواها وميزانيتها من مركز قيادة واحد.</div>{canCreate && <button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> حملة جديدة</button>}</>
          )}
        </div></div>
      ) : (
        <>
          {([['running', 'قيد التنفيذ'], ['planning', 'التخطيط'], ['closed', 'المنتهية']] as [string, string][]).map(([stage, label]) => {
            const grp = campaigns.data.filter((c) => c.stage === stage);
            if (grp.length === 0) return null;
            return (
            <div key={stage} style={{ marginBottom: '1.4rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', marginBottom: '.7rem' }}>
                <span style={{ fontWeight: 700, fontSize: '.9rem' }}>{label}</span>
                <span className="ih-pipe__count">{grp.length}</span>
              </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(330px, 1fr))', gap: '1rem' }}>
            {grp.map((c) => (
              <a key={c.id} href={u(`/campaigns/${c.id}`)} className="ih-sec" style={{ textDecoration: 'none', color: 'var(--ih-text)', display: 'flex', flexDirection: 'column' }}>
                <div style={{ padding: '1.05rem 1.1rem .8rem', display: 'flex', alignItems: 'flex-start', gap: '.7rem' }}>
                  <span className="ih-idc__av">{c.name.slice(0, 1)}</span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div className="ih-idc__name" style={{ fontSize: '1rem' }}>{c.name}</div>
                    <div className="ih-idc__sub">{c.client ?? '—'}{c.brand ? ` · ${c.brand}` : ''}</div>
                  </div>
                  <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                </div>
                {c.platforms.length > 0 && (
                  <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', padding: '0 1.1rem .6rem' }}>
                    {c.platforms.slice(0, 4).map((p) => <span key={p} className="ih-tag" style={{ fontSize: '.66rem' }}>{p}</span>)}
                  </div>
                )}
                <div style={{ padding: '0 1.1rem .5rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>
                    <span>التقدّم · {c.deliverables} مخرجات</span>
                    <span style={{ fontWeight: 800, color: 'var(--ih-primary)', fontVariantNumeric: 'tabular-nums' }}>{c.progress}%</span>
                  </div>
                  <Bar pct={c.progress} />
                </div>
                <div className="ih-mcard__grid" style={{ margin: '0 1.1rem', paddingTop: '.7rem' }}>
                  <div className="ih-metric"><span className="ih-metric__v" style={{ direction: 'ltr' }}>{kfmt(c.budgetMinor)}</span><span className="ih-metric__k">الميزانية</span></div>
                  <div className="ih-metric"><span className="ih-metric__v">{c.creators}</span><span className="ih-metric__k">مؤثرون</span></div>
                  <div className="ih-metric"><span className="ih-metric__v" style={{ fontSize: '.82rem' }}>{c.endDate ?? '—'}</span><span className="ih-metric__k">الانتهاء</span></div>
                </div>
                <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', padding: '.7rem 1.1rem 1rem', minHeight: '2.6rem' }}>
                  {c.isLate && <span className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.62rem' }}>● متأخرة</span>}
                  {c.awaitingClient > 0 && <span className="badge" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.62rem' }}>{c.awaitingClient} بانتظار العميل</span>}
                </div>
              </a>
            ))}
          </div>
            </div>
            );
          })}
          <div style={{ marginTop: '1.1rem' }}><Pagination links={campaigns.links} /></div>
        </>
      )}
      {createOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setCreateOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 560 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>حملة جديدة</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="العميل" labelStyle={LBL}>
                  <select value={form.client_id} onChange={(e) => setForm({ ...form, client_id: e.target.value, brand_id: '' })} className="field" style={{ width: '100%' }}>
                    <option value="">اختر العميل…</option>
                    {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                </Field>
                <Field label="العلامة (اختياري)" labelStyle={LBL}>
                  <select value={form.brand_id} onChange={(e) => setForm({ ...form, brand_id: e.target.value })} className="field" style={{ width: '100%' }} disabled={!form.client_id}>
                    <option value="">— بدون —</option>
                    {brandOpts.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                  </select>
                </Field>
              </div>
              <Field label="اسم الحملة" labelStyle={LBL}>
                <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="field" style={{ width: '100%' }} />
              </Field>
              <Field label="الهدف (اختياري)" labelStyle={LBL}>
                <textarea value={form.objective} onChange={(e) => setForm({ ...form, objective: e.target.value })} className="field" rows={2} style={{ width: '100%', resize: 'vertical' }} />
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
                <Field label="الميزانية (ر.س)" labelStyle={LBL}>
                  <input type="number" min="0" value={form.budget} onChange={(e) => setForm({ ...form, budget: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
                <Field label="البداية" labelStyle={LBL}>
                  <input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
                <Field label="النهاية" labelStyle={LBL}>
                  <input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
              </div>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.name.trim() || !form.client_id} onClick={submitCreate} className="btn btn-primary">إنشاء الحملة</button>
              <button disabled={busy} onClick={() => setCreateOpen(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
