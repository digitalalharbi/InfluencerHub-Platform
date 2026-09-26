import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, SummaryStrip, WorkspaceHeader } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';
import { PdfPreviewModal, type PreviewDoc } from '@/Components/PdfPreviewModal';

interface Payout {
  id: number; number: string; creator: string | null; amountMinor: number; currency: string;
  ibanLast4: string | null; description: string | null; dueDate: string | null;
  paidAt: string | null; paymentReference: string | null; failureReason: string | null;
  status: string; statusLabel: string; statusTone: string;
}
type Action = [string, string, string, 'none' | 'reason' | 'date' | 'reference'];
interface History { from: string; to: string; by: string; reason: string | null; at: string | null }
/** `canManage` (صلاحية التعديل) لم يعد يحجب شريط الإجراءات — انظر التعليق أدناه. */
interface Props { payout: Payout; actions: Action[]; providerNote: boolean; history: History[]; documents: { statement: PreviewDoc } }

const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };
const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;
const PAYLOAD_KEY: Record<string, string> = { reason: 'reason', date: 'due_date', reference: 'payment_reference' };

export default function PayoutShow({ payout, actions, providerNote, history, documents }: Props) {
  const t = useT();
  const [stmtOpen, setStmtOpen] = useState(false);
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
    <AppShell heading={t('payouts.show_heading')}>
      <Head title={payout.number} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={t('payouts.show_eyebrow', { num: payout.number })}
        title={payout.creator ?? '—'}
        statusTone={payout.statusTone} statusLabel={payout.statusLabel}
        back={u("/payouts")} backLabel={t('payouts.back_all')}
        meta={[
          [t('payouts.m_amount'), money(payout.amountMinor, payout.currency)], ['IBAN', payout.ibanLast4 ? `•••• ${payout.ibanLast4}` : '—'],
          [t('payouts.m_due'), payout.dueDate ?? '—'], [t('payouts.m_paid'), payout.paidAt ?? '—'],
        ]}
        /* `canManage` هو صلاحية *التعديل* وهي مقصورة على «قيد الانتظار»
           (`isEditable`). ربط شريط الإجراءات بها كان يُخفي الجدولة والصرف عن
           المالية فور الاعتماد — فيقف المستحقّ المعتمَد بلا مخرج رغم أن
           المتحكّم فحص كل فعل بقاعدته وأرسله في `actions`. */
        actions={<>
          <button onClick={() => setStmtOpen(true)} className="btn btn-sm btn-outline" title={t('payouts.stmt_preview_title')}>
            <Icon name="file-text" size={14} /> {t('payouts.stmt_pdf')}{documents.statement.stale && <span style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--ih-warning-ink, #B54708)', display: 'inline-block', marginInlineStart: 5 }} />}
          </button>
          {actions.map((a) => (
            <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
          ))}
        </>}
      />

      {providerNote && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.84rem' }}>
          <Icon name="clipboard-check" size={15} /> {t('payouts.provider_note')}
        </div>
      )}

      <SummaryStrip items={[
        { label: t('payouts.m_amount'), value: money(payout.amountMinor, payout.currency), tone: 'primary', icon: 'wallet' },
        { label: 'IBAN', value: payout.ibanLast4 ? `•••• ${payout.ibanLast4}` : '—' },
        { label: t('payouts.m_due'), value: payout.dueDate ?? '—' },
        { label: t('payouts.m_reference'), value: payout.paymentReference ?? '—' },
        { label: t('payouts.ss_paid_at'), value: payout.paidAt ?? '—' },
      ]} />

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <Sec title={t('payouts.sec_details')} icon="wallet">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
            {payout.description && <p style={{ margin: 0, lineHeight: 1.7 }}>{payout.description}</p>}
            {payout.failureReason && <div style={{ padding: '.6rem .8rem', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', borderRadius: 'var(--ih-radius-sm)', fontSize: '.85rem' }}><b>{t('payouts.failure_label')}</b> {payout.failureReason}</div>}
            {!payout.description && !payout.failureReason && <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>{t('payouts.no_description')}</div>}
          </div>
        </Sec>
        <Sec title={t('payouts.sec_history')} icon="bar-chart-3">
          <div className="ih-sec__body">
            {history.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>{t('payouts.no_history')}</div> :
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
              <input className="field" value={value} onChange={(e) => setValue(e.target.value)} placeholder={t('payouts.ref_placeholder')} style={{ direction: 'ltr' }} autoFocus />
            ) : (
              <textarea className="field" rows={3} value={value} onChange={(e) => setValue(e.target.value)} placeholder={t('payouts.reason_placeholder')} autoFocus />
            )}
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button className={`btn ${BTN[modalFor[2]] ?? 'btn-primary'}`} onClick={submitModal} disabled={(modalFor[3] === 'reference' || modalFor[3] === 'reason') && !value.trim()}>{t('payouts.confirm')}</button>
              <button className="btn btn-ghost" onClick={() => setModalFor(null)}>{t('payouts.cancel')}</button>
            </div>
          </div>
        </div>
      )}
      <PdfPreviewModal doc={documents.statement} open={stmtOpen} onClose={() => setStmtOpen(false)} />
    </AppShell>
  );
}
