import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Sec, SummaryStrip, WorkspaceHeader , WaitingNotice } from '@/Components/ui';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';

interface Contract {
  id: number; number: string; title: string; party: string | null; partyType: string;
  valueMinor: number; currency: string; startDate: string | null; endDate: string | null; terms: string | null;
  status: string; statusLabel: string; statusTone: string; signedByName: string | null; signedAt: string | null;
}
type Action = [string, string, string, boolean];
interface History { from: string; to: string; by: string; reason: string | null; at: string | null }
interface WaitingInfo { party: string; expects: string; canRemind: boolean }
interface Props { contract: Contract; canManage: boolean; actions: Action[]; history: History[]; waitingOn: WaitingInfo | null; }

const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };
const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

export default function ContractShow({ contract, canManage, actions, history, waitingOn}: Props) {
  const { props } = usePage<SharedProps>();
  const [reasonFor, setReasonFor] = useState<Action | null>(null);
  const [reason, setReason] = useState('');
  // التحرير متاح على المسودة فقط — الخدمة ترفض ما بعدها، فلا نعرض ما لا يُقبل
  const editable = canManage && contract.status === 'draft';
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editErrors, setEditErrors] = useState<Record<string, string>>({});
  const [draft, setDraft] = useState({
    title: contract.title,
    terms: contract.terms ?? '',
    value: String(contract.valueMinor / 100),
    start_date: contract.startDate ?? '',
    end_date: contract.endDate ?? '',
  });

  const saveDraft = () => {
    if (!draft.title.trim()) return;
    const riyals = Number(draft.value || 0);
    setSaving(true);
    router.post(u(`/contracts/${contract.id}`), {
      title: draft.title, terms: draft.terms,
      value_minor: Number.isFinite(riyals) ? Math.round(riyals * 100) : 0,
      start_date: draft.start_date, end_date: draft.end_date,
    }, {
      preserveScroll: true,
      onFinish: () => setSaving(false),
      onError: (e) => setEditErrors(e as Record<string, string>),
      onSuccess: () => { setEditing(false); setEditErrors({}); },
    });
  };

  const runAction = (a: Action) => {
    if (a[3]) { setReasonFor(a); setReason(''); return; }
    router.post(u(`/contracts/${contract.id}/${a[0]}`), {}, { preserveScroll: true });
  };
  const submitReason = () => {
    if (!reasonFor) return;
    router.post(u(`/contracts/${contract.id}/${reasonFor[0]}`), { reason }, { preserveScroll: true, onSuccess: () => setReasonFor(null) });
  };

  return (
    <AppShell heading="عقد">
      <Head title={contract.title} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={`عقد · ${contract.number}`}
        title={contract.title}
        statusTone={contract.statusTone} statusLabel={contract.statusLabel}
        back={u("/contracts")} backLabel="كل العقود"
        meta={[
          ['الطرف', `${contract.party ?? '—'} (${contract.partyType})`], ['القيمة', money(contract.valueMinor, contract.currency)],
          ['البداية', contract.startDate ?? '—'], ['النهاية', contract.endDate ?? '—'],
        ]}
        actions={canManage && actions.length > 0 ? <>{actions.map((a) => (
          <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
        ))}</> : undefined}
      />

      {/* الانتظار يُعلَن: قائمة إجراءات فارغة بلا سبب تبدو عطلًا */}
      <WaitingNotice waiting={waitingOn} />

      {contract.signedAt && (
        <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.85rem' }}>
          قُبِل في {contract.signedAt}{contract.signedByName ? ` — ${contract.signedByName}` : ''} · قبول داخل المنصّة (تسجيل موافقة، ليس توقيعًا قانونيًا خارجيًا).
        </div>
      )}

      <SummaryStrip items={[
        { label: 'القيمة', value: money(contract.valueMinor, contract.currency), tone: 'primary', icon: 'wallet' },
        { label: 'الطرف', value: contract.partyType },
        { label: 'البداية', value: contract.startDate ?? '—' },
        { label: 'النهاية', value: contract.endDate ?? '—' },
        { label: 'قبِله', value: contract.signedByName ?? '—' },
      ]} />

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <Sec title="بنود العقد" icon="file-text">
          <div className="ih-sec__body">
            {editable && !editing && (
              <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '.7rem' }}>
                <button onClick={() => setEditing(true)} className="btn btn-xs btn-outline">تحرير المسودة</button>
              </div>
            )}
            {editing ? (
              <div style={{ display: 'grid', gap: '.8rem' }}>
                <Field label="العنوان" labelStyle={LBL}>
                  <input value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} className="field" style={{ width: '100%' }} />
                  {editErrors.title && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{editErrors.title}</div>}
                </Field>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
                  <Field label="القيمة (ر.س)" labelStyle={LBL}>
                    <input type="number" min={0} step="0.01" value={draft.value} onChange={(e) => setDraft({ ...draft, value: e.target.value })}
                      className="field" style={{ width: '100%', direction: 'ltr' }} />
                  </Field>
                  <Field label="البداية" labelStyle={LBL}>
                    <input type="date" value={draft.start_date} onChange={(e) => setDraft({ ...draft, start_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                  </Field>
                  <Field label="النهاية" labelStyle={LBL}>
                    <input type="date" value={draft.end_date} onChange={(e) => setDraft({ ...draft, end_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                    {editErrors.end_date && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.72rem', marginTop: '.3rem' }}>{editErrors.end_date}</div>}
                  </Field>
                </div>
                <Field label="البنود" labelStyle={LBL}>
                  <textarea value={draft.terms} onChange={(e) => setDraft({ ...draft, terms: e.target.value })} className="field" rows={8} style={{ width: '100%' }} />
                </Field>
                {editErrors.wf && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.8rem' }}>{editErrors.wf}</div>}
                <div style={{ display: 'flex', gap: '.5rem' }}>
                  <button disabled={saving || !draft.title.trim()} onClick={saveDraft} className="btn btn-sm btn-primary">حفظ</button>
                  <button disabled={saving} onClick={() => { setEditing(false); setEditErrors({}); }} className="btn btn-sm btn-ghost">إلغاء</button>
                </div>
              </div>
            ) : (
              <p style={{ margin: 0, lineHeight: 1.8, whiteSpace: 'pre-wrap', color: contract.terms ? 'var(--ih-text)' : 'var(--ih-text-muted)' }}>{contract.terms ?? 'لا بنود مسجّلة.'}</p>
            )}
          </div>
        </Sec>
        <Sec title="سجل الحالة" icon="bar-chart-3">
          <div className="ih-sec__body">
            {history.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا سجل بعد.</div> :
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
            <textarea className="field" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="السبب" autoFocus />
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
