import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, SummaryStrip, WorkspaceHeader , WaitingNotice } from '@/Components/ui';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';

interface Content {
  id: number; number: string; title: string; type: string; platform: string | null;
  caption: string | null; mediaUrl: string | null; version: number;
  creator: string | null; client: string | null; campaign: string | null; campaignId: number | null;
  status: string; statusLabel: string; statusTone: string; scheduledAt: string | null; publishedAt: string | null;
  publishedUrl: string | null; proofNote: string | null; proofAt: string | null;
  results: { reach: number | null; impressions: number | null; engagements: number | null; clicks: number | null; source: string; at: string } | null;
}
type Action = [string, string, string, 'none' | 'reason' | 'schedule'];
interface Approval { stage: string; decision: string; note: string | null; version: number; at: string | null }
interface WaitingInfo { party: string; expects: string; canRemind: boolean }
interface Props { content: Content; canReview: boolean; actions: Action[]; approvals: Approval[]; waitingOn: WaitingInfo | null; }

const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };

export default function ContentShow({ content, canReview, actions, approvals, waitingOn}: Props) {
  const { props } = usePage<SharedProps>();
  const [modalFor, setModalFor] = useState<Action | null>(null);
  const [reason, setReason] = useState('');
  const [scheduledAt, setScheduledAt] = useState('');
  const [proofOpen, setProofOpen] = useState(false);
  const [proofUrl, setProofUrl] = useState('');
  const [proofNote, setProofNote] = useState('');
  const [resultsOpen, setResultsOpen] = useState(false);
  const [metrics, setMetrics] = useState({ reach: '', impressions: '', engagements: '', clicks: '' });

  const submitProof = () => {
    router.post(u(`/content/${content.id}/record-proof`), { published_url: proofUrl, proof_note: proofNote || null },
      { preserveScroll: true, onSuccess: () => { setProofOpen(false); setProofUrl(''); setProofNote(''); } });
  };
  const submitResults = () => {
    // الفارغ يُرسَل null لا صفرًا: ما لم يُقَس لا يُدَّعى أنه صفر
    const payload = Object.fromEntries(
      Object.entries(metrics).map(([k, v]) => [k, v === '' ? null : Number(v)]),
    );
    router.post(u(`/content/${content.id}/record-results`), payload,
      { preserveScroll: true, onSuccess: () => setResultsOpen(false) });
  };
  const anyMetric = Object.values(metrics).some((v) => v !== '');

  const runAction = (a: Action) => {
    if (a[3] === 'none') { router.post(u(`/content/${content.id}/${a[0]}`), {}, { preserveScroll: true }); return; }
    setModalFor(a); setReason(''); setScheduledAt('');
  };
  const submitModal = () => {
    if (!modalFor) return;
    const payload = modalFor[3] === 'schedule' ? { scheduled_at: scheduledAt } : { reason };
    router.post(u(`/content/${content.id}/${modalFor[0]}`), payload, { preserveScroll: true, onSuccess: () => setModalFor(null) });
  };
  const modalValid = modalFor?.[3] === 'schedule' ? !!scheduledAt : !!reason.trim();

  return (
    <AppShell heading="مراجعة محتوى">
      <Head title={content.title} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={`مراجعة محتوى · ${content.number} · v${content.version}`}
        title={content.title}
        statusTone={content.statusTone} statusLabel={content.statusLabel}
        back={u("/content")} backLabel="كل المحتوى"
        meta={[
          ['المبدع', content.creator ?? '—'], ['العميل', content.client ?? '—'],
          ['النوع', content.type], ['المنصّة', content.platform ?? '—'],
          ...(content.campaign ? [['الحملة', content.campaign] as [string, string]] : []),
        ]}
        actions={canReview && actions.length > 0 ? <>{actions.map((a) => (
          <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
        ))}</> : undefined}
      />

      {/* الانتظار يُعلَن: قائمة إجراءات فارغة بلا سبب تبدو عطلًا */}
      <WaitingNotice waiting={waitingOn} />

      <SummaryStrip items={[
        { label: 'الإصدار', value: `v${content.version}`, icon: 'file-text' },
        { label: 'النوع', value: content.type, icon: 'image' },
        { label: 'قرارات المراجعة', value: approvals.length, icon: 'clipboard-check' },
        { label: 'مجدول', value: content.scheduledAt ?? '—' },
        { label: 'نُشر', value: content.publishedAt ?? '—' },
      ]} />

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="المحتوى" icon="image">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.9rem' }}>
              {content.mediaUrl && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>رابط المحتوى</div><a href={content.mediaUrl} target="_blank" rel="noopener" style={{ color: 'var(--ih-primary)', direction: 'ltr', display: 'inline-block' }}>{content.mediaUrl}</a></div>}
              {content.caption && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>النص</div><p style={{ margin: '.3rem 0 0', lineHeight: 1.7, whiteSpace: 'pre-wrap' }}>{content.caption}</p></div>}
              {!content.mediaUrl && !content.caption && <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا رابط ولا نص بعد.</div>}
            </div>
          </Sec>
        </div>

        {/* إثبات النشر ونتائجه — يظهر بعد النشر فقط، فقبله لا معنى لإثبات */}
        {content.status === 'published' && (
          <Sec title="إثبات النشر والنتائج" icon="clipboard-check">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '1rem' }}>
              {content.publishedUrl ? (
                <div style={{ display: 'grid', gap: '.35rem' }}>
                  <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>رابط المنشور الحيّ</div>
                  <a href={content.publishedUrl} target="_blank" rel="noopener"
                     style={{ color: 'var(--ih-primary)', direction: 'ltr', fontWeight: 600 }}>{content.publishedUrl}</a>
                  <div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>
                    {['أُثبت ' + (content.proofAt ?? ''), content.proofNote].filter(Boolean).join(' · ')}
                  </div>
                </div>
              ) : (
                <div style={{ display: 'grid', gap: '.6rem' }}>
                  <div style={{ fontSize: '.85rem', color: 'var(--ih-text-muted)', lineHeight: 1.7 }}>
                    نُشر المحتوى ولم يُسجَّل رابط المنشور بعد. الإثبات هو ما تُبنى عليه الفاتورة والتقرير.
                  </div>
                  {canReview && <button className="btn btn-sm btn-primary" onClick={() => setProofOpen(true)}>سجّل إثبات النشر</button>}
                </div>
              )}

              {content.results ? (
                <div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1.4rem' }}>
                    {([['الوصول', content.results.reach], ['الظهور', content.results.impressions],
                       ['التفاعل', content.results.engagements], ['النقرات', content.results.clicks]] as const)
                      .filter(([, v]) => v !== null)
                      .map(([label, v]) => (
                        <div key={label}>
                          <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{label}</div>
                          <bdi style={{ fontSize: '1.35rem', fontWeight: 700 }}>{Number(v).toLocaleString('en')}</bdi>
                        </div>
                      ))}
                  </div>
                  {/* المصدر معلَن دائمًا: رقم بلا مصدر ادّعاء */}
                  <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginTop: '.5rem' }}>
                    {content.results.source} · {content.results.at}
                  </div>
                </div>
              ) : content.publishedUrl && canReview && (
                <button className="btn btn-sm btn-outline" onClick={() => setResultsOpen(true)}>سجّل النتائج</button>
              )}
            </div>
          </Sec>
        )}

        <Sec title={approvals.length ? `قرارات المراجعة (${approvals.length})` : 'قرارات المراجعة'} icon="clipboard-check">
          <div className="ih-sec__body">
            {approvals.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا قرارات بعد.</div> :
              <div className="ih-tl">
                {approvals.map((a, i) => (
                  <div key={i} className="ih-tl__item"><span className="ih-tl__dot" />
                    <div className="ih-tl__text">{a.stage}: {a.decision} · v{a.version}</div>
                    <div className="ih-tl__meta">{[a.at, a.note].filter(Boolean).join(' · ')}</div>
                  </div>
                ))}
              </div>}
          </div>
        </Sec>
      </div>

      {proofOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setProofOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 .3rem' }}>إثبات النشر</h3>
            <p style={{ margin: '0 0 1rem', fontSize: '.82rem', color: 'var(--ih-text-muted)', lineHeight: 1.7 }}>
              رابط المنشور الحيّ على المنصّة — لا رابط الملف الإبداعي.
            </p>
            <label style={{ display: 'block', fontSize: '.8rem', fontWeight: 600, marginBottom: '.3rem' }} htmlFor="proof-url">الرابط</label>
            <input id="proof-url" className="field" style={{ width: '100%', direction: 'ltr' }} value={proofUrl}
                   onChange={(e) => setProofUrl(e.target.value)} placeholder="https://…" autoFocus />
            <label style={{ display: 'block', fontSize: '.8rem', fontWeight: 600, margin: '.8rem 0 .3rem' }} htmlFor="proof-note">ملاحظة (اختياري)</label>
            <input id="proof-note" className="field" style={{ width: '100%' }} value={proofNote} onChange={(e) => setProofNote(e.target.value)} />
            <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.1rem' }}>
              <button className="btn btn-primary" disabled={!proofUrl.trim()} onClick={submitProof}>حفظ الإثبات</button>
              <button className="btn btn-ghost" onClick={() => setProofOpen(false)}>إلغاء</button>
            </div>
          </div>
        </div>
      )}

      {resultsOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setResultsOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 .3rem' }}>نتائج المنشور</h3>
            <p style={{ margin: '0 0 1rem', fontSize: '.82rem', color: 'var(--ih-text-muted)', lineHeight: 1.7 }}>
              تُدخَل يدويًّا وتُوسَم بذلك — لا مزوّد منصّة مربوط. اترك ما لم تقِسه فارغًا.
            </p>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(130px,1fr))', gap: '.7rem' }}>
              {([['reach', 'الوصول'], ['impressions', 'الظهور'], ['engagements', 'التفاعل'], ['clicks', 'النقرات']] as const).map(([k, label]) => (
                <div key={k}>
                  <label style={{ display: 'block', fontSize: '.78rem', fontWeight: 600, marginBottom: '.25rem' }} htmlFor={`m-${k}`}>{label}</label>
                  <input id={`m-${k}`} className="field" style={{ width: '100%', direction: 'ltr' }} inputMode="numeric"
                         value={metrics[k]} onChange={(e) => setMetrics({ ...metrics, [k]: e.target.value.replace(/\D/g, '') })} />
                </div>
              ))}
            </div>
            <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.1rem' }}>
              <button className="btn btn-primary" disabled={!anyMetric} onClick={submitResults}>حفظ النتائج</button>
              <button className="btn btn-ghost" onClick={() => setResultsOpen(false)}>إلغاء</button>
            </div>
          </div>
        </div>
      )}

      {modalFor && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setModalFor(null)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{modalFor[1]}</h3>
            {modalFor[3] === 'schedule' ? (
              <input className="field" type="datetime-local" value={scheduledAt} onChange={(e) => setScheduledAt(e.target.value)} autoFocus />
            ) : (
              <textarea className="field" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="السبب (يظهر للمبدع)" autoFocus />
            )}
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button className={`btn ${BTN[modalFor[2]] ?? 'btn-primary'}`} onClick={submitModal} disabled={!modalValid}>تأكيد</button>
              <button className="btn btn-ghost" onClick={() => setModalFor(null)}>إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
