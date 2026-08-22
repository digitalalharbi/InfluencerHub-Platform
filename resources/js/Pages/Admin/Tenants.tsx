import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi, numFmt } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Row { id: number; name: string; slug: string; mode: string; status: string; statusLabel: string; statusTone: string; orgs: number; members: number; sub: boolean }
interface Summary {
  total: number; active: number; saas: number; withSub: number;
  byStatus: { status: string; label: string; count: number }[];
}
interface Props { tenants: Paginated<Row>; summary: Summary; filters: { q: string | null; status: string | null } }

const MODE_LABEL: Record<string, string> = { saas: 'سحابي (SaaS)', dedicated: 'مخصّص', self_hosted: 'استضافة ذاتية' };
const STATUSES: [string, string][] = [['active', 'نشط'], ['suspended', 'معلّق'], ['pending', 'قيد الانتظار'], ['archived', 'مؤرشف']];
const MODES: [string, string][] = [['saas', 'سحابي (SaaS)'], ['dedicated', 'مخصّص'], ['self_hosted', 'استضافة ذاتية']];

export default function AdminTenants({ tenants, summary, filters }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const first = useRef(true);
  const [edit, setEdit] = useState<Row | null>(null);
  const [ef, setEf] = useState({ name: '', status: '', deployment_mode: '' });
  const [saving, setSaving] = useState(false);
  const openEdit = (t: Row) => { setEf({ name: t.name, status: t.status, deployment_mode: t.mode }); setEdit(t); };
  const save = () => {
    if (!edit) return;
    setSaving(true);
    router.post(u(`/tenants/${edit.id}`), ef, { preserveScroll: true, onFinish: () => setSaving(false), onSuccess: () => setEdit(null) });
  };

  const apply = (patch: Record<string, string | undefined>) =>
    router.get(u('/tenants'), clean({ q: q || undefined, status: filters.status || undefined, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => apply({ q: q || undefined }), 350);
    return () => clearTimeout(t);
  }, [q]);

  return (
    <AppShell heading="المستأجرون" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="المستأجرون" />
      <ListHead eyebrow="المنصّة · إشراف" title="المستأجرون" sub="كل المنشآت المستضافة على المنصّة — مؤسّساتها وأعضاؤها واشتراكها." />

      <div className="ih-kpis">
        <Kpi label="إجمالي المستأجرين" icon="building-2" value={numFmt(summary.total)} sub={`${summary.active} نشط`} />
        <Kpi label="نشطون" icon="shield-check" tone="success" value={numFmt(summary.active)} sub="حالة نشطة" />
        <Kpi label="سحابيون (SaaS)" icon="layout-dashboard" tone="accent" value={numFmt(summary.saas)} sub="نمط الاستضافة" />
        <Kpi label="باشتراك نشط" icon="wallet" tone="warning" value={numFmt(summary.withSub)} sub="trialing/active" />
      </div>

      {/* شرائح الحالة */}
      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        <button onClick={() => apply({ status: undefined })} className={`ih-chip${!filters.status ? ' active' : ''}`}>الكل <span className="ih-chip__count">{summary.total}</span></button>
        {summary.byStatus.map((s) => (
          <button key={s.status} onClick={() => apply({ status: filters.status === s.status ? undefined : s.status })}
            className={`ih-chip${filters.status === s.status ? ' active' : ''}`}>{s.label} <span className="ih-chip__count">{s.count}</span></button>
        ))}
      </div>

      {/* البحث */}
      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={15} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالاسم أو المعرّف…" />
        </label>
        {(filters.q || filters.status) && <button className="btn btn-sm btn-ghost" onClick={() => { setQ(''); router.get(u('/tenants')); }}>مسح</button>}
      </div>

      {tenants.data.length === 0 ? (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="building-2" size={26} /></span>
          <div className="ih-empty__title">لا مستأجرين</div>
          <div className="ih-empty__text">لا نتائج مطابقة للبحث أو الحالة.</div>
        </div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>المستأجر</th><th>النمط</th><th>المؤسسات</th><th>الأعضاء</th><th>اشتراك</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                  {tenants.data.map((t) => (
                    <tr key={t.id}>
                      <td>
                        <div className="ih-idc">
                          <span className="ih-idc__av">{t.name.slice(0, 1)}</span>
                          <span className="ih-idc__main">
                            <span className="ih-idc__name">{t.name}</span>
                            <span className="ih-idc__sub" style={{ direction: 'ltr', textAlign: 'right' }}>{t.slug}</span>
                          </span>
                        </div>
                      </td>
                      <td><span className="ih-tag">{MODE_LABEL[t.mode] ?? t.mode}</span></td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{t.orgs.toLocaleString('en-US')}</td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{t.members.toLocaleString('en-US')}</td>
                      <td>{t.sub ? <span className="badge" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>نشط</span> : <span style={{ color: 'var(--ih-text-muted)' }}>—</span>}</td>
                      <td><StatusBadge tone={t.statusTone} label={t.statusLabel} /></td>
                      <td style={{ textAlign: 'end' }}><button className="btn btn-xs btn-outline" onClick={() => openEdit(t)}><Icon name="pencil" size={12} /> تعديل</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot"><span>{tenants.total.toLocaleString('en-US')} مستأجر</span><Pagination links={tenants.links} /></div>
            </div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {tenants.data.map((t) => (
                <div key={t.id} className="ih-mcard">
                  <div className="ih-mcard__top">
                    <span className="ih-idc__av" style={{ width: 42, height: 42 }}>{t.name.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{t.name} <StatusBadge tone={t.statusTone} label={t.statusLabel} /></div>
                      <div className="ih-idc__sub" style={{ direction: 'ltr', textAlign: 'right' }}>{t.slug}</div>
                    </div>
                  </div>
                  <div className="ih-mcard__grid">
                    <div><span className="ih-mcard__lbl">النمط</span><span className="ih-mcard__val">{MODE_LABEL[t.mode] ?? t.mode}</span></div>
                    <div><span className="ih-mcard__lbl">المؤسسات</span><span className="ih-mcard__val">{t.orgs}</span></div>
                    <div><span className="ih-mcard__lbl">الأعضاء</span><span className="ih-mcard__val">{t.members}</span></div>
                  </div>
                  <button className="btn btn-xs btn-outline" style={{ marginTop: '.6rem' }} onClick={() => openEdit(t)}><Icon name="pencil" size={12} /> تعديل</button>
                </div>
              ))}
            </div>
            <Pagination links={tenants.links} />
          </div>
        </>
      )}

      {edit && (
        <div className="modal-backdrop" role="dialog" aria-modal="true" aria-label="تعديل مستأجر"
          onClick={(e) => { if (e.target === e.currentTarget) setEdit(null); }}>
          <div className="modal" style={{ width: 'min(440px, 100%)', padding: '1.3rem' }}>
            <h3 style={{ margin: '0 0 1rem' }}>تعديل المستأجر</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <label style={{ display: 'grid', gap: '.3rem' }}>
                <span style={{ fontSize: '.8rem', fontWeight: 600 }}>الاسم</span>
                <input className="field" value={ef.name} onChange={(e) => setEf({ ...ef, name: e.target.value })} autoFocus />
              </label>
              <label style={{ display: 'grid', gap: '.3rem' }}>
                <span style={{ fontSize: '.8rem', fontWeight: 600 }}>الحالة</span>
                <select className="field" value={ef.status} onChange={(e) => setEf({ ...ef, status: e.target.value })}>
                  {STATUSES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </label>
              <label style={{ display: 'grid', gap: '.3rem' }}>
                <span style={{ fontSize: '.8rem', fontWeight: 600 }}>نمط الاستضافة</span>
                <select className="field" value={ef.deployment_mode} onChange={(e) => setEf({ ...ef, deployment_mode: e.target.value })}>
                  {MODES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </label>
            </div>
            <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.2rem', justifyContent: 'flex-end' }}>
              <button className="btn btn-sm btn-ghost" onClick={() => setEdit(null)}>إلغاء</button>
              <button className="btn btn-sm btn-primary" disabled={saving || !ef.name.trim()} onClick={save}><Icon name="check" size={14} /> {saving ? 'جارٍ الحفظ…' : 'حفظ واعتماد'}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

function clean(obj: Record<string, string | undefined>) {
  return Object.fromEntries(Object.entries(obj).filter(([, v]) => v != null && v !== ''));
}
