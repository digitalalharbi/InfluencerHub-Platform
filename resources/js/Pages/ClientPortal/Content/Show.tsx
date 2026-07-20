import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { WorkspaceHeader, Sec, StatusBadge, Field } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Item {
  id: number; number: string; title: string; type: string; platform: string | null;
  creator: string | null; campaign: string | null; status: string; statusLabel: string; statusTone: string;
  caption: string | null; mediaUrl: string | null; version: number; scheduledAt: string | null; publishedAt: string | null;
}
interface History { from: string | null; to: string; tone: string; actor: string; note: string | null; at: string | null }
interface Props { clientName: string; item: Item; history: History[]; canReview: boolean; isPending: boolean }

export default function ClientContentShow({ clientName, item, history, canReview, isPending }: Props) {
  const [modal, setModal] = useState(false);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const base = u(`/content/${item.id}`);

  const approve = () => { setBusy(true); router.post(`${base}/approve`, {}, { preserveScroll: true, onFinish: () => setBusy(false) }); };
  const requestChanges = () => {
    if (reason.trim().length < 3) return;
    setBusy(true);
    router.post(`${base}/request-changes`, { reason }, { preserveScroll: true, onFinish: () => { setBusy(false); setModal(false); setReason(''); } });
  };

  return (
    <AppShell heading="مراجعة المحتوى" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title={item.title} />

      <WorkspaceHeader
        eyebrow={`محتوى · ${item.number}`}
        title={item.title}
        statusTone={item.statusTone} statusLabel={item.statusLabel}
        back={u("/content")} backLabel="المحتوى"
        meta={[
          ['الحملة', item.campaign ?? '—'], ['المبدع', item.creator ?? '—'],
          ['النوع', item.type], ['المنصّة', item.platform ?? '—'],
        ]}
        actions={
          canReview && isPending ? (
            <>
              <button disabled={busy} onClick={approve} className="btn btn-sm">اعتماد</button>
              <button disabled={busy} onClick={() => setModal(true)} className="btn btn-sm btn-outline">طلب تعديل</button>
            </>
          ) : undefined
        }
      />

      {!canReview && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.84rem' }}>
          <Icon name="shield-check" size={14} /> العرض فقط — اعتماد المحتوى متاح لأدوار محدّدة في فريقك (المدير / مراجع المحتوى).
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.3fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="المحتوى" icon="image">
          {item.mediaUrl ? (
            <a href={item.mediaUrl} target="_blank" rel="noopener noreferrer" className="btn btn-sm btn-outline" style={{ marginBottom: '.9rem', display: 'inline-flex', gap: '.4rem' }}>
              <Icon name="image" size={15} /> فتح الوسائط <span style={{ direction: 'ltr', fontSize: '.7rem', opacity: .7 }}>↗</span>
            </a>
          ) : (
            <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', marginBottom: '.9rem' }}>لا وسائط مرفقة بعد.</div>
          )}
          <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>النص التسويقي</div>
          <div className="card" style={{ padding: '.9rem 1rem', whiteSpace: 'pre-wrap', fontSize: '.9rem', lineHeight: 1.7, minHeight: 60 }}>
            {item.caption || <span style={{ color: 'var(--ih-text-muted)' }}>—</span>}
          </div>
          <div style={{ display: 'flex', gap: '1.2rem', marginTop: '.8rem', fontSize: '.78rem', color: 'var(--ih-text-muted)', flexWrap: 'wrap' }}>
            <span>الإصدار: <b style={{ direction: 'ltr' }}>{item.version}</b></span>
            {item.scheduledAt && <span>مجدول: <b style={{ direction: 'ltr' }}>{item.scheduledAt}</b></span>}
            {item.publishedAt && <span>نُشر: <b style={{ direction: 'ltr' }}>{item.publishedAt}</b></span>}
          </div>
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

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 460 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>طلب تعديل المحتوى</h3>
            <Field label="سبب التعديل المطلوب" labelStyle={{ fontSize: '.82rem', fontWeight: 600, display: 'block', marginBottom: '.4rem' }}>
              <textarea value={reason} onChange={(e) => setReason(e.target.value)} className="field" rows={4}
                placeholder="اشرح ما الذي يجب تعديله…" style={{ width: '100%', resize: 'vertical' }} autoFocus />
            </Field>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || reason.trim().length < 3} onClick={requestChanges} className="btn btn-primary">إرسال الطلب</button>
              <button disabled={busy} onClick={() => setModal(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
