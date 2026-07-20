import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface AgencyRow {
  id: number; name: string; number: string; contact: string | null; specialization: string | null;
  members: number; links: number; status: string; statusLabel: string; statusTone: string;
  needsReview: boolean;
}
interface Summary { total: number; needsReview: number; approved: number; suspended: number; draft: number }
interface Filters { status?: string }
interface Props {
  agencies: Paginated<AgencyRow>; filters: Filters; summary: Summary;
  statusOptions: Record<string, string>; canCreate: boolean;
}

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

export default function PartnersIndex({ agencies, filters, summary, statusOptions, canCreate }: Props) {
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [form, setForm] = useState({
    name: '', contact_name: '', contact_email: '', specialization: '', country_code: '',
  });

  const update = (status: string) =>
    router.get(u('/partner-agencies'), status ? { status } : {}, { preserveState: true, replace: true, preserveScroll: true });

  const submitCreate = () => {
    if (!form.name.trim()) return;
    setBusy(true);
    router.post(u('/partner-agencies'), form, {
      onFinish: () => setBusy(false),
      onError: (e) => setErrors(e as Record<string, string>),
      onSuccess: () => { setCreateOpen(false); setErrors({}); },
    });
  };

  return (
    <AppShell heading="الوكالات الشريكة">
      <Head title="الوكالات الشريكة" />

      <ListHead eyebrow="الإدارة" title="الوكالات الشريكة"
        sub="وكالات خارجية تعمل على عملائك بنطاق محدَّد — اعتماد، أعضاء، وروابط مُنطّقة"
        actions={canCreate ? <button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> وكالة شريكة</button> : undefined} />

      <div className="ih-kpis">
        <Kpi label="بانتظار المراجعة" icon="clipboard-check" tone={summary.needsReview ? 'warning' : undefined}
          value={summary.needsReview.toLocaleString('en-US')} sub="مُرسَلة أو قيد المراجعة" />
        <Kpi label="معتمدة" icon="shield-check" tone="success" value={summary.approved.toLocaleString('en-US')} sub="تعمل على عملاء" />
        <Kpi label="معلّقة" icon="activity" tone={summary.suspended ? 'danger' : undefined}
          value={summary.suspended.toLocaleString('en-US')} sub="موقوفة عن العمل" />
        <Kpi label="الإجمالي" icon="handshake" value={summary.total.toLocaleString('en-US')} sub={`${summary.draft} مسودة`} />
      </div>

      <div className="ih-filterbar">
        <select className="field" style={{ maxWidth: 200 }} value={filters.status ?? ''} onChange={(e) => update(e.target.value)}>
          <option value="">كل الحالات</option>
          {Object.entries(statusOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </div>

      {agencies.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="handshake" size={26} /></span>
          <div className="ih-empty__title">لا وكالات شريكة</div>
          <div className="ih-empty__text">
            {filters.status ? 'لا نتائج للحالة المختارة.' : 'أضِف وكالة خارجية لتعمل على عملائك بنطاق محدَّد ومراقَب.'}
          </div>
          {filters.status && <button onClick={() => update('')} className="btn btn-sm btn-outline">مسح الفلتر</button>}
        </div></div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '.9rem' }}>
              {agencies.data.map((a) => (
                <a key={a.id} href={u(`/partner-agencies/${a.id}`)} className="ih-idcard" style={{ textDecoration: 'none', color: 'inherit' }}>
                  <div className="ih-idcard__top">
                    <span className="ih-idcard__logo">{a.name.slice(0, 1)}</span>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div className="ih-idc__name">{a.name}</div>
                      <div className="ih-idc__sub" style={{ direction: 'ltr', textAlign: 'start' }}>{a.number}</div>
                    </div>
                    <StatusBadge tone={a.statusTone} label={a.statusLabel} />
                  </div>
                  <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
                    {a.specialization ?? 'بلا تخصّص محدَّد'}{a.contact ? ` · ${a.contact}` : ''}
                  </div>
                  <div className="ih-idcard__stats">
                    <div className="ih-idcard__stat"><div className="ih-idcard__sv">{a.members}</div><div className="ih-idcard__sl">أعضاء</div></div>
                    <div className="ih-idcard__stat"><div className="ih-idcard__sv">{a.links}</div><div className="ih-idcard__sl">روابط</div></div>
                  </div>
                  {a.needsReview && (
                    <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', alignSelf: 'flex-start' }}>
                      بانتظار المراجعة
                    </span>
                  )}
                </a>
              ))}
            </div>
            <div className="ih-dt__foot" style={{ marginTop: '.9rem' }}>
              <span>{agencies.total} وكالة</span>
              <Pagination links={agencies.links} />
            </div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {agencies.data.map((a) => (
                <a key={a.id} href={u(`/partner-agencies/${a.id}`)} className="ih-mcard">
                  <div className="ih-mcard__top">
                    <span className="ih-idc__av" style={{ width: 42, height: 42 }}>{a.name.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{a.name}</div>
                      <div className="ih-idc__sub">{a.specialization ?? '—'}</div>
                    </div>
                    <StatusBadge tone={a.statusTone} label={a.statusLabel} />
                  </div>
                  <div className="ih-mcard__grid">
                    <div className="ih-metric"><span className="ih-metric__v">{a.members}</span><span className="ih-metric__k">أعضاء</span></div>
                    <div className="ih-metric"><span className="ih-metric__v">{a.links}</span><span className="ih-metric__k">روابط</span></div>
                  </div>
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={agencies.links} /></div>
          </div>
        </>
      )}

      {createOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setCreateOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 540 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>وكالة شريكة جديدة</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="اسم الوكالة" labelStyle={LBL}>
                <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="field" style={{ width: '100%' }} autoFocus />
                {errors.name && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.name}</div>}
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="جهة الاتصال" labelStyle={LBL}>
                  <input value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} className="field" style={{ width: '100%' }} />
                </Field>
                <Field label="بريد الاتصال" labelStyle={LBL}>
                  <input value={form.contact_email} onChange={(e) => setForm({ ...form, contact_email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                  {errors.contact_email && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.contact_email}</div>}
                </Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="التخصّص" labelStyle={LBL}>
                  <input value={form.specialization} onChange={(e) => setForm({ ...form, specialization: e.target.value })} className="field" style={{ width: '100%' }} />
                </Field>
                <Field label="الدولة" labelStyle={LBL}>
                  <input value={form.country_code} onChange={(e) => setForm({ ...form, country_code: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="SA" />
                </Field>
              </div>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.name.trim()} onClick={submitCreate} className="btn btn-primary">إنشاء مسودة</button>
              <button disabled={busy} onClick={() => setCreateOpen(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
