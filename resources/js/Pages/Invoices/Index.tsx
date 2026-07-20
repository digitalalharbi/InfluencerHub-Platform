import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { Kpi, ListHead, StatusBadge, sarShort } from '@/Components/ui'
import { Icon } from '@/Components/Icon'
import { Pagination, type Paginated } from '@/Components/Pagination'
import { u } from '@/lib/href'

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

const SEGMENTS: [string, string][] = [
  ['all', 'الكل'], ['draft', 'مسودة'], ['open', 'قيد التحصيل'], ['paid', 'مدفوعة'], ['cancelled', 'ملغاة'],
]

/**
 * إنشاء فاتورة.
 *
 * اختيار الحملة يجلب بنودها من المخرجات المسجّلة: ما هو مسجّل لا يُعاد إدخاله.
 * والاقتراح قابل للتعديل لأن الحملة قد تُفوتَر على دفعات.
 */
function NewInvoiceModal({ options, taxRateBp, onClose }: { options: Options; taxRateBp: number; onClose: () => void }) {
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
    <div className="ih-modal-backdrop" role="dialog" aria-modal="true" aria-label="فاتورة جديدة">
      <div className="ih-modal" style={{ maxWidth: 720 }}>
        <h3 style={{ margin: '0 0 1rem' }}>فاتورة جديدة</h3>

        <div style={{ display: 'grid', gap: '.8rem' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
            <Fld label="العميل" error={errors.client_id} required>
              <select className="field" style={{ width: '100%' }} value={clientId}
                onChange={(e) => { setClientId(e.target.value); setCampaignId('') }}>
                <option value="">اختر عميلًا…</option>
                {options.clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </Fld>
            <Fld label="الحملة" error={errors.campaign_id}>
              <select className="field" style={{ width: '100%' }} value={campaignId}
                onChange={(e) => pullFromCampaign(e.target.value)} disabled={!clientId}>
                <option value="">بلا حملة</option>
                {campaignsForClient.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </Fld>
          </div>

          {loadingItems && (
            <p style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>يجري جلب مخرجات الحملة…</p>
          )}
          {campaignId && !loadingItems && (
            <p style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
              البنود مقترحة من مخرجات الحملة — عدّلها إن كنت تُفوتر جزءًا منها.
            </p>
          )}

          <div>
            <div style={{ fontSize: '.8rem', fontWeight: 600, marginBottom: '.4rem' }}>البنود</div>
            {items.map((it, idx) => (
              <div key={idx} style={{ display: 'grid', gridTemplateColumns: '1fr 70px 110px 32px', gap: '.4rem', marginBottom: '.4rem' }}>
                <input className="field" placeholder="الوصف" value={it.description}
                  onChange={(e) => setItem(idx, { description: e.target.value })} />
                <input className="field" type="number" min="1" value={it.quantity}
                  onChange={(e) => setItem(idx, { quantity: e.target.value })} />
                <input className="field" type="number" min="0" step="0.01" placeholder="ر.س"
                  value={it.unit_price_riyals} onChange={(e) => setItem(idx, { unit_price_riyals: e.target.value })} />
                <button type="button" className="btn btn-xs btn-ghost" aria-label="حذف البند"
                  onClick={() => setItems((p) => p.filter((_, i) => i !== idx))} disabled={items.length === 1}>×</button>
              </div>
            ))}
            <button type="button" className="btn btn-xs btn-outline"
              onClick={() => setItems((p) => [...p, { description: '', quantity: '1', unit_price_riyals: '', deliverable_id: null }])}>
              + بند
            </button>
            {errors.items && <em style={{ display: 'block', fontSize: '.75rem', color: 'var(--ih-danger-ink)', fontStyle: 'normal' }}>{errors.items}</em>}
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
            <Fld label="الخصم (ر.س)" error={errors.discount_riyals}>
              <input className="field" type="number" min="0" step="0.01" style={{ width: '100%' }}
                value={discount} onChange={(e) => setDiscount(e.target.value)} />
            </Fld>
            <Fld label="تاريخ الاستحقاق" error={errors.due_date}>
              <input className="field" type="date" style={{ width: '100%' }}
                value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
            </Fld>
          </div>

          {/* المجاميع تُعرض قبل الحفظ: لا يُصدَر مبلغ لم يره صاحبه */}
          <div style={{ background: 'var(--ih-surface-sunken)', borderRadius: 10, padding: '.75rem 1rem', fontSize: '.85rem', display: 'grid', gap: '.25rem' }}>
            <Row label="المجموع" value={subtotal} />
            {Number(discount) > 0 && <Row label="الخصم" value={-(Number(discount) || 0)} />}
            <Row label={`ضريبة القيمة المضافة ${taxRateBp / 100}٪`} value={tax} />
            <div style={{ borderTop: '1px solid var(--ih-border)', marginTop: '.25rem', paddingTop: '.35rem', fontWeight: 700 }}>
              <Row label="الإجمالي" value={afterDiscount + tax} />
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.2rem', justifyContent: 'flex-end', alignItems: 'center' }}>
          {!ready && <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>اختر عميلًا وأضِف بندًا بسعر</span>}
          <button onClick={onClose} className="btn btn-sm btn-ghost">إلغاء</button>
          <button onClick={submit} className="btn btn-sm" disabled={busy || !ready}>
            {busy ? 'جارٍ الحفظ…' : 'حفظ كمسوّدة'}
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
  const [creating, setCreating] = useState(false)
  const seg = filters.seg ?? 'all'

  return (
    <AppShell heading="الفواتير">
      <Head title="الفواتير" />
      <ListHead
        eyebrow="المالية"
        title="الفواتير"
        sub="مطالبات العملاء وتحصيلها — الطرف المقابل لمستحقات المبدعين"
        actions={canCreate ? (
          <button onClick={() => setCreating(true)} className="btn btn-sm btn-primary">
            <Icon name="file-text" size={15} /> فاتورة جديدة
          </button>
        ) : undefined}
      />

      {creating && <NewInvoiceModal options={options} taxRateBp={defaultTaxRateBp} onClose={() => setCreating(false)} />}

      <div className="ih-kpis">
        <Kpi label="قيد التحصيل" icon="wallet" value={sarShort(summary.outstandingMinor)}
          sub={`${summary.open} فاتورة مفتوحة`} tone={summary.outstandingMinor ? 'warning' : undefined} />
        <Kpi label="المحصَّل" icon="wallet" value={sarShort(summary.collectedMinor)} sub={`${summary.paid} مدفوعة`} />
        <Kpi label="مسودات" icon="file-text" value={summary.draft.toLocaleString('en-US')} sub="لم تُصدَر بعد" />
        <Kpi label="الإجمالي" icon="bar-chart-3" value={summary.total.toLocaleString('en-US')} sub="كل الفواتير" />
      </div>

      <div className="ih-filterbar" style={{ marginBottom: '1rem', gap: '.4rem', flexWrap: 'wrap' }}>
        {SEGMENTS.map(([k, label]) => (
          <button key={k} onClick={() => router.get(u('/invoices'), k === 'all' ? {} : { seg: k }, { preserveState: true, replace: true })}
            className={`btn btn-sm${seg === k ? '' : ' btn-outline'}`}>{label}</button>
        ))}
      </div>

      {invoices.data.length === 0 ? (
        <div className="ih-empty"><div className="ih-empty__inner">
          <span className="ih-empty__icon"><Icon name="file-text" size={26} /></span>
          <div className="ih-empty__title">لا فواتير بعد</div>
          <div className="ih-empty__text">
            أنشئ فاتورة من حملة فتُقترح بنودها من مخرجاتها المسجّلة، أو ابدأ بفاتورة فارغة.
          </div>
          {canCreate && <button onClick={() => setCreating(true)} className="btn btn-sm">فاتورة جديدة</button>}
        </div></div>
      ) : (
        <div className="ih-dt-wrap"><div className="ih-dt-scroll">
          <table className="ih-dt">
            <thead><tr>
              <th>الرقم</th><th>العميل</th><th>الحملة</th><th>الإجمالي</th>
              <th>المتبقّي</th><th>الاستحقاق</th><th>الحالة</th><th></th>
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
                  <td><a href={u(`/invoices/${i.id}`)} className="btn btn-xs btn-outline">فتح</a></td>
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
