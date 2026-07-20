import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { creatorNav } from '@/lib/nav';
import { Field, ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; number: string; title: string; type: string; typeLabel: string; platform: string | null;
  campaignName: string | null; status: string; statusLabel: string; statusTone: string;
}
interface Option { value: string; label: string }
interface Props {
  creatorName: string; items: Paginated<Row>; todo: number;
  collabs: { id: number; label: string }[]; types: Option[];
  /** تعاونات مقبولة لم تبدأ — سبب غياب قائمة الربط، يُقال بدل أن يُخفى */
  notStartedCollabs: number;
}

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

export default function CreatorContentIndex({ creatorName, items, todo, collabs, types, notStartedCollabs }: Props) {
  const [modal, setModal] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ title: '', type: types[0]?.value ?? 'post', platform: '', caption: '', media_url: '', collaboration_id: '' });

  const submit = () => {
    if (!form.title.trim()) return;
    setBusy(true);
    router.post(u('/content'), { ...form, collaboration_id: form.collaboration_id || null }, {
      onFinish: () => setBusy(false),
      onSuccess: () => { setModal(false); setForm({ title: '', type: types[0]?.value ?? 'post', platform: '', caption: '', media_url: '', collaboration_id: '' }); },
    });
  };

  return (
    <AppShell heading="المحتوى" nav={creatorNav} portal="creator" wsName={creatorName} wsPlan="بوابة المبدع">
      <Head title="المحتوى" />
      <ListHead eyebrow="بوابة المبدع" title="المحتوى" sub="أنشئ محتواك، أرفق رابطه، وقدّمه للمراجعة."
        actions={<button onClick={() => setModal(true)} className="btn btn-sm">+ محتوى جديد</button>} />

      <div className="ih-kpis">
        <Kpi label="بحاجة عمل" icon="image" tone={todo ? 'warning' : 'success'} value={todo.toLocaleString('en-US')} sub={todo ? 'مسودة/تعديل مطلوب' : 'لا شيء معلّق'} />
        <Kpi label="إجمالي المحتوى" icon="image" value={items.total.toLocaleString('en-US')} sub="لديك" />
      </div>

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا محتوى بعد — ابدأ بمحتوى جديد.</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>المحتوى</th><th>النوع</th><th>الحملة</th><th>الحالة</th><th>—</th></tr></thead>
              <tbody>
                {items.data.map((it) => (
                  <tr key={it.id}>
                    <td>
                      <Link href={u(`/content/${it.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{it.title}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{it.number}</div>
                    </td>
                    <td>{it.typeLabel}</td>
                    <td>{it.campaignName ?? '—'}</td>
                    <td><StatusBadge tone={it.statusTone} label={it.statusLabel} /></td>
                    <td><Link href={u(`/content/${it.id}`)} className="btn btn-xs btn-outline">عرض</Link></td>
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
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>محتوى جديد</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="العنوان" labelStyle={LBL}>
                <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="field" style={{ width: '100%' }} placeholder="عنوان المحتوى" autoFocus />
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="النوع" labelStyle={LBL}>
                  <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="field" style={{ width: '100%' }}>
                    {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </Field>
                <Field label="المنصّة" labelStyle={LBL}>
                  <input value={form.platform} onChange={(e) => setForm({ ...form, platform: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="instagram / tiktok…" />
                </Field>
              </div>
              {collabs.length > 0 ? (
                <Field label="ربط بتعاون (اختياري)" labelStyle={LBL}>
                  <select value={form.collaboration_id} onChange={(e) => setForm({ ...form, collaboration_id: e.target.value })} className="field" style={{ width: '100%' }}>
                    <option value="">— بدون —</option>
                    {collabs.map((cl) => <option key={cl.id} value={cl.id}>{cl.label}</option>)}
                  </select>
                </Field>
              ) : notStartedCollabs > 0 && (
                /* الربط هو ما يوصل المحتوى بالحملة ومراجعة العميل والفاتورة.
                   إخفاء الحقل بلا سبب يترك المبدع أمام نموذج ناقص لا يعرف علّته. */
                <div
                  role="status"
                  style={{
                    padding: '.85rem 1.05rem', borderRadius: 'var(--ih-radius-sm)',
                    background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)',
                    borderInlineStart: '3px solid var(--ih-info)', fontSize: '.82rem', lineHeight: 1.8,
                  }}
                >
                  لديك {notStartedCollabs === 1 ? 'تعاون مقبول لم يبدأ' : `${notStartedCollabs} تعاونات مقبولة لم تبدأ`}.
                  {' '}افتح التعاون واضغط «بدء العمل» ليصير قابلًا للربط — المحتوى غير المرتبط لا يصل الحملة ولا مراجعة العميل.
                  {' '}
                  <a href={u('/collaborations')} style={{ fontWeight: 600, color: 'inherit', textDecoration: 'underline' }}>افتح التعاونات</a>
                </div>
              )}
              <Field label="رابط الوسائط (اختياري)" labelStyle={LBL}>
                <input value={form.media_url} onChange={(e) => setForm({ ...form, media_url: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="https://…" />
              </Field>
              <Field label="النص التسويقي (اختياري)" labelStyle={LBL}>
                <textarea value={form.caption} onChange={(e) => setForm({ ...form, caption: e.target.value })} className="field" rows={3} style={{ width: '100%', resize: 'vertical' }} placeholder="اكتب الكابشن…" />
              </Field>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.title.trim()} onClick={submit} className="btn btn-primary">حفظ كمسودة</button>
              <button disabled={busy} onClick={() => setModal(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
