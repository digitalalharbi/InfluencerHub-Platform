import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, SummaryStrip, WorkspaceHeader } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';

interface Payout {
  id: number; number: string; creator: string | null; amountMinor: number; currency: string;
  ibanLast4: string | null; description: string | null; dueDate: string | null;
  paidAt: string | null; paymentReference: string | null; failureReason: string | null;
  status: string; statusLabel: string; statusTone: string;
}
type Action = [string, string, string, 'none' | 'reason' | 'date' | 'reference'];
interface History { from: string; to: string; by: string; reason: string | null; at: string | null }
/** `canManage` (صلاحية التعديل) لم يعد يحجب شريط الإجراءات — انظر التعليق أدناه. */
interface Props { payout: Payout; actions: Action[]; providerNote: boolean; history: History[] }

const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };
const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;
const PAYLOAD_KEY: Record<string, string> = { reason: 'reason', date: 'due_date', reference: 'payment_reference' };

export default function PayoutShow({ payout, actions, providerNote, history }: Props) {
  const { props } = usePage<SharedProps>();
  const [modalFor, setModalFor] = useState<Action | null>(null);
  const [value, setValue] = useState('');

  const runAction = (a: Action) => {
    if (a[3] === 'none') { router.post(u(`/payouts/${payout.id}/${a[0]}`), {}, { preserveScroll: true }); return; }
    setModalFor(a); setValue('');
  };
  const submitModal = () => {
    if (!modalFor) return;
    const key = PAYLOAD_KEY[modalFor[3]];
    router.post(u(`/payouts/${payout.id}/${modalFor[0]}`), { [key]: value }, { preserveScroll: true, onSuccess: () => setModalFor(null) });
  };

  return (
    <AppShell heading="مستحق">
      <Head title={payout.number} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={`مستحق · ${payout.number}`}
        title={payout.creator ?? '—'}
        statusTone={payout.statusTone} statusLabel={payout.statusLabel}
        back={u("/payouts")} backLabel="كل المستحقات"
        meta={[
          ['المبلغ', money(payout.amountMinor, payout.currency)], ['IBAN', payout.ibanLast4 ? `•••• ${payout.ibanLast4}` : '—'],
          ['الاستحقاق', payout.dueDate ?? '—'], ['دُفع', payout.paidAt ?? '—'],
        ]}
        /* `canManage` هو صلاحية *التعديل* وهي مقصورة على «قيد الانتظار»
           (`isEditable`). ربط شريط الإجراءات بها كان يُخفي الجدولة والصرف عن
           المالية فور الاعتماد — فيقف المستحقّ المعتمَد بلا مخرج رغم أن
           المتحكّم فحص كل فعل بقاعدته وأرسله في `actions`. */
        actions={actions.length > 0 ? <>{actions.map((a) => (
          <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
        ))}</> : undefined}
      />

      {providerNote && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.84rem' }}>
          <Icon name="clipboard-check" size={15} /> بانتظار ربط مزوّد دفع. النظام لا ينفّذ التحويل — تُسجَّل «مدفوع» يدويًا بمرجع تحويل بعد التسوية الفعلية.
        </div>
      )}

      <SummaryStrip items={[
        { label: 'المبلغ', value: money(payout.amountMinor, payout.currency), tone: 'primary', icon: 'wallet' },
        { label: 'IBAN', value: payout.ibanLast4 ? `•••• ${payout.ibanLast4}` : '—' },
        { label: 'الاستحقاق', value: payout.dueDate ?? '—' },
        { label: 'مرجع الدفع', value: payout.paymentReference ?? '—' },
        { label: 'دُفع في', value: payout.paidAt ?? '—' },
      ]} />

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <Sec title="تفاصيل المستحق" icon="wallet">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
            {payout.description && <p style={{ margin: 0, lineHeight: 1.7 }}>{payout.description}</p>}
            {payout.failureReason && <div style={{ padding: '.6rem .8rem', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', borderRadius: 'var(--ih-radius-sm)', fontSize: '.85rem' }}><b>سبب الفشل:</b> {payout.failureReason}</div>}
            {!payout.description && !payout.failureReason && <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا وصف إضافي.</div>}
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

      {modalFor && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setModalFor(null)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{modalFor[1]}</h3>
            {modalFor[3] === 'date' ? (
              <input className="field" type="date" value={value} onChange={(e) => setValue(e.target.value)} autoFocus />
            ) : modalFor[3] === 'reference' ? (
              <input className="field" value={value} onChange={(e) => setValue(e.target.value)} placeholder="مرجع التحويل (إلزامي)" style={{ direction: 'ltr' }} autoFocus />
            ) : (
              <textarea className="field" rows={3} value={value} onChange={(e) => setValue(e.target.value)} placeholder="السبب" autoFocus />
            )}
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button className={`btn ${BTN[modalFor[2]] ?? 'btn-primary'}`} onClick={submitModal} disabled={(modalFor[3] === 'reference' || modalFor[3] === 'reason') && !value.trim()}>تأكيد</button>
              <button className="btn btn-ghost" onClick={() => setModalFor(null)}>إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
