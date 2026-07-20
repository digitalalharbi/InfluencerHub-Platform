import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { WorkspaceHeader, Sec, StatusBadge, Field } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

interface Brand {
  id: number; name: string; sector: string | null; status: string; statusLabel: string; statusTone: string;
  website: string | null; description: string | null; toneOfVoice: string | null; targetAudience: string | null;
  preferredLanguage: string | null; changesReason: string | null;
}
interface History { to: string; tone: string; actor: string; note: string | null; at: string | null }
interface Props { clientName: string; brand: Brand; history: History[]; canManage: boolean; editable: boolean }

function ReadRow({ label, value }: { label: string; value: string | null }) {
  return (
    <div style={{ borderBottom: '1px solid var(--ih-border)', paddingBottom: '.5rem' }}>
      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.15rem' }}>{label}</div>
      <div style={{ fontSize: '.88rem' }}>{value || <span style={{ color: 'var(--ih-text-muted)' }}>—</span>}</div>
    </div>
  );
}

export default function ClientBrandShow({ clientName, brand, history, canManage, editable }: Props) {
  const [edit, setEdit] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    name: brand.name, sector: brand.sector ?? '', website: brand.website ?? '', description: brand.description ?? '',
    tone_of_voice: brand.toneOfVoice ?? '', target_audience: brand.targetAudience ?? '', preferred_language: brand.preferredLanguage ?? '',
  });

  const save = () => {
    setBusy(true);
    router.post(u(`/brands/${brand.id}/update`), form, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setEdit(false) });
  };
  const submit = () => {
    setBusy(true);
    router.post(u(`/brands/${brand.id}/submit`), {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  return (
    <AppShell heading="علامة تجارية" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title={brand.name} />

      <WorkspaceHeader
        eyebrow="علامة تجارية"
        title={brand.name}
        statusTone={brand.statusTone} statusLabel={brand.statusLabel}
        back={u("/brands")} backLabel="علاماتي"
        meta={[['القطاع', brand.sector ?? '—'], ['اللغة', brand.preferredLanguage ?? '—']]}
        actions={canManage && editable ? (
          <>
            {!edit && <button disabled={busy} onClick={() => setEdit(true)} className="btn btn-sm btn-outline">تعديل</button>}
            <button disabled={busy} onClick={submit} className="btn btn-sm">إرسال للمراجعة</button>
          </>
        ) : undefined}
      />

      {brand.status === 'changes_requested' && brand.changesReason && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.84rem' }}>
          <Icon name="clipboard-check" size={14} /> طُلب تعديل: {brand.changesReason}
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="ملف العلامة" icon="bookmark">
          {edit ? (
            <div style={{ display: 'grid', gap: '.8rem' }}>
              {([['name', 'الاسم'], ['sector', 'القطاع'], ['website', 'الموقع'], ['target_audience', 'الجمهور المستهدف'], ['tone_of_voice', 'نبرة الصوت'], ['preferred_language', 'اللغة المفضّلة']] as const).map(([k, label]) => (
                <Field key={k} label={label} labelStyle={LBL}>
                  <input value={(form as Record<string, string>)[k]} onChange={(e) => setForm({ ...form, [k]: e.target.value })} className="field" style={{ width: '100%', direction: k === 'website' ? 'ltr' : undefined }} />
                </Field>
              ))}
              <Field label="الوصف" labelStyle={LBL}>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="field" rows={4} style={{ width: '100%', resize: 'vertical' }} />
              </Field>
              <div style={{ display: 'flex', gap: '.5rem' }}>
                <button disabled={busy || !form.name.trim()} onClick={save} className="btn btn-sm btn-primary">حفظ</button>
                <button disabled={busy} onClick={() => setEdit(false)} className="btn btn-sm btn-ghost">إلغاء</button>
              </div>
            </div>
          ) : (
            <div style={{ display: 'grid', gap: '.7rem' }}>
              <ReadRow label="الموقع" value={brand.website} />
              <ReadRow label="الجمهور المستهدف" value={brand.targetAudience} />
              <ReadRow label="نبرة الصوت" value={brand.toneOfVoice} />
              <ReadRow label="الوصف" value={brand.description} />
            </div>
          )}
        </Sec>

        <Sec title="سجل الحالة" icon="clipboard-check">
          {history.length === 0 ? (
            <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)' }}>لا سجل بعد.</div>
          ) : (
            <div style={{ display: 'grid', gap: '.7rem' }}>
              {history.map((h, i) => (
                <div key={i} style={{ borderInlineStart: '2px solid var(--ih-border)', paddingInlineStart: '.7rem' }}>
                  <div style={{ display: 'flex', gap: '.4rem', alignItems: 'center', flexWrap: 'wrap' }}>
                    <StatusBadge tone={h.tone} label={h.to} />
                    <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{h.actor}</span>
                    {h.at && <span style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)', direction: 'ltr', marginInlineStart: 'auto' }}>{h.at}</span>}
                  </div>
                  {h.note && <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', marginTop: '.2rem' }}>{h.note}</div>}
                </div>
              ))}
            </div>
          )}
        </Sec>
      </div>
    </AppShell>
  );
}
