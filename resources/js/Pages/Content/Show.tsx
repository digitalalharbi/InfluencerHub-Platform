import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, SummaryStrip, WorkspaceHeader , WaitingNotice } from '@/Components/ui';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Content {
  id: number; number: string; title: string; type: string; platform: string | null;
  caption: string | null; mediaUrl: string | null; version: number;
  creator: string | null; creatorId: number | null; client: string | null; clientId: number | null;
  campaign: string | null; campaignId: number | null;
  status: string; statusLabel: string; statusTone: string; scheduledAt: string | null; publishedAt: string | null;
  publishedUrl: string | null; proofNote: string | null; proofAt: string | null;
  results: { reach: number | null; impressions: number | null; engagements: number | null; clicks: number | null; source: string; at: string } | null;
}
type Action = [string, string, string, 'none' | 'reason' | 'schedule'];
interface Approval { stage: string; decision: string; note: string | null; version: number; at: string | null }
interface TimelineEntry { kind: 'status' | 'decision'; label: string; actor: string | null; role: string; note: string | null; version: number | null; at: string | null; decision?: string; toStatus?: string | null }
interface WaitingInfo { party: string; expects: string; canRemind: boolean }
interface Props { content: Content; canReview: boolean; actions: Action[]; approvals: Approval[]; timeline: TimelineEntry[]; waitingOn: WaitingInfo | null; }

const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };

export default function ContentShow({ content, canReview, actions, approvals, timeline, waitingOn}: Props) {
  const t = useT();
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
    setModalFor(a); setReason('');
    // إعادة الجدولة تبدأ من الموعد الحالي بدل حقل فارغ
    setScheduledAt(a[0] === 'reschedule' && content.scheduledAt ? content.scheduledAt.replace(' ', 'T') : '');
  };
  const submitModal = () => {
    if (!modalFor) return;
    const payload = modalFor[3] === 'schedule' ? { scheduled_at: scheduledAt } : { reason };
    router.post(u(`/content/${content.id}/${modalFor[0]}`), payload, { preserveScroll: true, onSuccess: () => setModalFor(null) });
  };
  const modalValid = modalFor?.[3] === 'schedule' ? !!scheduledAt : !!reason.trim();

  return (
    <AppShell heading={t('content.show_heading')}>
      <Head title={content.title} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={t('content.show_eyebrow', { num: content.number, ver: content.version })}
        title={content.title}
        statusTone={content.statusTone} statusLabel={content.statusLabel}
        back={u("/content")} backLabel={t('content.back_all')}
        meta={[
          [t('content.m_creator'), content.creator ?? '—'], [t('content.m_client'), content.client ?? '—'],
          [t('content.m_type'), content.type], [t('content.m_platform'), content.platform ?? '—'],
          ...(content.campaign ? [[t('content.m_campaign'), content.campaign] as [string, string]] : []),
        ]}
        actions={canReview && actions.length > 0 ? <>{actions.map((a) => (
          <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
        ))}</> : undefined}
      />

      {/* الانتظار يُعلَن: قائمة إجراءات فارغة بلا سبب تبدو عطلًا */}
      <WaitingNotice waiting={waitingOn} />

      <SummaryStrip items={[
        { label: t('content.ss_version'), value: `v${content.version}`, icon: 'file-text' },
        { label: t('content.m_type'), value: content.type, icon: 'image' },
        { label: t('content.ss_review_decisions'), value: approvals.length, icon: 'clipboard-check' },
        { label: t('content.ss_scheduled'), value: content.scheduledAt ?? '—' },
        { label: t('content.ss_published'), value: content.publishedAt ?? '—' },
      ]} />

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title={t('content.sec_content')} icon="image">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.9rem' }}>
              {content.mediaUrl && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{t('content.media_url')}</div><a href={content.mediaUrl} target="_blank" rel="noopener" style={{ color: 'var(--ih-primary)', direction: 'ltr', display: 'inline-block' }}>{content.mediaUrl}</a></div>}
              {content.caption && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{t('content.caption')}</div><p style={{ margin: '.3rem 0 0', lineHeight: 1.7, whiteSpace: 'pre-wrap' }}>{content.caption}</p></div>}
              {!content.mediaUrl && !content.caption && <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>{t('content.no_media')}</div>}
            </div>
          </Sec>

          {/* السياق والروابط — تنقّل فعلي عبر الوحدات (حملة/مبدع/عميل/منشور حيّ) */}
          <Sec title={t('content.sec_context')} icon="external-link">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.55rem', fontSize: '.85rem' }}>
              <CtxRow label={t('content.m_campaign')} value={content.campaign} href={content.campaignId ? u(`/campaigns/${content.campaignId}`) : null} hint={t('content.ctx_campaign_hint')} />
              <CtxRow label={t('content.m_creator')} value={content.creator} href={content.creatorId ? u(`/creators/${content.creatorId}`) : null} />
              <CtxRow label={t('content.m_client')} value={content.client} href={content.clientId ? u(`/clients/${content.clientId}`) : null} />
              <CtxRow label={t('content.m_platform')} value={content.platform} />
              {content.publishedUrl && (
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem' }}>
                  <span style={{ color: 'var(--ih-text-muted)' }}>{t('content.live_post')}</span>
                  <a href={content.publishedUrl} target="_blank" rel="noopener" style={{ color: 'var(--ih-primary)', direction: 'ltr', fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '60%' }}>{content.publishedUrl}</a>
                </div>
              )}
            </div>
          </Sec>
        </div>

        {/* إثبات النشر ونتائجه — يظهر بعد النشر فقط، فقبله لا معنى لإثبات */}
        {content.status === 'published' && (
          <Sec title={t('content.sec_proof')} icon="clipboard-check">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '1rem' }}>
              {content.publishedUrl ? (
                <div style={{ display: 'grid', gap: '.35rem' }}>
                  <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{t('content.live_post_url')}</div>
                  <a href={content.publishedUrl} target="_blank" rel="noopener"
                     style={{ color: 'var(--ih-primary)', direction: 'ltr', fontWeight: 600 }}>{content.publishedUrl}</a>
                  <div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>
                    {[content.proofAt ? t('content.proved_at', { date: content.proofAt }) : '', content.proofNote].filter(Boolean).join(' · ')}
                  </div>
                </div>
              ) : (
                <div style={{ display: 'grid', gap: '.6rem' }}>
                  <div style={{ fontSize: '.85rem', color: 'var(--ih-text-muted)', lineHeight: 1.7 }}>
                    {t('content.proof_missing_hint')}
                  </div>
                  {canReview && <button className="btn btn-sm btn-primary" onClick={() => setProofOpen(true)}>{t('content.record_proof')}</button>}
                </div>
              )}

              {content.results ? (
                <div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1.4rem' }}>
                    {([[t('content.r_reach'), content.results.reach], [t('content.r_impressions'), content.results.impressions],
                       [t('content.r_engagements'), content.results.engagements], [t('content.r_clicks'), content.results.clicks]] as const)
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
                <button className="btn btn-sm btn-outline" onClick={() => setResultsOpen(true)}>{t('content.record_results')}</button>
              )}
            </div>
          </Sec>
        )}

        {/* سجل موحّد: كل قرار مراجعة + كل انتقال حالة، بفاعله ودوره ووقته وإصداره */}
        <Sec title={timeline.length ? t('content.sec_timeline_n', { n: timeline.length }) : t('content.sec_timeline')} icon="clipboard-check">
          <div className="ih-sec__body">
            {timeline.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>{t('content.no_timeline')}</div> :
              <div className="ih-tl">
                {timeline.map((e, i) => (
                  <div key={i} className="ih-tl__item">
                    <span className="ih-tl__dot" style={e.kind === 'decision' ? { background: e.decision === 'approved' ? 'var(--ih-success)' : e.decision === 'rejected' ? 'var(--ih-danger)' : 'var(--ih-warning)' } : undefined} />
                    <div className="ih-tl__text">
                      {e.label}{e.version ? <span style={{ color: 'var(--ih-text-muted)', fontWeight: 400 }}> · v{e.version}</span> : null}
                    </div>
                    <div className="ih-tl__meta">{[e.at, [e.actor, e.role].filter(Boolean).join(' · '), e.note].filter(Boolean).join(' · ')}</div>
                  </div>
                ))}
              </div>}
          </div>
        </Sec>
      </div>

      {proofOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setProofOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 .3rem' }}>{t('content.proof_modal_title')}</h3>
            <p style={{ margin: '0 0 1rem', fontSize: '.82rem', color: 'var(--ih-text-muted)', lineHeight: 1.7 }}>
              {t('content.proof_modal_hint')}
            </p>
            <label style={{ display: 'block', fontSize: '.8rem', fontWeight: 600, marginBottom: '.3rem' }} htmlFor="proof-url">{t('content.f_url')}</label>
            <input id="proof-url" className="field" style={{ width: '100%', direction: 'ltr' }} value={proofUrl}
                   onChange={(e) => setProofUrl(e.target.value)} placeholder="https://…" autoFocus />
            <label style={{ display: 'block', fontSize: '.8rem', fontWeight: 600, margin: '.8rem 0 .3rem' }} htmlFor="proof-note">{t('content.f_note_optional')}</label>
            <input id="proof-note" className="field" style={{ width: '100%' }} value={proofNote} onChange={(e) => setProofNote(e.target.value)} />
            <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.1rem' }}>
              <button className="btn btn-primary" disabled={!proofUrl.trim()} onClick={submitProof}>{t('content.save_proof')}</button>
              <button className="btn btn-ghost" onClick={() => setProofOpen(false)}>{t('content.cancel')}</button>
            </div>
          </div>
        </div>
      )}

      {resultsOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setResultsOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 .3rem' }}>{t('content.results_modal_title')}</h3>
            <p style={{ margin: '0 0 1rem', fontSize: '.82rem', color: 'var(--ih-text-muted)', lineHeight: 1.7 }}>
              {t('content.results_modal_hint')}
            </p>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(130px,1fr))', gap: '.7rem' }}>
              {([['reach', t('content.r_reach')], ['impressions', t('content.r_impressions')], ['engagements', t('content.r_engagements')], ['clicks', t('content.r_clicks')]] as const).map(([k, label]) => (
                <div key={k}>
                  <label style={{ display: 'block', fontSize: '.78rem', fontWeight: 600, marginBottom: '.25rem' }} htmlFor={`m-${k}`}>{label}</label>
                  <input id={`m-${k}`} className="field" style={{ width: '100%', direction: 'ltr' }} inputMode="numeric"
                         value={metrics[k]} onChange={(e) => setMetrics({ ...metrics, [k]: e.target.value.replace(/\D/g, '') })} />
                </div>
              ))}
            </div>
            <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.1rem' }}>
              <button className="btn btn-primary" disabled={!anyMetric} onClick={submitResults}>{t('content.save_results')}</button>
              <button className="btn btn-ghost" onClick={() => setResultsOpen(false)}>{t('content.cancel')}</button>
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
              <textarea className="field" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t('content.reason_placeholder')} autoFocus />
            )}
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button className={`btn ${BTN[modalFor[2]] ?? 'btn-primary'}`} onClick={submitModal} disabled={!modalValid}>{t('content.confirm')}</button>
              <button className="btn btn-ghost" onClick={() => setModalFor(null)}>{t('content.cancel')}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

function CtxRow({ label, value, href, hint }: { label: string; value: string | null; href?: string | null; hint?: string }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem', alignItems: 'baseline' }}>
      <span style={{ color: 'var(--ih-text-muted)' }}>{label}</span>
      {value ? (
        href ? (
          <Link href={href} style={{ color: 'var(--ih-primary)', fontWeight: 600, textAlign: 'end' }}>
            {value}{hint ? <span style={{ color: 'var(--ih-text-muted)', fontWeight: 400, fontSize: '.75rem' }}> · {hint}</span> : null}
          </Link>
        ) : (
          <span style={{ fontWeight: 600, textAlign: 'end' }}>{value}</span>
        )
      ) : (
        <span style={{ color: 'var(--ih-text-muted)' }}>—</span>
      )}
    </div>
  );
}
