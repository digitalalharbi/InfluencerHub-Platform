import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi, numFmt } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Row {
  id: number; org: string; plan: string; version: number; status: string; statusLabel: string; statusTone: string;
  provider: string | null; providerRaw: string; trialEndsAt: string | null; periodEnd: string | null;
}
const SUB_STATUSES: [string, string][] = [['active', 'نشط'], ['trialing', 'تجريبي'], ['past_due', 'متأخّر'], ['canceled', 'ملغى'], ['expired', 'منتهٍ']];
const PROVIDERS: [string, string][] = [['manual', 'يدوي'], ['fake', 'تجريبي'], ['moyasar', 'ميسر'], ['stripe', 'Stripe']];
interface Summary {
  total: number; active: number; trialing: number; attention: number;
  byStatus: { status: string; label: string; count: number }[];
}
interface Props { subs: Paginated<Row>; summary: Summary; filters: { status: string | null } }

export default function AdminSubscriptions({ subs, summary, filters }: Props) {
  const apply = (status?: string) =>
    router.get(u('/subscriptions'), status ? { status } : {}, { preserveState: true, replace: true, preserveScroll: true });

  const [edit, setEdit] = useState<Row | null>(null);
  const [ef, setEf] = useState({ status: '', billing_provider: '', trial_ends_at: '', current_period_end: '' });
  const [saving, setSaving] = useState(false);
  const openEdit = (s: Row) => { setEf({ status: s.status, billing_provider: s.providerRaw, trial_ends_at: s.trialEndsAt ?? '', current_period_end: s.periodEnd ?? '' }); setEdit(s); };
  const save = () => {
    if (!edit) return;
    setSaving(true);
    router.post(u(`/subscriptions/${edit.id}`), ef, { preserveScroll: true, onFinish: () => setSaving(false), onSuccess: () => setEdit(null) });
  };

  return (
    <AppShell heading="الاشتراكات" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="الاشتراكات" />
      <ListHead eyebrow="المنصّة · الفوترة" title="الاشتراكات" sub="اشتراكات المؤسسات وخططها ودورات فوترتها عبر المنصّة." />

      <div className="ih-kpis">
        <Kpi label="إجمالي الاشتراكات" icon="wallet" value={numFmt(summary.total)} sub="كل الحالات" />
        <Kpi label="نشطة" icon="shield-check" tone="success" value={numFmt(summary.active)} sub="مدفوعة وجارية" />
        <Kpi label="تجريبية" icon="clipboard-check" tone="accent" value={numFmt(summary.trialing)} sub="فترة تجربة" />
        <Kpi label="تحتاج انتباهًا" icon="alert-triangle" tone={summary.attention ? 'danger' : 'success'} value={numFmt(summary.attention)} sub="متأخّرة/منتهية" />
      </div>

      {/* شرائح الحالة */}
      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        <button onClick={() => apply()} className={`ih-chip${!filters.status ? ' active' : ''}`}>الكل <span className="ih-chip__count">{summary.total}</span></button>
        {summary.byStatus.map((s) => (
          <button key={s.status} onClick={() => apply(filters.status === s.status ? undefined : s.status)}
            className={`ih-chip${filters.status === s.status ? ' active' : ''}`}>{s.label} <span className="ih-chip__count">{s.count}</span></button>
        ))}
      </div>

      {subs.data.length === 0 ? (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="wallet" size={26} /></span>
          <div className="ih-empty__title">لا اشتراكات</div>
          <div className="ih-empty__text">لا نتائج بهذه الحالة.</div>
        </div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>المؤسسة</th><th>الخطة</th><th>المزوّد</th><th>انتهاء التجربة</th><th>نهاية الدورة</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                  {subs.data.map((s) => (
                    <tr key={s.id}>
                      <td>
                        <div className="ih-idc">
                          <span className="ih-idc__av">{s.org.slice(0, 1)}</span>
                          <span className="ih-idc__main"><span className="ih-idc__name">{s.org}</span></span>
                        </div>
                      </td>
                      <td>{s.plan} <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>v{s.version}</span></td>
                      <td><span className="ih-tag">{s.provider ?? '—'}</span></td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{s.trialEndsAt ?? '—'}</td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{s.periodEnd ?? '—'}</td>
                      <td><StatusBadge tone={s.statusTone} label={s.statusLabel} /></td>
                      <td style={{ textAlign: 'end' }}><button className="btn btn-xs btn-outline" onClick={() => openEdit(s)}><Icon name="pencil" size={12} /> تعديل</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot"><span>{subs.total.toLocaleString('en-US')} اشتراك</span><Pagination links={subs.links} /></div>
            </div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {subs.data.map((s) => (
                <div key={s.id} className="ih-mcard">
                  <div className="ih-mcard__top">
                    <span className="ih-idc__av" style={{ width: 42, height: 42 }}>{s.org.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{s.org} <StatusBadge tone={s.statusTone} label={s.statusLabel} /></div>
                      <div className="ih-idc__sub">{s.plan} · <span style={{ direction: 'ltr' }}>v{s.version}</span></div>
                    </div>
                  </div>
                  <div className="ih-mcard__grid">
                    <div><span className="ih-mcard__lbl">المزوّد</span><span className="ih-mcard__val">{s.provider ?? '—'}</span></div>
                    <div><span className="ih-mcard__lbl">التجربة</span><span className="ih-mcard__val" style={{ direction: 'ltr' }}>{s.trialEndsAt ?? '—'}</span></div>
                    <div><span className="ih-mcard__lbl">نهاية الدورة</span><span className="ih-mcard__val" style={{ direction: 'ltr' }}>{s.periodEnd ?? '—'}</span></div>
                  </div>
                  <button className="btn btn-xs btn-outline" style={{ marginTop: '.6rem' }} onClick={() => openEdit(s)}><Icon name="pencil" size={12} /> تعديل</button>
                </div>
              ))}
            </div>
            <Pagination links={subs.links} />
          </div>
        </>
      )}

      {edit && (
        <div className="modal-backdrop" role="dialog" aria-modal="true" aria-label="تعديل اشتراك"
          onClick={(e) => { if (e.target === e.currentTarget) setEdit(null); }}>
          <div className="modal" style={{ width: 'min(460px, 100%)', padding: '1.3rem' }}>
            <h3 style={{ margin: '0 0 .2rem' }}>تعديل اشتراك «{edit.org}»</h3>
            <p style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', margin: '0 0 1rem' }}>{edit.plan} · <span style={{ direction: 'ltr' }}>v{edit.version}</span></p>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <label style={{ display: 'grid', gap: '.3rem' }}>
                <span style={{ fontSize: '.8rem', fontWeight: 600 }}>الحالة</span>
                <select className="field" value={ef.status} onChange={(e) => setEf({ ...ef, status: e.target.value })} autoFocus>
                  {SUB_STATUSES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </label>
              <label style={{ display: 'grid', gap: '.3rem' }}>
                <span style={{ fontSize: '.8rem', fontWeight: 600 }}>المزوّد</span>
                <select className="field" value={ef.billing_provider} onChange={(e) => setEf({ ...ef, billing_provider: e.target.value })}>
                  {PROVIDERS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </label>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.7rem' }}>
                <label style={{ display: 'grid', gap: '.3rem' }}>
                  <span style={{ fontSize: '.8rem', fontWeight: 600 }}>انتهاء التجربة</span>
                  <input className="field" type="date" value={ef.trial_ends_at} onChange={(e) => setEf({ ...ef, trial_ends_at: e.target.value })} style={{ direction: 'ltr' }} />
                </label>
                <label style={{ display: 'grid', gap: '.3rem' }}>
                  <span style={{ fontSize: '.8rem', fontWeight: 600 }}>نهاية الدورة</span>
                  <input className="field" type="date" value={ef.current_period_end} onChange={(e) => setEf({ ...ef, current_period_end: e.target.value })} style={{ direction: 'ltr' }} />
                </label>
              </div>
            </div>
            <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.2rem', justifyContent: 'flex-end' }}>
              <button className="btn btn-sm btn-ghost" onClick={() => setEdit(null)}>إلغاء</button>
              <button className="btn btn-sm btn-primary" disabled={saving} onClick={save}><Icon name="check" size={14} /> {saving ? 'جارٍ الحفظ…' : 'حفظ واعتماد'}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
