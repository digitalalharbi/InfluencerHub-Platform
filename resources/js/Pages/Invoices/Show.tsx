import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { Sec, WorkspaceHeader, sarShort } from '@/Components/ui'
import { Icon } from '@/Components/Icon'
import { u } from '@/lib/href'
import { PdfPreviewModal, type PreviewDoc } from '@/Components/PdfPreviewModal'
import { useT } from '@/lib/i18n'

interface Invoice {
  id: number; number: string; client: string | null; campaign: string | null
  clientId: number | null; campaignId: number | null
  status: string; statusLabel: string; statusTone: string; currency: string
  totalMinor: number; paidMinor: number; balanceMinor: number
  subtotalMinor: number; discountMinor: number; taxMinor: number; taxRateBp: number
  issueDate: string | null; dueDate: string | null; isOverdue: boolean
  notes: string | null; cancelReason: string | null
}
interface Item { id: number; description: string; quantity: number; unitPriceMinor: number; lineTotalMinor: number }
interface Payment {
  id: number; amountMinor: number; method: string; methodLabel: string
  reference: string | null; receivedAt: string | null; note: string | null
}
interface Hist { from: string; to: string; reason: string | null; at: string | null }
interface Props {
  invoice: Invoice; items: Item[]; payments: Payment[]; history: Hist[]
  can: { edit: boolean; issue: boolean; pay: boolean; cancel: boolean }
  paymentMethods: Record<string, string>
  documents: { pdf: PreviewDoc }
}

export default function InvoiceShow({ invoice, items, payments, history, can, paymentMethods, documents }: Props) {
  const t = useT()
  const [pdfOpen, setPdfOpen] = useState(false)
  const { errors } = usePage().props as { errors?: Record<string, string> }
  const [panel, setPanel] = useState<'pay' | 'cancel' | null>(null)
  const [busy, setBusy] = useState(false)

  const [amount, setAmount] = useState('')
  const [method, setMethod] = useState('bank_transfer')
  const [receivedAt, setReceivedAt] = useState(new Date().toISOString().slice(0, 10))
  const [reference, setReference] = useState('')
  const [reason, setReason] = useState('')

  const act = (path: string, data: Record<string, string> = {}) => {
    setBusy(true)
    router.post(u(`/invoices/${invoice.id}/${path}`), data, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
      onSuccess: () => { setPanel(null); setAmount(''); setReason('') },
    })
  }

  const paidPct = invoice.totalMinor > 0 ? Math.round((invoice.paidMinor / invoice.totalMinor) * 100) : 0

  return (
    <AppShell heading={t('invoices.show_heading')}>
      <Head title={invoice.number} />

      <WorkspaceHeader
        eyebrow={t('invoices.show_eyebrow', { num: invoice.number })}
        title={invoice.client ?? t('invoices.show_heading')}
        statusTone={invoice.statusTone} statusLabel={invoice.statusLabel}
        back={u('/invoices')} backLabel={t('invoices.back_all')}
        meta={[
          [t('invoices.m_total'), sarShort(invoice.totalMinor)],
          [t('invoices.m_collected'), sarShort(invoice.paidMinor)],
          [t('invoices.m_balance'), sarShort(invoice.balanceMinor)],
          [t('invoices.m_due'), invoice.dueDate ?? '—'],
        ]}
        actions={
          <>
            <button onClick={() => setPdfOpen(true)} className="btn btn-sm btn-outline" title={t('invoices.preview_pdf')}>{t('invoices.preview_pdf')}{documents.pdf.stale && <span style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--ih-warning-ink, #B54708)', display: 'inline-block', marginInlineStart: 5 }} />}</button>
            {can.issue && <button onClick={() => act('issue')} className="btn btn-sm" disabled={busy}>{t('invoices.act_issue')}</button>}
            {can.pay && <button onClick={() => setPanel(panel === 'pay' ? null : 'pay')} className="btn btn-sm">{t('invoices.act_pay')}</button>}
            {can.cancel && <button onClick={() => setPanel(panel === 'cancel' ? null : 'cancel')} className="btn btn-sm btn-outline">{t('invoices.cancel')}</button>}
          </>
        }
      />

      {/* سلسلة الاتصال: الفاتورة تعرف عميلها وحملتها */}
      <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap', alignItems: 'center', marginBottom: '1rem', fontSize: '.78rem' }}>
        <span style={{ color: 'var(--ih-text-muted)' }}>{t('invoices.linked_to')}</span>
        {invoice.clientId && (
          <a href={u(`/clients/${invoice.clientId}`)} className="btn btn-xs btn-outline">
            <Icon name="building-2" size={13} /> {invoice.client}
          </a>
        )}
        {invoice.campaignId && (
          <a href={u(`/campaigns/${invoice.campaignId}`)} className="btn btn-xs btn-outline">
            <Icon name="megaphone" size={13} /> {invoice.campaign}
          </a>
        )}
      </div>

      {(errors?.invoice || errors?.payment) && (
        <div className="pub-error-banner" style={{ marginBottom: '1rem' }}>{errors.invoice ?? errors.payment}</div>
      )}

      {invoice.status === 'cancelled' && invoice.cancelReason && (
        <Sec title={t('invoices.cancel_reason_title')}><p style={{ color: 'var(--ih-text-secondary)' }}>{invoice.cancelReason}</p></Sec>
      )}

      {panel === 'pay' && (
        <Sec title={t('invoices.act_pay')}>
          {/* التسجيل لا التحصيل: لا مزوّد دفع مربوط، وهذه واقعة وقعت خارج النظام */}
          <p style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)', marginBlockEnd: '.75rem' }}>
            {t('invoices.pay_hint', { bal: sarShort(invoice.balanceMinor) })}
          </p>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '.8rem' }}>
            <Fld label={t('invoices.f_amount')}><input className="field" type="number" min="0.01" step="0.01" style={{ width: '100%' }}
              value={amount} onChange={(e) => setAmount(e.target.value)} autoFocus /></Fld>
            <Fld label={t('invoices.f_method')}><select className="field" style={{ width: '100%' }} value={method} onChange={(e) => setMethod(e.target.value)}>
              {Object.entries(paymentMethods).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select></Fld>
            <Fld label={t('invoices.f_received')}><input className="field" type="date" style={{ width: '100%' }}
              value={receivedAt} onChange={(e) => setReceivedAt(e.target.value)} /></Fld>
            <Fld label={t('invoices.f_reference')}><input className="field" style={{ width: '100%' }} value={reference}
              onChange={(e) => setReference(e.target.value)} placeholder={t('invoices.ref_placeholder')} /></Fld>
          </div>
          <div style={{ display: 'flex', gap: '.5rem', marginTop: '.9rem', alignItems: 'center' }}>
            <button className="btn btn-sm" disabled={busy || !(Number(amount) > 0)}
              onClick={() => act('pay', { amount_riyals: amount, method, received_at: receivedAt, provider_reference: reference })}>
              {t('invoices.save_payment')}
            </button>
            <button className="btn btn-sm btn-ghost" onClick={() => setPanel(null)}>{t('invoices.cancel')}</button>
            {!(Number(amount) > 0) && <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{t('invoices.amount_gt_zero')}</span>}
          </div>
        </Sec>
      )}

      {panel === 'cancel' && (
        <Sec title={t('invoices.cancel_title')}>
          <Fld label={t('invoices.f_cancel_reason')}>
            <textarea className="field" style={{ width: '100%' }} rows={2} value={reason}
              onChange={(e) => setReason(e.target.value)} autoFocus />
          </Fld>
          <div style={{ display: 'flex', gap: '.5rem', marginTop: '.8rem', alignItems: 'center' }}>
            <button className="btn btn-sm btn-outline" disabled={busy || !reason.trim()}
              onClick={() => act('cancel', { reason })}>{t('invoices.confirm_cancel')}</button>
            <button className="btn btn-sm btn-ghost" onClick={() => setPanel(null)}>{t('invoices.undo')}</button>
            {!reason.trim() && <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{t('invoices.cancel_reason_hint')}</span>}
          </div>
        </Sec>
      )}

      <Sec title={t('invoices.items')} icon="file-text">
        <div className="ih-dt-wrap"><div className="ih-dt-scroll">
          <table className="ih-dt">
            <thead><tr><th>{t('invoices.item_desc')}</th><th>{t('invoices.th_qty')}</th><th>{t('invoices.th_unit')}</th><th>{t('invoices.m_total')}</th></tr></thead>
            <tbody>
              {items.map((i) => (
                <tr key={i.id}>
                  <td>{i.description}</td>
                  <td style={{ direction: 'ltr' }}>{i.quantity}</td>
                  <td style={{ direction: 'ltr' }}>{sarShort(i.unitPriceMinor)}</td>
                  <td style={{ direction: 'ltr' }}>{sarShort(i.lineTotalMinor)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div></div>

        <div style={{ marginTop: '1rem', display: 'grid', gap: '.3rem', maxWidth: 320, marginInlineStart: 'auto', fontSize: '.875rem' }}>
          <Line label={t('invoices.subtotal')} minor={invoice.subtotalMinor} />
          {invoice.discountMinor > 0 && <Line label={t('invoices.discount_row')} minor={-invoice.discountMinor} />}
          <Line label={t('invoices.vat', { rate: String(invoice.taxRateBp / 100) })} minor={invoice.taxMinor} />
          <div style={{ borderTop: '1px solid var(--ih-border)', paddingTop: '.35rem', fontWeight: 700 }}>
            <Line label={t('invoices.m_total')} minor={invoice.totalMinor} />
          </div>
          {invoice.paidMinor > 0 && (
            <>
              <Line label={t('invoices.m_collected')} minor={invoice.paidMinor} />
              <Line label={t('invoices.m_balance')} minor={invoice.balanceMinor} />
            </>
          )}
        </div>
      </Sec>

      <Sec title={t('invoices.payments_title', { pct: String(paidPct) })} icon="wallet">
        {payments.length === 0 ? (
          <p style={{ color: 'var(--ih-text-secondary)', fontSize: '.875rem' }}>
            {invoice.status === 'draft' ? t('invoices.no_payments_draft') : t('invoices.no_payments')}
          </p>
        ) : (
          <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '.5rem' }}>
            {payments.map((p) => (
              <li key={p.id} style={{ display: 'flex', gap: '.75rem', alignItems: 'center', padding: '.6rem .8rem', border: '1px solid var(--ih-border)', borderRadius: 10 }}>
                <span style={{ fontWeight: 700, direction: 'ltr' }}>{sarShort(p.amountMinor)}</span>
                <span style={{ fontSize: '.8rem', color: 'var(--ih-text-secondary)' }}>{p.methodLabel}</span>
                <span style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{p.receivedAt}</span>
                {p.reference && <span style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{p.reference}</span>}
              </li>
            ))}
          </ul>
        )}
      </Sec>

      <Sec title={t('invoices.history_title')} icon="activity">
        <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '.4rem', fontSize: '.82rem' }}>
          {history.map((h, i) => (
            <li key={i} style={{ display: 'flex', gap: '.6rem', color: 'var(--ih-text-secondary)' }}>
              <span style={{ direction: 'ltr', color: 'var(--ih-text-muted)' }}>{h.at}</span>
              <span>{h.from} ← {h.to}</span>
              {h.reason && <span style={{ color: 'var(--ih-text-muted)' }}>· {h.reason}</span>}
            </li>
          ))}
        </ul>
      </Sec>
      <PdfPreviewModal doc={documents.pdf} open={pdfOpen} onClose={() => setPdfOpen(false)} />
    </AppShell>
  )
}

function Line({ label, minor }: { label: string; minor: number }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
      <span>{label}</span><span style={{ direction: 'ltr' }}>{sarShort(minor)}</span>
    </div>
  )
}

function Fld({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label style={{ fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' }}>{label}</label>
      {children}
    </div>
  )
}
