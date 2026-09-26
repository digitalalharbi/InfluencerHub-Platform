import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, SummaryStrip, WorkspaceHeader , WaitingNotice } from '@/Components/ui';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Collab {
  id: number; number: string; title: string; brief: string | null; creator: string | null; creatorId: number | null;
  campaign: string | null; campaignId: number | null; feeMinor: number; currency: string; dueDate: string | null;
  submissionNote: string | null; declineReason: string | null;
  status: string; statusLabel: string; statusTone: string;
}
type Action = [string, string, string, boolean];
interface History { from: string; to: string; by: string; reason: string | null; at: string | null }
interface WaitingInfo { party: string; expects: string; canRemind: boolean }
interface Props { collaboration: Collab; canManage: boolean; actions: Action[]; history: History[]; waitingOn: WaitingInfo | null; }

const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };
const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;

export default function CollaborationShow({ collaboration, canManage, actions, history, waitingOn}: Props) {
  const t = useT();
  const { props } = usePage<SharedProps>();
  const [reasonFor, setReasonFor] = useState<Action | null>(null);
  const [reason, setReason] = useState('');

  const runAction = (a: Action) => {
    if (a[3]) { setReasonFor(a); setReason(''); return; }
    router.post(u(`/collaborations/${collaboration.id}/${a[0]}`), {}, { preserveScroll: true });
  };
  const submitReason = () => {
    if (!reasonFor) return;
    router.post(u(`/collaborations/${collaboration.id}/${reasonFor[0]}`), { reason }, { preserveScroll: true, onSuccess: () => setReasonFor(null) });
  };

  return (
    <AppShell heading={t('collaborations.show_heading')}>
      <Head title={collaboration.title} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={t('collaborations.show_eyebrow', { num: collaboration.number })}
        title={collaboration.title}
        statusTone={collaboration.statusTone} statusLabel={collaboration.statusLabel}
        back={u("/collaborations")} backLabel={t('collaborations.back_all')}
        meta={[
          [t('collaborations.m_creator'), collaboration.creator ?? '—'], [t('collaborations.m_campaign'), collaboration.campaign ?? '—'],
          [t('collaborations.m_fee'), money(collaboration.feeMinor, collaboration.currency)], [t('collaborations.m_due'), collaboration.dueDate ?? '—'],
        ]}
        actions={canManage && actions.length > 0 ? <>{actions.map((a) => (
          <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
        ))}</> : undefined}
      />

      {/* الانتظار يُعلَن: قائمة إجراءات فارغة بلا سبب تبدو عطلًا */}
      <WaitingNotice waiting={waitingOn} />

      {collaboration.declineReason && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-danger)', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.85rem' }}>
          <b>{t('collaborations.decline_label')}</b> {collaboration.declineReason}
        </div>
      )}

      <SummaryStrip items={[
        { label: t('collaborations.m_fee'), value: money(collaboration.feeMinor, collaboration.currency), tone: 'primary', icon: 'wallet' },
        { label: t('collaborations.ss_status'), value: collaboration.statusLabel },
        { label: t('collaborations.m_due'), value: collaboration.dueDate ?? '—' },
        { label: t('collaborations.m_campaign'), value: collaboration.campaign ?? '—' },
      ]} />

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title={t('collaborations.sec_brief')} icon="git-merge">
            <div className="ih-sec__body">
              <p style={{ margin: 0, lineHeight: 1.8, whiteSpace: 'pre-wrap', color: collaboration.brief ? 'var(--ih-text)' : 'var(--ih-text-muted)' }}>{collaboration.brief ?? t('collaborations.no_brief')}</p>
            </div>
          </Sec>
          {collaboration.submissionNote && (
            <Sec title={t('collaborations.sec_submission_note')} icon="clipboard-check">
              <div className="ih-sec__body"><p style={{ margin: 0, lineHeight: 1.7, whiteSpace: 'pre-wrap' }}>{collaboration.submissionNote}</p></div>
            </Sec>
          )}
        </div>
        <Sec title={t('collaborations.sec_history')} icon="bar-chart-3">
          <div className="ih-sec__body">
            {history.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>{t('collaborations.no_history')}</div> :
              <div className="ih-tl">
                {history.map((h, i) => (
                  <div key={i} className="ih-tl__item"><span className="ih-tl__dot" />
                    <div className="ih-tl__text">{h.from} → {h.to}</div>
                    <div className="ih-tl__meta">{[h.by, h.at, h.reason].filter(Boolean).join(' · ')}</div>
                  </div>
                ))}
              </div>}
          </div>
        </Sec>
      </div>

      {reasonFor && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setReasonFor(null)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{reasonFor[1]}</h3>
            <textarea className="field" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t('collaborations.reason_placeholder')} autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button className={`btn ${BTN[reasonFor[2]] ?? 'btn-primary'}`} onClick={submitReason} disabled={!reason.trim()}>{t('collaborations.confirm')}</button>
              <button className="btn btn-ghost" onClick={() => setReasonFor(null)}>{t('collaborations.cancel')}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
