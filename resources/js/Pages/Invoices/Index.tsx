import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { Kpi, ListHead, StatusBadge, sarShort } from '@/Components/ui'
import { Donut } from '@/Components/Charts'
import { Icon } from '@/Components/Icon'
import { Pagination, type Paginated } from '@/Components/Pagination'
import { u } from '@/lib/href'
import { useT } from '@/lib/i18n'

interface Row {
  id: number; number: string; client: string | null; campaign: string | null
  status: string; statusLabel: string; statusTone: string; currency: string
  totalMinor: number; paidMinor: number; balanceMinor: number
  issueDate: string | null; dueDate: string | null; isOverdue: boolean
}
interface Summary {
  total: number; draft: number; open: number; paid: number
  outstandingMinor: number; collectedMinor: number
}
interface Options {
  clients: { id: number; name: string }[]
  campaigns: { id: number; name: string; clientId: number }[]
}
interface Props {
  invoices: Paginated<Row>; filters: { q?: string; seg?: string }
  summary: Summary; canCreate: boolean; options: Options
  /** من InvoiceService::DEFAULT_TAX_RATE_BP — لا نسخة ثابتة ثانية في الواجهة */
  defaultTaxRateBp: number
}

interface DraftItem { description: string; quantity: string; unit_price_riyals: string; deliverable_id: number | null }

const SEGMENT_KEYS = ['all', 'draft', 'open', 'paid', 'cancelled'] as const

/**
 * إنشاء فاتورة.
 *
 * اختيار الحملة يجلب بنودها من المخرجات المسجّلة: ما هو مسجّل لا يُعاد إدخاله.
 * والاقتراح قابل للتعديل لأن الحملة قد تُفوتَر على دفعات.
 */
function NewInvoiceModal({ options, taxRateBp, onClose }: { options: Options; taxRateBp: number; onClose: () => void }) {
  const t = useT()
  const [clientId, setClientId] = useState(options.clients.length === 1 ? String(options.clients[0].id) : '')
  const [campaignId, setCampaignId] = useState('')
  const [dueDate, setDueDate] = useState('')
  const [discount, setDiscount] = useState('')
  const [items, setItems] = useState<DraftItem[]>([
    { description: '', quantity: '1', unit_price_riyals: '', deliverable_id: null },
  ])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)
  const [loadingItems, setLoadingItems] = useState(false)

  const campaignsForClient = options.campaigns.filter((c) => String(c.clientId) === clientId)

  const pullFromCampaign = async (id: string) => {
    setCampaignId(id)
    if (!id) return
    setLoadingItems(true)
    try {
      const res = await fetch(u(`/campaigns/${id}/invoice-items`), {
        headers: { Accept: 'application/json' }, credentials: 'same-origin',
      })
      if (!res.ok) return
      const data = await res.json()
      if (Array.isArray(data.items) && data.items.length) {
        setItems(data.items.map((i: { description: string; quantity: number; unit_price_minor: number; deliverable_id: number | null }) => ({
          description: i.description,
          quantity: String(i.quantity),
          unit_price_riyals: (i.unit_price_minor / 100).toString(),
          deliverable_id: i.deliverable_id,
        })))
      }
    } finally {
      setLoadingItems(false)
    }
  }

  const setItem = (idx: number, patch: Partial<DraftItem>) =>
    setItems((prev) => prev.map((it, i) => (i === idx ? { ...it, ...patch } : it)))

  const subtotal = items.reduce(
    (s, i) => s + (Number(i.quantity) || 0) * (Number(i.unit_price_riyals) || 0), 0,
  )
  const afterDiscount = Math.max(0, subtotal - (Number(discount) || 0))
  const tax = (afterDiscount * taxRateBp) / 10000
  const ready = clientId !== '' && items.some((i) => i.description.trim() && Number(i.unit_price_riyals) > 0)

  const submit = () => {
    setBusy(true)
    router.post(u('/invoices'), {
      client_id: clientId, campaign_id: campaignId || null, due_date: dueDate || null,
      discount_riyals: discount || '0',
      // الصفوف كائنات متداخلة: يقبلها Inertia حمولةً ويعيدها Laravel مصفوفةً
      items: items.filter((i) => i.description.trim()) as unknown as Record<string, string>[],
    }, {
      onError: (e) => { setErrors(e as Record<string, string>); setBusy(false) },
      onFinish: () => setBusy(false),
    })
  }

  return (
    <div className="ih-modal-backdrop" role="dialog" aria-modal="true" aria-label={t('invoices.m_title')}>
      <div className="ih-modal" style={{ maxWidth: 720 }}>
        <h3 style={{ margin: '0 0 1rem' }}>{t('invoices.m_title')}</h3>

        <div style={{ display: 'grid', gap: '.8rem' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
            <Fld label={t('invoices.f_client')} error={errors.client_id} required>
              <select className="field" style={{ width: '100%' }} value={clientId}
                onChange={(e) => { setClientId(e.target.value); setCampaignId('') }}>
                <option value="">{t('invoices.choose_client')}</option>
                {options.clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </Fld>
            <Fld label={t('invoices.f_campaign')} error={errors.campaign_id}>
              <select className="field" style={{ width: '100%' }} value={campaignId}
                onChange={(e) => pullFromCampaign(e.target.value)} disabled={!clientId}>
                <option value="">{t('invoices.no_campaign')}</option>
                {campaignsForClient.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </Fld>
          </div>

          {loadingItems && (
            <p style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>{t('invoices.loading_items')}</p>
          )}
          {campaignId && !loadingItems && (
            <p style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
              {t('invoices.items_hint')}
            </p>
          )}

          <div>
            <div style={{ fontSize: '.8rem', fontWeight: 600, marginBottom: '.4rem' }}>{t('invoices.items')}</div>
            {items.map((it, idx) => (
              <div key={idx} style={{ display: 'grid', gridTemplateColumns: '1fr 70px 110px 32px', gap: '.4rem', marginBottom: '.4rem' }}>
                <input className="field" placeholder={t('invoices.item_desc')} value={it.description}
                  onChange={(e) => setItem(idx, { description: e.target.value })} />
                <input className="field" type="number" min="1" value={it.quantity}
                  onChange={(e) => setItem(idx, { quantity: e.target.value })} />
                <input className="field" type="number" min="0" step="0.01" placeholder="ر.س"
                  value={it.unit_price_riyals} onChange={(e) => setItem(idx, { unit_price_riyals: e.target.value })} />
                <button type="button" className="btn btn-xs btn-ghost" aria-label={t('invoices.del_item')}
                  onClick={() => setItems((p) => p.filter((_, i) => i !== idx))} disabled={items.length === 1}>×</button>
              </div>
            ))}
            <button type="button" className="btn btn-xs btn-outline"
              onClick={() => setItems((p) => [...p, { description: '', quantity: '1', unit_price_riyals: '', deliverable_id: null }])}>
              {t('invoices.add_item')}
            </button>
            {errors.items && <em style={{ display: 'block', fontSize: '.75rem', color: 'var(--ih-danger-ink)', fontStyle: 'normal' }}>{errors.items}</em>}
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
            <Fld label={t('invoices.discount')} error={errors.discount_riyals}>
              <input className="field" type="number" min="0" step="0.01" style={{ width: '100%' }}
                value={discount} onChange={(e) => setDiscount(e.target.value)} />
            </Fld>
            <Fld label={t('invoices.due_date')} error={errors.due_date}>
              <input className="field" type="date" style={{ width: '100%' }}
                value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
            </Fld>
          </div>

          {/* المجاميع تُعرض قبل الحفظ: لا يُصدَر مبلغ لم يره صاحبه */}
          <div style={{ background: 'var(--ih-surface-sunken)', borderRadius: 10, padding: '.75rem 1rem', fontSize: '.85rem', display: 'grid', gap: '.25rem' }}>
            <Row label={t('invoices.subtotal')} value={subtotal} />
            {Number(discount) > 0 && <Row label={t('invoices.discount_row')} value={-(Number(discount) || 0)} />}
            <Row label={t('invoices.vat', { rate: taxRateBp / 100 })} value={tax} />
            <div style={{ borderTop: '1px solid var(--ih-border)', marginTop: '.25rem', paddingTop: '.35rem', fontWeight: 700 }}>
              <Row label={t('invoices.total')} value={afterDiscount + tax} />
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.2rem', justifyContent: 'flex-end', alignItems: 'center' }}>
          {!ready && <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{t('invoices.ready_hint')}</span>}
          <button onClick={onClose} className="btn btn-sm btn-ghost">{t('invoices.cancel')}</button>
          <button onClick={submit} className="btn btn-sm" disabled={busy || !ready}>
            {busy ? t('invoices.saving') : t('invoices.save_draft')}
          </button>
        </div>
      </div>
    </div>
  )
}

function Row({ label, value }: { label: string; value: number }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
      <span>{label}</span>
      <span style={{ direction: 'ltr' }}>{value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ر.س</span>
    </div>
  )
}

function Fld({ label, error, required, children }: { label: string; error?: string; required?: boolean; children: React.ReactNode }) {
  return (
    <div>
      <label style={{ fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' }}>
        {label}{required && <b style={{ color: 'var(--ih-danger-ink)' }}> *</b>}
      </label>
      {children}
      {error && <em style={{ fontSize: '.75rem', color: 'var(--ih-danger-ink)', fontStyle: 'normal' }}>{error}</em>}
    </div>
  )
}

export default function InvoicesIndex({ invoices, filters, summary, canCreate, options, defaultTaxRateBp }: Props) {
  const t = useT()
  const [creating, setCreating] = useState(false)
  const seg = filters.seg ?? 'all'

  return (
    <AppShell heading={t('invoices.title')}>
      <Head title={t('invoices.title')} />
      <ListHead
        eyebrow={t('invoices.eyebrow')}
        title={t('invoices.title')}
        sub={t('invoices.sub')}
        actions={canCreate ? (
          <button onClick={() => setCreating(true)} className="btn btn-sm btn-primary">
            <Icon name="file-text" size={15} /> {t('invoices.new_invoice')}
          </button>
        ) : undefined}
      />

      {creating && <NewInvoiceModal options={options} taxRateBp={defaultTaxRateBp} onClose={() => setCreating(false)} />}

      <div className="ih-kpis">
        <Kpi label={t('invoices.kpi_outstanding')} icon="wallet" value={sarShort(summary.outstandingMinor)}
          sub={t('invoices.kpi_outstanding_sub', { n: summary.open })} tone={summary.outstandingMinor ? 'warning' : undefined} />
        <Kpi label={t('invoices.kpi_collected')} icon="wallet" value={sarShort(summary.collectedMinor)} sub={t('invoices.kpi_collected_sub', { n: summary.paid })} />
        <Kpi label={t('invoices.kpi_draft')} icon="file-text" value={summary.draft.toLocaleString('en-US')} sub={t('invoices.kpi_draft_sub')} />
        <Kpi label={t('invoices.kpi_total')} icon="bar-chart-3" value={summary.total.toLocaleString('en-US')} sub={t('invoices.kpi_total_sub')} />
      </div>

      {/* نظرة التحصيل — كم حُصِّل مقابل ما بقي (مبالغ فعلية) */}
      {(() => {
        const billed = summary.collectedMinor + summary.outstandingMinor;
        if (billed <= 0) return null;
        const segs = [
          { label: t('invoices.d_collected'), value: summary.collectedMinor, color: 'var(--ih-success-700, #067647)' },
          { label: t('invoices.d_outstanding'), value: summary.outstandingMinor, color: 'var(--ih-warning-ink, #B54708)' },
        ];
        const pct = Math.round((summary.collectedMinor / billed) * 100);
        return (
          <div className="card" style={{ padding: '1.1rem 1.3rem', marginBottom: '1.2rem', display: 'flex', alignItems: 'center', gap: '1.6rem', flexWrap: 'wrap' }}>
            <Donut segments={segs} size={120} centerValue={`${pct}٪`} centerLabel={t('invoices.d_center')} ariaLabel={`${t('invoices.d_collected')} ${sarShort(summary.collectedMinor)} ر.س، ${t('invoices.d_outstanding')} ${sarShort(summary.outstandingMinor)} ر.س`} />
            <div style={{ flex: 1, minWidth: 200, display: 'grid', gap: '.55rem' }}>
              <div style={{ fontWeight: 800, fontSize: '.95rem' }}>{t('invoices.d_headline', { pct, total: sarShort(billed) })}</div>
              {segs.map((s) => (
                <div key={s.label} style={{ display: 'flex', alignItems: 'center', gap: '.5rem', fontSize: '.85rem' }}>
                  <span style={{ width: 10, height: 10, borderRadius: 3, background: s.color, flexShrink: 0 }} />
                  <span style={{ color: 'var(--ih-text-muted)', flex: 1 }}>{s.label}</span>
                  <span style={{ fontWeight: 700, direction: 'ltr' }}>{sarShort(s.value)} ر.س</span>
                </div>
              ))}
            </div>
          </div>
        );
      })()}

      <div className="ih-filterbar" style={{ marginBottom: '1rem', gap: '.4rem', flexWrap: 'wrap' }}>
        {SEGMENT_KEYS.map((k) => (
          <button key={k} onClick={() => router.get(u('/invoices'), k === 'all' ? {} : { seg: k }, { preserveState: true, replace: true })}
            className={`btn btn-sm${seg === k ? '' : ' btn-outline'}`}>{t(`invoices.seg_${k}`)}</button>
        ))}
      </div>

      {invoices.data.length === 0 ? (
        <div className="ih-empty"><div className="ih-empty__inner">
          <span className="ih-empty__icon"><Icon name="file-text" size={26} /></span>
          <div className="ih-empty__title">{t('invoices.empty_title')}</div>
          <div className="ih-empty__text">
            {t('invoices.empty_text')}
          </div>
          {canCreate && <button onClick={() => setCreating(true)} className="btn btn-sm">{t('invoices.new_invoice')}</button>}
        </div></div>
      ) : (
        <div className="ih-dt-wrap"><div className="ih-dt-scroll">
          <table className="ih-dt">
            <thead><tr>
              <th>{t('invoices.th_number')}</th><th>{t('invoices.th_client')}</th><th>{t('invoices.th_campaign')}</th><th>{t('invoices.th_total')}</th>
              <th>{t('invoices.th_balance')}</th><th>{t('invoices.th_due')}</th><th>{t('invoices.th_status')}</th><th></th>
            </tr></thead>
            <tbody>
              {invoices.data.map((i) => (
                <tr key={i.id}>
                  <td style={{ direction: 'ltr', fontWeight: 600 }}>{i.number}</td>
                  <td>{i.client ?? '—'}</td>
                  <td>{i.campaign ?? '—'}</td>
                  <td style={{ direction: 'ltr' }}>{sarShort(i.totalMinor)}</td>
                  <td style={{ direction: 'ltr', color: i.balanceMinor ? 'var(--ih-warning-ink)' : 'var(--ih-text-muted)' }}>
                    {sarShort(i.balanceMinor)}
                  </td>
                  <td style={{ direction: 'ltr', color: i.isOverdue ? 'var(--ih-danger-ink)' : undefined }}>
                    {i.dueDate ?? '—'}
                  </td>
                  <td><StatusBadge tone={i.statusTone} label={i.statusLabel} /></td>
                  <td><a href={u(`/invoices/${i.id}`)} className="btn btn-xs btn-outline">{t('invoices.open')}</a></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div></div>
      )}

      <Pagination links={invoices.links} />
    </AppShell>
  )
}
