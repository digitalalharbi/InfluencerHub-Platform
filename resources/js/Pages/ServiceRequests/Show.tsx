import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, SummaryStrip, WorkspaceHeader } from '@/Components/ui';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';

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
const slaText = (r: Req) => r.sla === 'overdue' ? `متأخر ${Math.abs(r.slaHours ?? 0)}س` : r.sla === 'soon' ? `خلال ${r.slaHours}س` : r.sla === 'ok' ? `${r.slaHours}س متبقية` : '—';
const slaTone = (r: Req) => r.sla === 'overdue' ? 'danger' : r.sla === 'soon' ? 'warning' : 'success';

export default function ServiceRequestShow({ request, canHandle, canConvert, actions, agents, comments, history, brief, convertedCampaign }: Props) {
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
    <AppShell heading="طلب">
      <Head title={request.title} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={`طلب · ${request.number}`}
        title={request.title}
        statusTone={request.statusTone} statusLabel={request.statusLabel}
        back={u("/service-requests")} backLabel="كل الطلبات"
        meta={[
          ['العميل', request.client ?? '—'], ['النوع', request.type],
          ['المُسنَد', request.assignee ?? 'غير مسند'], ['الاستحقاق', request.dueAt ?? '—'],
        ]}
        actions={(canHandle && actions.length > 0) || canConvert ? <>
          {canHandle && actions.map((a) => (
            <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
          ))}
          {convertedCampaign
            ? <a href={u(`/campaigns/${convertedCampaign.id}`)} className="btn btn-sm btn-outline">افتح الحملة {convertedCampaign.number}</a>
            : canConvert && <button onClick={convert} className="btn btn-sm btn-primary">تحويل إلى حملة</button>}
        </> : undefined}
      />

      {brief.hasAny && (
        <Sec title="موجز الحملة — ينتقل إلى الحملة عند التحويل" icon="clipboard-check">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))', gap: '.7rem' }}>
              {brief.brand && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>العلامة</div><div style={{ fontWeight: 600, fontSize: '.88rem' }}>{brief.brand}</div></div>}
              {brief.budgetMinor !== null && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>الميزانية</div><div style={{ fontWeight: 600, fontSize: '.88rem', direction: 'ltr', textAlign: 'start' }}>{(brief.budgetMinor / 100).toLocaleString('en-US')} {brief.currency}</div></div>}
              {brief.startDate && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>البداية المفضّلة</div><div style={{ fontWeight: 600, fontSize: '.88rem', direction: 'ltr', textAlign: 'start' }}>{brief.startDate}</div></div>}
              {brief.endDate && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>النهاية المفضّلة</div><div style={{ fontWeight: 600, fontSize: '.88rem', direction: 'ltr', textAlign: 'start' }}>{brief.endDate}</div></div>}
            </div>
            {brief.platforms.length > 0 && (
              <div>
                <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginBottom: '.25rem' }}>المنصّات المطلوبة</div>
                <div style={{ display: 'flex', gap: '.35rem', flexWrap: 'wrap' }}>
                  {brief.platforms.map((p) => <span key={p} className="ih-tag" style={{ fontSize: '.7rem' }}>{p}</span>)}
                </div>
              </div>
            )}
            {brief.scopeNotes && (
              <div>
                <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginBottom: '.15rem' }}>نطاق العمل</div>
                <div style={{ fontSize: '.85rem', whiteSpace: 'pre-wrap' }}>{brief.scopeNotes}</div>
              </div>
            )}
            <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>
              {convertedCampaign ? 'نُقل هذا الموجز إلى الحملة بالفعل.' : 'سينتقل هذا كلّه تلقائيًا إلى الحملة — لا يُعاد إدخاله.'}
            </div>
          </div>
        </Sec>
      )}

      <SummaryStrip items={[
        { label: 'الأولوية', value: <span className="badge" style={{ background: pt.bg, color: pt.fg }}>{request.priorityLabel}</span> },
        { label: 'SLA', value: slaText(request), tone: slaTone(request) as 'danger' | 'warning' | 'success' },
        { label: 'النوع', value: request.type },
        { label: 'أُنشئ', value: request.createdAt ?? '—' },
        { label: 'أُنجز', value: request.resolvedAt ?? '—' },
      ]} />

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="تفاصيل الطلب" icon="inbox">
            <div className="ih-sec__body">
              <p style={{ margin: 0, lineHeight: 1.8, color: request.description ? 'var(--ih-text)' : 'var(--ih-text-muted)', whiteSpace: 'pre-wrap' }}>{request.description ?? 'لا وصف.'}</p>
            </div>
          </Sec>

          <Sec title={comments.length ? `التعليقات (${comments.length})` : 'التعليقات'} icon="clipboard-check">
            <div className="ih-sec__body">
              {canHandle && (
                <div style={{ display: 'flex', gap: '.5rem', marginBottom: '1rem' }}>
                  <input className="field" value={body} onChange={(e) => setBody(e.target.value)} placeholder="أضِف تعليقًا داخليًا…" onKeyDown={(e) => e.key === 'Enter' && addComment()} />
                  <button className="btn btn-sm btn-primary" onClick={addComment} disabled={!body.trim()}>إضافة</button>
                </div>
              )}
              {comments.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا تعليقات بعد.</div> :
                <div style={{ display: 'grid', gap: '.7rem' }}>
                  {comments.map((c) => (
                    <div key={c.id} style={{ padding: '.7rem .9rem', background: 'var(--ih-surface-muted)', borderRadius: 'var(--ih-radius-sm)' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.76rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>
                        <span style={{ fontWeight: 700, color: 'var(--ih-text-secondary)' }}>{c.author}{c.internal && ' · داخلي'}</span><span>{c.at}</span>
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
            <Sec title="الإسناد" icon="user-plus">
              <div className="ih-sec__body" style={{ display: 'flex', gap: '.5rem' }}>
                <select className="field" value={assignTo} onChange={(e) => setAssignTo(e.target.value)}>
                  <option value="">— اختر عضوًا —</option>
                  {agents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
                <button className="btn btn-sm btn-primary" onClick={assign} disabled={!assignTo}>إسناد</button>
              </div>
            </Sec>
          )}

          <Sec title="سجل الحالة" icon="bar-chart-3">
            <div className="ih-sec__body">
              {history.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا سجل بعد.</div> :
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
            <textarea className="field" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="السبب / الملاحظة" autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button className={`btn ${BTN[reasonFor[2]] ?? 'btn-primary'}`} onClick={submitReason} disabled={!reason.trim()}>تأكيد</button>
              <button className="btn btn-ghost" onClick={() => setReasonFor(null)}>إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
