import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { creatorNav } from '@/lib/nav';
import { WorkspaceHeader, SummaryStrip, Sec, StatusBadge } from '@/Components/ui';
import { u } from '@/lib/href';

interface Collab {
  id: number; number: string; title: string; campaignName: string | null; campaign: string | null; client: string | null;
  feeMinor: number; currency: string; status: string; statusLabel: string; statusTone: string;
  brief: string | null; dueDate: string | null; declineReason: string | null; submissionNote: string | null;
}
interface Action { key: string; label: string; tone: string; input: string | null }
interface History { to: string; tone: string; actor: string; note: string | null; at: string | null }
interface Props { creatorName: string; collab: Collab; history: History[]; actions: Action[] }

const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;
const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger' };

export default function CreatorCollaborationShow({ creatorName, collab, history, actions }: Props) {
  const [modal, setModal] = useState<Action | null>(null);
  const [value, setValue] = useState('');
  const [busy, setBusy] = useState(false);

  const run = (a: Action) => {
    if (a.input) { setModal(a); setValue(''); return; }
    setBusy(true);
    router.post(u(`/collaborations/${collab.id}/${a.key}`), {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  };
  const submitModal = () => {
    if (!modal) return;
    const key = modal.input === 'reason' ? 'reason' : 'note';
    setBusy(true);
    router.post(u(`/collaborations/${collab.id}/${modal.key}`), { [key]: value }, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
      onSuccess: () => setModal(null),
    });
  };

  return (
    <AppShell heading="تعاون" nav={creatorNav} portal="creator" wsName={creatorName} wsPlan="بوابة المبدع">
      <Head title={collab.campaign ?? collab.title} />

      <WorkspaceHeader
        eyebrow={`تعاون · ${collab.number}`}
        title={collab.campaign ?? collab.title}
        statusTone={collab.statusTone} statusLabel={collab.statusLabel}
        back={u("/collaborations")} backLabel="التعاونات"
        meta={[
          ['العميل', collab.client ?? '—'],
          ['الأجر', collab.feeMinor ? money(collab.feeMinor, collab.currency) : '—'],
          ...(collab.dueDate ? [['الاستحقاق', collab.dueDate] as [string, string]] : []),
        ]}
        actions={actions.length > 0 ? (
          <>
            {actions.map((a) => (
              <button key={a.key} disabled={busy} onClick={() => run(a)} className={`btn btn-sm ${BTN[a.tone] ?? 'btn-outline'}`}>{a.label}</button>
            ))}
          </>
        ) : undefined}
      />

      <SummaryStrip
        items={[
          { label: 'الحالة', value: collab.statusLabel, icon: 'activity' },
          { label: 'الأجر', value: collab.feeMinor ? money(collab.feeMinor, collab.currency) : '—', icon: 'wallet', tone: 'success' },
          { label: 'العميل', value: collab.client ?? '—', icon: 'building-2' },
          { label: 'الاستحقاق', value: collab.dueDate ?? '—', icon: 'clipboard-check' },
        ]}
      />

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="ملخص التعاون" icon="git-merge">
          <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>الوصف</div>
          <div className="card" style={{ padding: '1rem 1.1rem', whiteSpace: 'pre-wrap', fontSize: '.9rem', lineHeight: 1.8, minHeight: 80 }}>
            {collab.brief || <span style={{ color: 'var(--ih-text-muted)' }}>لا وصف.</span>}
          </div>
          {collab.declineReason && (
            <div className="card" style={{ marginTop: '.8rem', padding: '.7rem .9rem', borderInlineStart: '3px solid var(--ih-danger)', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.82rem' }}>
              سبب الاعتذار: {collab.declineReason}
            </div>
          )}
          {collab.submissionNote && (
            <div className="card" style={{ marginTop: '.8rem', padding: '.7rem .9rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.82rem' }}>
              ملاحظة التسليم: {collab.submissionNote}
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

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(null)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 460 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{modal.label}</h3>
            <textarea value={value} onChange={(e) => setValue(e.target.value)} className="field" rows={3}
              placeholder={modal.input === 'reason' ? 'سبب الاعتذار (اختياري)' : 'ملاحظة التسليم (اختياري)'}
              style={{ width: '100%', resize: 'vertical' }} autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy} onClick={submitModal} className={`btn ${BTN[modal.tone] ?? 'btn-primary'}`}>تأكيد</button>
              <button disabled={busy} onClick={() => setModal(null)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
