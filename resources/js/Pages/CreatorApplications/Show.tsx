import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { WorkspaceHeader, SummaryStrip, Sec, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Platform { platform: string; username: string | null; followers: number; status: string; statusTone: string }
interface App {
  id: number; reference: string; name: string; email: string | null; type: string; typeLabel: string;
  country: string | null; status: string; statusLabel: string; statusTone: string; submittedAt: string | null;
  phone: string | null; city: string | null; bio: string | null; categories: string[]; platforms: Platform[];
  reviewer: string | null; rejectionReason: string | null;
}
interface History { to: string; tone: string; actor: string; note: string | null; at: string | null }
interface Doc { id: number; title: string | null; kind: string | null; sizeKb: number; status: string | null; uploadedAt: string | null }
interface Msg { body: string; fromAgency: boolean; at: string | null }
interface Note { body: string | null; by: string; at: string | null }
interface Verification { mowthooq: string | null; mowthooqReason: string | null; financial: string | null }
interface Props {
  application: App; history: History[]; canReview: boolean; canApprove: boolean; isPending: boolean;
  documents: Doc[]; messages: Msg[]; notes: Note[]; verification: Verification;
}

/** حالات التحقّق تُعرض بتسمية عربية صريحة — لا مفاتيح خام. */
const VERIFY_LABEL: Record<string, string> = {
  verified: 'موثّق', rejected: 'مرفوض', pending: 'قيد التحقّق', not_required: 'غير مطلوب',
};
const VERIFY_TONE: Record<string, { bg: string; fg: string }> = {
  verified: { bg: 'var(--ih-success-soft)', fg: 'var(--ih-success-ink)' },
  rejected: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  pending: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
};

const fmt = (n: number) => n >= 1000 ? Math.round(n / 1000).toLocaleString('en-US') + 'K' : n.toLocaleString('en-US');

export default function CreatorApplicationShow({ application: a, history, canReview, canApprove, isPending, documents, messages, notes, verification }: Props) {
  const [modal, setModal] = useState<null | 'reject' | 'completion' | 'suspend'>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [msgBody, setMsgBody] = useState('');
  const [internalNote, setInternalNote] = useState('');
  const base = u(`/creator-applications/${a.id}`);

  const act = (path: string, data: Record<string, string> = {}) => { setBusy(true); router.post(`${base}/${path}`, data, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => { setModal(null); setNote(''); } }); };
  const submitModal = () => {
    if (note.trim().length < 2) return;
    if (modal === 'reject') act('reject', { reason: note });
    else if (modal === 'suspend') act('suspend', { reason: note });
    else act('request-completion', { message: note });
  };

  return (
    <AppShell heading="طلب انضمام">
      <Head title={a.name} />

      <WorkspaceHeader
        eyebrow={`طلب انضمام · ${a.reference}`}
        title={a.name}
        statusTone={a.statusTone} statusLabel={a.statusLabel}
        back={u("/creator-applications")} backLabel="طلبات الانضمام"
        meta={[
          ['النوع', a.typeLabel], ['الدولة', a.country ?? '—'], ['المدينة', a.city ?? '—'],
          ['المراجع', a.reviewer ?? 'غير مُسنَد'],
        ]}
        actions={canReview && isPending ? (
          <>
            {canApprove && <button disabled={busy} onClick={() => act('approve')} className="btn btn-sm">قبول وإنشاء مبدع</button>}
            <button disabled={busy} onClick={() => act('assign')} className="btn btn-sm btn-outline">إسناد إليّ</button>
            <button disabled={busy} onClick={() => { setModal('completion'); setNote(''); }} className="btn btn-sm btn-outline">طلب استكمال</button>
            <button disabled={busy} onClick={() => { setModal('suspend'); setNote(''); }} className="btn btn-sm btn-ghost">تعليق</button>
            <button disabled={busy} onClick={() => { setModal('reject'); setNote(''); }} className="btn btn-sm btn-danger">رفض</button>
          </>
        ) : undefined}
      />

      {a.rejectionReason && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-danger)', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.84rem' }}>
          <Icon name="activity" size={14} /> سبب الرفض: {a.rejectionReason}
        </div>
      )}

      <SummaryStrip
        items={[
          { label: 'المنصّات', value: a.platforms.length.toLocaleString('en-US'), icon: 'radar' },
          { label: 'إجمالي المتابعين', value: fmt(a.platforms.reduce((s, p) => s + p.followers, 0)), icon: 'users' },
          { label: 'البريد', value: a.email ?? '—', icon: 'file-text' },
          { label: 'الهاتف', value: a.phone ?? '—', icon: 'inbox' },
        ]}
      />

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="بيانات المتقدّم" icon="user-plus">
          {a.bio && <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '.9rem', fontSize: '.88rem', lineHeight: 1.7 }}>{a.bio}</div>}
          {a.categories.length > 0 && (
            <div style={{ marginBottom: '.9rem' }}>
              <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>الفئات</div>
              <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>{a.categories.map((c, i) => <span key={i} className="ih-tag" style={{ fontSize: '.7rem' }}>{c}</span>)}</div>
            </div>
          )}
          <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>المنصّات</div>
          {a.platforms.length === 0 ? (
            <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>لا منصّات مُدرجة.</div>
          ) : (
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>المنصّة</th><th>الحساب</th><th>المتابعون</th><th>الحالة</th></tr></thead>
                <tbody>
                  {a.platforms.map((p, i) => (
                    <tr key={i}>
                      <td>{p.platform}</td>
                      <td style={{ direction: 'ltr' }}>{p.username ?? '—'}</td>
                      <td style={{ direction: 'ltr' }}>{fmt(p.followers)}</td>
                      <td><StatusBadge tone={p.statusTone} label={p.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div></div>
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

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(null)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 440 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>
              {modal === 'reject' ? 'رفض الطلب' : modal === 'suspend' ? 'تعليق الطلب' : 'طلب استكمال البيانات'}
            </h3>
            <textarea value={note} onChange={(e) => setNote(e.target.value)} className="field" rows={3}
              placeholder={modal === 'reject' ? 'سبب الرفض (إلزامي)' : modal === 'suspend' ? 'سبب التعليق (إلزامي)' : 'رسالة للمتقدّم بما يجب استكماله (إلزامي)'}
              style={{ width: '100%', resize: 'vertical' }} autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || note.trim().length < 2} onClick={submitModal} className={`btn ${modal === 'reject' ? 'btn-danger' : 'btn-primary'}`}>تأكيد</button>
              <button disabled={busy} onClick={() => setModal(null)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
      {/* التحقّقات — قرار صريح لكل مسار، والحالة تُعرض بتسمية لا بمفتاح */}
      <Sec title="التحقّقات" icon="shield-check">
        <div className="ih-sec__body" style={{ display: 'grid', gap: '.9rem' }}>
          {([
            ['موثوق', verification.mowthooq, 'mowthooq-review', verification.mowthooqReason],
            ['التحقّق المالي', verification.financial, 'financial-review', null],
          ] as [string, string | null, string, string | null][]).map(([label, state, path, reason]) => {
            const tone = VERIFY_TONE[state ?? ''] ?? { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-muted)' };
            return (
              <div key={path} style={{ display: 'flex', alignItems: 'center', gap: '.7rem', flexWrap: 'wrap' }}>
                <span style={{ fontWeight: 600, fontSize: '.86rem', minWidth: 110 }}>{label}</span>
                <span className="badge" style={{ background: tone.bg, color: tone.fg, fontSize: '.68rem' }}>
                  {VERIFY_LABEL[state ?? ''] ?? 'غير محدَّد'}
                </span>
                {reason && <span style={{ fontSize: '.76rem', color: 'var(--ih-danger-ink)' }}>{reason}</span>}
                {canReview && (
                  <span style={{ marginInlineStart: 'auto', display: 'flex', gap: '.35rem' }}>
                    <button disabled={busy} onClick={() => act(path, { decision: 'verified' })} className="btn btn-xs btn-outline">توثيق</button>
                    <button disabled={busy} onClick={() => act(path, { decision: 'rejected' })} className="btn btn-xs btn-danger">رفض</button>
                  </span>
                )}
              </div>
            );
          })}
        </div>
      </Sec>

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.1rem', alignItems: 'start' }}>
        <Sec title="المستندات" icon="file-text">
          <div className="ih-sec__body">
            {documents.length === 0 ? (
              <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا مستندات مرفوعة.</div>
            ) : (
              <div style={{ display: 'grid', gap: '.5rem' }}>
                {documents.map((d) => (
                  <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
                    <Icon name="file-text" size={16} />
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ fontSize: '.84rem', fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{d.title ?? '—'}</div>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{d.kind ?? '—'} · <bdi>{d.sizeKb}</bdi> ك.ب{d.uploadedAt ? ` · ${d.uploadedAt}` : ''}</div>
                    </div>
                    {/* التنزيل يمرّ عبر الخادم ويُسجَّل — لا رابط عام للملف */}
                    <a href={`${base}/documents/${d.id}/download?preview=1`} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline">معاينة</a>
                    <a href={`${base}/documents/${d.id}/download`} className="btn btn-xs btn-outline">تنزيل</a>
                  </div>
                ))}
              </div>
            )}
          </div>
        </Sec>

        <Sec title="ملاحظات داخلية" icon="clipboard-check">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
            <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>لا تصل مقدّم الطلب.</div>
            {notes.length > 0 && (
              <div style={{ display: 'grid', gap: '.5rem' }}>
                {notes.map((n, i) => (
                  <div key={i} className="card" style={{ padding: '.6rem .8rem' }}>
                    <div style={{ fontSize: '.84rem', whiteSpace: 'pre-wrap' }}>{n.body}</div>
                    <div style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)', marginTop: '.25rem' }}>{n.by}{n.at ? ` · ${n.at}` : ''}</div>
                  </div>
                ))}
              </div>
            )}
            {canReview && (
              <div style={{ display: 'grid', gap: '.4rem' }}>
                <textarea value={internalNote} onChange={(e) => setInternalNote(e.target.value)} className="field" rows={2} placeholder="ملاحظة داخلية…" />
                <div>
                  <button disabled={busy || internalNote.trim().length < 2}
                    onClick={() => { act('note', { notes: internalNote }); setInternalNote(''); }}
                    className="btn btn-sm btn-outline">إضافة ملاحظة</button>
                </div>
              </div>
            )}
          </div>
        </Sec>
      </div>

      <Sec title="المراسلات مع مقدّم الطلب" icon="inbox">
        <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
          {messages.length === 0 ? (
            <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا رسائل بعد.</div>
          ) : (
            <div style={{ display: 'grid', gap: '.5rem' }}>
              {messages.map((m, i) => (
                <div key={i} className="card" style={{
                  padding: '.6rem .8rem',
                  borderInlineStart: `3px solid ${m.fromAgency ? 'var(--ih-primary)' : 'var(--ih-border)'}`,
                }}>
                  <div style={{ fontSize: '.84rem', whiteSpace: 'pre-wrap' }}>{m.body}</div>
                  <div style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)', marginTop: '.25rem' }}>
                    {m.fromAgency ? 'الوكالة' : 'مقدّم الطلب'}{m.at ? ` · ${m.at}` : ''}
                  </div>
                </div>
              ))}
            </div>
          )}
          {canReview && (
            <div style={{ display: 'grid', gap: '.4rem' }}>
              <textarea value={msgBody} onChange={(e) => setMsgBody(e.target.value)} className="field" rows={2} placeholder="اكتب رسالة لمقدّم الطلب…" />
              <div>
                <button disabled={busy || msgBody.trim().length < 2}
                  onClick={() => { act('message', { body: msgBody }); setMsgBody(''); }}
                  className="btn btn-sm btn-primary">إرسال</button>
              </div>
            </div>
          )}
        </div>
      </Sec>

    </AppShell>
  );
}
