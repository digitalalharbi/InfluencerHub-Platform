import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { Field, ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; number: string; title: string; type: string; typeLabel: string;
  priority: string; priorityLabel: string; status: string; statusLabel: string; statusTone: string; assignee: string | null;
}
interface Option { value: string; label: string }
interface Props {
  clientName: string; items: Paginated<Row>; open: number;
  brands: { id: number; name: string }[]; types: Option[]; priorities: Option[];
  platformOptions: Record<string, string>;
}

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };
const EMPTY_FORM = (type: string) => ({
  type, title: '', description: '', priority: 'normal', brand_id: '',
  budget: '', preferred_start_date: '', preferred_end_date: '', platforms: [] as string[], scope_notes: '',
});

export default function ClientRequestsIndex({ clientName, items, open, brands, types, priorities, platformOptions }: Props) {
  const [modal, setModal] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM(types[0]?.value ?? 'other'));
  // موجز الحملة يظهر فقط حين يكون النوع حملة — لا نعرض حقولًا لا تخصّ الطلب
  const isCampaign = form.type === 'campaign';

  const submit = () => {
    if (!form.title.trim()) return;
    setBusy(true);
    // ما لا يخصّ الحملة لا يُرسَل أصلًا
    const brief = isCampaign ? {
      budget: form.budget || null,
      preferred_start_date: form.preferred_start_date || null,
      preferred_end_date: form.preferred_end_date || null,
      platforms: form.platforms.length ? form.platforms : null,
      scope_notes: form.scope_notes || null,
    } : {};
    router.post(u('/requests'), {
      type: form.type, title: form.title, description: form.description,
      priority: form.priority, brand_id: form.brand_id || null, ...brief,
    }, {
      onFinish: () => setBusy(false),
      onSuccess: () => { setModal(false); setForm(EMPTY_FORM(types[0]?.value ?? 'other')); },
    });
  };

  return (
    <AppShell heading="الطلبات" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title="الطلبات" />
      <ListHead eyebrow="بوابة العميل" title="الطلبات" sub="أرسل طلباتك للوكالة وتابع حالتها."
        actions={<button onClick={() => setModal(true)} className="btn btn-sm">+ طلب جديد</button>} />

      <div className="ih-kpis">
        <Kpi label="طلبات مفتوحة" icon="inbox" tone={open ? 'warning' : 'success'} value={open.toLocaleString('en-US')} sub={open ? 'قيد التنفيذ' : 'لا شيء مفتوح'} />
        <Kpi label="إجمالي الطلبات" icon="clipboard-check" value={items.total.toLocaleString('en-US')} sub="لديك" />
      </div>

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا طلبات بعد — ابدأ بطلب جديد.</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>الطلب</th><th>النوع</th><th>الأولوية</th><th>المسؤول</th><th>الحالة</th><th>—</th></tr></thead>
              <tbody>
                {items.data.map((s) => (
                  <tr key={s.id}>
                    <td>
                      <Link href={u(`/requests/${s.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{s.title}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{s.number}</div>
                    </td>
                    <td>{s.typeLabel}</td>
                    <td>{s.priorityLabel}</td>
                    <td>{s.assignee ?? '—'}</td>
                    <td><StatusBadge tone={s.statusTone} label={s.statusLabel} /></td>
                    <td><Link href={u(`/requests/${s.id}`)} className="btn btn-xs btn-outline">عرض</Link></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
          <Pagination links={items.links} />
        </>
      )}

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 520 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>طلب خدمة جديد</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="العنوان" labelStyle={LBL}>
                <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="field" style={{ width: '100%' }} placeholder="ملخص مختصر للطلب" autoFocus />
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="النوع" labelStyle={LBL}>
                  <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="field" style={{ width: '100%' }}>
                    {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </Field>
                <Field label="الأولوية" labelStyle={LBL}>
                  <select value={form.priority} onChange={(e) => setForm({ ...form, priority: e.target.value })} className="field" style={{ width: '100%' }}>
                    {priorities.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                  </select>
                </Field>
              </div>
              {brands.length > 0 && (
                <Field label="العلامة التجارية (اختياري)" labelStyle={LBL}>
                  <select value={form.brand_id} onChange={(e) => setForm({ ...form, brand_id: e.target.value })} className="field" style={{ width: '100%' }}>
                    <option value="">— بدون —</option>
                    {brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                  </select>
                </Field>
              )}
              <Field label="التفاصيل (اختياري)" labelStyle={LBL}>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="field" rows={4} style={{ width: '100%', resize: 'vertical' }} placeholder="اشرح ما تحتاجه بالتفصيل…" />
              </Field>

              {isCampaign && (
                <div style={{ borderTop: '1px solid var(--ih-border)', paddingTop: '.9rem', display: 'grid', gap: '.8rem' }}>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: '.86rem' }}>موجز الحملة</div>
                    <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)', marginTop: '.15rem' }}>
                      ما تُدخله هنا ينتقل تلقائيًا إلى الحملة عند اعتماد طلبك — لن يُطلب منك مرّة أخرى.
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
                    <Field label="الميزانية (ر.س)" labelStyle={LBL}>
                      <input type="number" min={0} step="0.01" value={form.budget}
                        onChange={(e) => setForm({ ...form, budget: e.target.value })}
                        className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="50000" />
                    </Field>
                    <Field label="البداية المفضّلة" labelStyle={LBL}>
                      <input type="date" value={form.preferred_start_date}
                        onChange={(e) => setForm({ ...form, preferred_start_date: e.target.value })}
                        className="field" style={{ width: '100%', direction: 'ltr' }} />
                    </Field>
                    <Field label="النهاية المفضّلة" labelStyle={LBL}>
                      <input type="date" value={form.preferred_end_date}
                        onChange={(e) => setForm({ ...form, preferred_end_date: e.target.value })}
                        className="field" style={{ width: '100%', direction: 'ltr' }} />
                    </Field>
                  </div>
                  <Field label="المنصّات المطلوبة" labelStyle={LBL}>
                    {(g) => (
                      <div {...g} role="group" style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap' }}>
                        {Object.entries(platformOptions).map(([k, v]) => (
                          <label key={k} style={{ display: 'inline-flex', alignItems: 'center', gap: '.3rem', fontSize: '.8rem' }}>
                            <input type="checkbox" checked={form.platforms.includes(k)}
                              onChange={(e) => setForm({ ...form, platforms: e.target.checked
                                ? [...form.platforms, k] : form.platforms.filter((p) => p !== k) })} />
                            {v}
                          </label>
                        ))}
                      </div>
                    )}
                  </Field>
                  <Field label="نطاق العمل المطلوب" labelStyle={LBL}>
                    <textarea value={form.scope_notes} onChange={(e) => setForm({ ...form, scope_notes: e.target.value })}
                      className="field" rows={2} style={{ width: '100%', resize: 'vertical' }}
                      placeholder="عدد المنشورات، نوع المحتوى، الجمهور المستهدف…" />
                  </Field>
                </div>
              )}
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.title.trim()} onClick={submit} className="btn btn-primary">إرسال الطلب</button>
              <button disabled={busy} onClick={() => setModal(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
