import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, SummaryStrip, WorkspaceHeader } from '@/Components/ui';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Req {
  id: number; number: string; title: string; description: string | null; client: string | null; brand: string | null;
  type: string; priority: string; priorityLabel: string; status: string; statusLabel: string; statusTone: string;
  assignee: string | null; assignedTo: number | null; dueAt: string | null; sla: 'none' | 'overdue' | 'soon' | 'ok';
  slaHours: number | null; createdAt: string | null; resolvedAt: string | null;
}
type Action = [string, string, string, boolean];
interface Comment { id: number; author: string; authorType: string; body: string; internal: boolean; at: string | null }
interface History { from: string; to: string; by: string; reason: string | null; at: string | null }
interface Props {
  request: Req; canHandle: boolean; canConvert: boolean; actions: Action[]; agents: { id: number; name: string }[];
  brief: {
    budgetMinor: number | null; currency: string; startDate: string | null; endDate: string | null;
    platforms: string[]; scopeNotes: string | null; brand: string | null; hasAny: boolean;
  };
  convertedCampaign: { id: number; name: string; number: string } | null;
  comments: Comment[]; history: History[];
}

const PRIO_TONE: Record<string, { bg: string; fg: string }> = {
  urgent: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  high: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
  normal: { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-secondary)' },
  low: { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-muted)' },
};
const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };
type TFn = ReturnType<typeof useT>;
const slaText = (r: Req, t: TFn) => r.sla === 'overdue' ? t('service_requests.sla_overdue', { n: Math.abs(r.slaHours ?? 0) }) : r.sla === 'soon' ? t('service_requests.sla_soon', { n: r.slaHours ?? 0 }) : r.sla === 'ok' ? t('service_requests.sla_ok', { n: r.slaHours ?? 0 }) : '—';
const slaTone = (r: Req) => r.sla === 'overdue' ? 'danger' : r.sla === 'soon' ? 'warning' : 'success';

export default function ServiceRequestShow({ request, canHandle, canConvert, actions, agents, comments, history, brief, convertedCampaign }: Props) {
  const t = useT();
  const { props } = usePage<SharedProps>();
  const [reasonFor, setReasonFor] = useState<Action | null>(null);
  const [reason, setReason] = useState('');
  const [assignTo, setAssignTo] = useState(request.assignedTo ? String(request.assignedTo) : '');
  const [body, setBody] = useState('');
  const pt = PRIO_TONE[request.priority] ?? PRIO_TONE.normal;

  const runAction = (a: Action) => {
    if (a[3]) { setReasonFor(a); setReason(''); return; }
    router.post(u(`/service-requests/${request.id}/${a[0]}`), {}, { preserveScroll: true });
  };
  const submitReason = () => {
    if (!reasonFor) return;
    router.post(u(`/service-requests/${request.id}/${reasonFor[0]}`), { reason }, { preserveScroll: true, onSuccess: () => setReasonFor(null) });
  };
  const assign = () => { if (assignTo) router.post(u(`/service-requests/${request.id}/assign`), { assigned_to: assignTo }, { preserveScroll: true }); };
  const addComment = () => { if (body.trim()) router.post(u(`/service-requests/${request.id}/comment`), { body }, { preserveScroll: true, onSuccess: () => setBody('') }); };
  // التحويل إلى حملة ينقل المستخدم إلى الحملة الجديدة — لا preserveScroll
  const convert = () => router.post(u(`/service-requests/${request.id}/convert-campaign`));

  return (
    <AppShell heading={t('service_requests.show_heading')}>
      <Head title={request.title} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={t('service_requests.show_eyebrow', { num: request.number })}
        title={request.title}
        statusTone={request.statusTone} statusLabel={request.statusLabel}
        back={u("/service-requests")} backLabel={t('service_requests.back_all')}
        meta={[
          [t('service_requests.m_client'), request.client ?? '—'], [t('service_requests.m_type'), request.type],
          [t('service_requests.m_assignee'), request.assignee ?? t('service_requests.unassigned')], [t('service_requests.m_due'), request.dueAt ?? '—'],
        ]}
        actions={(canHandle && actions.length > 0) || canConvert ? <>
          {canHandle && actions.map((a) => (
            <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
          ))}
          {convertedCampaign
            ? <a href={u(`/campaigns/${convertedCampaign.id}`)} className="btn btn-sm btn-outline">{t('service_requests.open_campaign', { num: convertedCampaign.number })}</a>
            : canConvert && <button onClick={convert} className="btn btn-sm btn-primary">{t('service_requests.convert_to_campaign')}</button>}
        </> : undefined}
      />

      {brief.hasAny && (
        <Sec title={t('service_requests.brief_title')} icon="clipboard-check">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))', gap: '.7rem' }}>
              {brief.brand && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{t('service_requests.brief_brand')}</div><div style={{ fontWeight: 600, fontSize: '.88rem' }}>{brief.brand}</div></div>}
              {brief.budgetMinor !== null && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{t('service_requests.brief_budget')}</div><div style={{ fontWeight: 600, fontSize: '.88rem', direction: 'ltr', textAlign: 'start' }}>{(brief.budgetMinor / 100).toLocaleString('en-US')} {brief.currency}</div></div>}
              {brief.startDate && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{t('service_requests.brief_start')}</div><div style={{ fontWeight: 600, fontSize: '.88rem', direction: 'ltr', textAlign: 'start' }}>{brief.startDate}</div></div>}
              {brief.endDate && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{t('service_requests.brief_end')}</div><div style={{ fontWeight: 600, fontSize: '.88rem', direction: 'ltr', textAlign: 'start' }}>{brief.endDate}</div></div>}
            </div>
            {brief.platforms.length > 0 && (
              <div>
                <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginBottom: '.25rem' }}>{t('service_requests.brief_platforms')}</div>
                <div style={{ display: 'flex', gap: '.35rem', flexWrap: 'wrap' }}>
                  {brief.platforms.map((p) => <span key={p} className="ih-tag" style={{ fontSize: '.7rem' }}>{p}</span>)}
                </div>
              </div>
            )}
            {brief.scopeNotes && (
              <div>
                <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginBottom: '.15rem' }}>{t('service_requests.brief_scope')}</div>
                <div style={{ fontSize: '.85rem', whiteSpace: 'pre-wrap' }}>{brief.scopeNotes}</div>
              </div>
            )}
            <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>
              {convertedCampaign ? t('service_requests.brief_moved') : t('service_requests.brief_will_move')}
            </div>
          </div>
        </Sec>
      )}

      <SummaryStrip items={[
        { label: t('service_requests.ss_priority'), value: <span className="badge" style={{ background: pt.bg, color: pt.fg }}>{request.priorityLabel}</span> },
        { label: 'SLA', value: slaText(request, t), tone: slaTone(request) as 'danger' | 'warning' | 'success' },
        { label: t('service_requests.m_type'), value: request.type },
        { label: t('service_requests.ss_created'), value: request.createdAt ?? '—' },
        { label: t('service_requests.ss_resolved'), value: request.resolvedAt ?? '—' },
      ]} />

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title={t('service_requests.sec_details')} icon="inbox">
            <div className="ih-sec__body">
              <p style={{ margin: 0, lineHeight: 1.8, color: request.description ? 'var(--ih-text)' : 'var(--ih-text-muted)', whiteSpace: 'pre-wrap' }}>{request.description ?? t('service_requests.no_description')}</p>
            </div>
          </Sec>

          <Sec title={comments.length ? t('service_requests.sec_comments_n', { n: comments.length }) : t('service_requests.sec_comments')} icon="clipboard-check">
            <div className="ih-sec__body">
              {canHandle && (
                <div style={{ display: 'flex', gap: '.5rem', marginBottom: '1rem' }}>
                  <input className="field" value={body} onChange={(e) => setBody(e.target.value)} placeholder={t('service_requests.comment_placeholder')} onKeyDown={(e) => e.key === 'Enter' && addComment()} />
                  <button className="btn btn-sm btn-primary" onClick={addComment} disabled={!body.trim()}>{t('service_requests.add')}</button>
                </div>
              )}
              {comments.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>{t('service_requests.no_comments')}</div> :
                <div style={{ display: 'grid', gap: '.7rem' }}>
                  {comments.map((c) => (
                    <div key={c.id} style={{ padding: '.7rem .9rem', background: 'var(--ih-surface-muted)', borderRadius: 'var(--ih-radius-sm)' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.76rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>
                        <span style={{ fontWeight: 700, color: 'var(--ih-text-secondary)' }}>{c.author}{c.internal && ` · ${t('service_requests.internal_tag')}`}</span><span>{c.at}</span>
                      </div>
                      <div style={{ fontSize: '.88rem', whiteSpace: 'pre-wrap' }}>{c.body}</div>
                    </div>
                  ))}
                </div>}
            </div>
          </Sec>
        </div>

        <div style={{ display: 'grid', gap: '1.1rem' }}>
          {canHandle && (
            <Sec title={t('service_requests.sec_assign')} icon="user-plus">
              <div className="ih-sec__body" style={{ display: 'flex', gap: '.5rem' }}>
                <select className="field" value={assignTo} onChange={(e) => setAssignTo(e.target.value)}>
                  <option value="">{t('service_requests.choose_member')}</option>
                  {agents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
                <button className="btn btn-sm btn-primary" onClick={assign} disabled={!assignTo}>{t('service_requests.assign_btn')}</button>
              </div>
            </Sec>
          )}

          <Sec title={t('service_requests.sec_history')} icon="bar-chart-3">
            <div className="ih-sec__body">
              {history.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>{t('service_requests.no_history')}</div> :
                <div className="ih-tl">
                  {history.map((h, i) => (
                    <div key={i} className="ih-tl__item">
                      <span className="ih-tl__dot" />
                      <div className="ih-tl__text">{h.from} → {h.to}</div>
                      <div className="ih-tl__meta">{[h.by, h.at, h.reason].filter(Boolean).join(' · ')}</div>
                    </div>
                  ))}
                </div>}
            </div>
          </Sec>
        </div>
      </div>

      {reasonFor && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setReasonFor(null)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{reasonFor[1]}</h3>
            <textarea className="field" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t('service_requests.reason_placeholder')} autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button className={`btn ${BTN[reasonFor[2]] ?? 'btn-primary'}`} onClick={submitReason} disabled={!reason.trim()}>{t('service_requests.confirm')}</button>
              <button className="btn btn-ghost" onClick={() => setReasonFor(null)}>{t('service_requests.cancel')}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
