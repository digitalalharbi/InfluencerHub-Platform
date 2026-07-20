import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { partnerNav } from '@/lib/nav';
import { Field, ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; number: string; title: string; type: string; typeLabel: string;
  priority: string; priorityLabel: string; clientName: string | null; status: string; statusLabel: string; statusTone: string; assignee: string | null;
}
interface Option { value: string; label: string }
interface Props {
  agencyName: string; items: Paginated<Row>; open: number;
  clients: { id: number; name: string }[]; types: Option[]; priorities: Option[];
}

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

export default function PartnerRequestsIndex({ agencyName, items, open, clients, types, priorities }: Props) {
  const [modal, setModal] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ client_id: clients[0]?.id?.toString() ?? '', type: types[0]?.value ?? 'other', title: '', description: '', priority: 'normal' });

  const submit = () => {
    if (!form.title.trim() || !form.client_id) return;
    setBusy(true);
    router.post(u('/requests'), form, {
      onFinish: () => setBusy(false),
      onSuccess: () => { setModal(false); setForm({ client_id: clients[0]?.id?.toString() ?? '', type: types[0]?.value ?? 'other', title: '', description: '', priority: 'normal' }); },
    });
  };

  return (
    <AppShell heading="الطلبات" nav={partnerNav} portal="partner" wsName={agencyName} wsPlan="بوابة الشريك">
      <Head title="الطلبات" />
      <ListHead eyebrow="بوابة الشريك" title="الطلبات" sub="أرسل طلبات نيابة عن عملائك المرتبطين وتابعها."
        actions={clients.length > 0 ? <button onClick={() => setModal(true)} className="btn btn-sm">+ طلب جديد</button> : undefined} />

      <div className="ih-kpis">
        <Kpi label="طلبات مفتوحة" icon="inbox" tone={open ? 'warning' : 'success'} value={open.toLocaleString('en-US')} sub={open ? 'قيد التنفيذ' : 'لا شيء مفتوح'} />
        <Kpi label="إجمالي الطلبات" icon="clipboard-check" value={items.total.toLocaleString('en-US')} sub="لديك" />
      </div>

      {clients.length === 0 && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.84rem' }}>
          لا عملاء مرتبطون بك حاليًا — لا يمكن إنشاء طلبات حتى تُربط بعميل.
        </div>
      )}

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا طلبات بعد.</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>الطلب</th><th>العميل</th><th>النوع</th><th>الأولوية</th><th>الحالة</th><th>—</th></tr></thead>
              <tbody>
                {items.data.map((s) => (
                  <tr key={s.id}>
                    <td>
                      <Link href={u(`/requests/${s.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{s.title}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{s.number}</div>
                    </td>
                    <td>{s.clientName ?? '—'}</td>
                    <td>{s.typeLabel}</td>
                    <td>{s.priorityLabel}</td>
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
              <Field label="العميل" labelStyle={LBL}>
                <select value={form.client_id} onChange={(e) => setForm({ ...form, client_id: e.target.value })} className="field" style={{ width: '100%' }}>
                  {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </Field>
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
              <Field label="التفاصيل (اختياري)" labelStyle={LBL}>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="field" rows={4} style={{ width: '100%', resize: 'vertical' }} placeholder="اشرح ما يحتاجه العميل…" />
              </Field>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.title.trim() || !form.client_id} onClick={submit} className="btn btn-primary">إرسال الطلب</button>
              <button disabled={busy} onClick={() => setModal(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
