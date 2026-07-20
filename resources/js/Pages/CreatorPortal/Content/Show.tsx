import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { creatorNav } from '@/lib/nav';
import { WorkspaceHeader, Sec, StatusBadge, Field } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

interface Item {
  id: number; number: string; title: string; type: string; typeLabel: string; platform: string | null;
  campaignName: string | null; campaign: string | null; status: string; statusLabel: string; statusTone: string;
  caption: string | null; mediaUrl: string | null; version: number;
}
interface History { to: string; tone: string; actor: string; note: string | null; at: string | null }
interface Props { creatorName: string; item: Item; history: History[]; editable: boolean }

export default function CreatorContentShow({ creatorName, item, history, editable }: Props) {
  const [edit, setEdit] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ title: item.title, caption: item.caption ?? '', media_url: item.mediaUrl ?? '', platform: item.platform ?? '' });

  const save = () => {
    setBusy(true);
    router.post(u(`/content/${item.id}/update`), form, {
      preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setEdit(false),
    });
  };
  const submit = () => {
    setBusy(true);
    router.post(u(`/content/${item.id}/submit`), {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  return (
    <AppShell heading="محتوى" nav={creatorNav} portal="creator" wsName={creatorName} wsPlan="بوابة المبدع">
      <Head title={item.title} />

      <WorkspaceHeader
        eyebrow={`محتوى · ${item.number}`}
        title={item.title}
        statusTone={item.statusTone} statusLabel={item.statusLabel}
        back={u("/content")} backLabel="المحتوى"
        meta={[['النوع', item.typeLabel], ['المنصّة', item.platform ?? '—'], ['الحملة', item.campaign ?? '—']]}
        actions={editable ? (
          <>
            {!edit && <button disabled={busy} onClick={() => setEdit(true)} className="btn btn-sm btn-outline">تعديل</button>}
            <button disabled={busy} onClick={submit} className="btn btn-sm">تقديم للمراجعة</button>
          </>
        ) : undefined}
      />

      {item.status === 'changes_requested' && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.84rem' }}>
          <Icon name="clipboard-check" size={14} /> طُلب تعديل على هذا المحتوى — راجع سجل الحالة، عدّل، ثم أعِد التقديم.
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="المحتوى" icon="image">
          {edit ? (
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="العنوان" labelStyle={LBL}>
                <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="field" style={{ width: '100%' }} />
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="المنصّة" labelStyle={LBL}>
                  <input value={form.platform} onChange={(e) => setForm({ ...form, platform: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
                <Field label="رابط الوسائط" labelStyle={LBL}>
                  <input value={form.media_url} onChange={(e) => setForm({ ...form, media_url: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="https://…" />
                </Field>
              </div>
              <Field label="النص التسويقي" labelStyle={LBL}>
                <textarea value={form.caption} onChange={(e) => setForm({ ...form, caption: e.target.value })} className="field" rows={4} style={{ width: '100%', resize: 'vertical' }} />
              </Field>
              <div style={{ display: 'flex', gap: '.5rem' }}>
                <button disabled={busy || !form.title.trim()} onClick={save} className="btn btn-sm btn-primary">حفظ</button>
                <button disabled={busy} onClick={() => setEdit(false)} className="btn btn-sm btn-ghost">إلغاء</button>
              </div>
            </div>
          ) : (
            <>
              {item.mediaUrl ? (
                <a href={item.mediaUrl} target="_blank" rel="noopener noreferrer" className="btn btn-sm btn-outline" style={{ marginBottom: '.9rem', display: 'inline-flex', gap: '.4rem' }}>
                  <Icon name="image" size={15} /> فتح الوسائط ↗
                </a>
              ) : (
                <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', marginBottom: '.9rem' }}>لا رابط وسائط بعد.</div>
              )}
              <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>النص التسويقي</div>
              <div className="card" style={{ padding: '.9rem 1rem', whiteSpace: 'pre-wrap', fontSize: '.9rem', lineHeight: 1.7, minHeight: 60 }}>
                {item.caption || <span style={{ color: 'var(--ih-text-muted)' }}>—</span>}
              </div>
              <div style={{ marginTop: '.7rem', fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>الإصدار: <b style={{ direction: 'ltr' }}>{item.version}</b></div>
            </>
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
