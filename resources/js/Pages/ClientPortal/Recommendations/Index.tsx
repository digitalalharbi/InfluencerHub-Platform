import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { clientNav } from '@/lib/nav'
import { ListHead, Kpi } from '@/Components/ui'
import { Icon } from '@/Components/Icon'
import { Pagination, type Paginated } from '@/Components/Pagination'
import { u } from '@/lib/href'

interface Row {
  id: number; name: string; platformLabel: string; accountUrl: string | null
  followers: number | null; categories: string[]; priceMinor: number | null
  region: string | null; city: string | null; sourceType: string
  status: string; reason: string | null; decidedAt: string | null
}
interface Props {
  clientName: string
  items: Paginated<Row>
  counts: { pending: number; approved: number; rejected: number; total: number }
}

const fnum = (n: number | null): string => {
  if (n == null) return '—'
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M'
  if (n >= 1000) return (n / 1000).toFixed(n >= 100_000 ? 0 : 1).replace('.0', '') + 'K'
  return n.toLocaleString('en-US')
}
const sar = (minor: number | null): string => (minor == null ? '—' : Math.round(minor / 100).toLocaleString('en-US') + ' ر.س')

const STATUS: Record<string, { label: string; bg: string; fg: string }> = {
  recommended: { label: 'بانتظار قرارك', bg: 'var(--ih-warning-soft, #FFFAEB)', fg: 'var(--ih-warning-ink, #B54708)' },
  approved: { label: 'معتمد', bg: 'var(--ih-success-soft, #ECFDF3)', fg: 'var(--ih-success-700, #067647)' },
  rejected: { label: 'مرفوض', bg: 'var(--ih-danger-soft, #FEF3F2)', fg: 'var(--ih-danger-ink, #B42318)' },
}

/**
 * ترشيحات المؤثرين — بوابة العميل. يقبل العميل أو يرفض ما رشّحه مدير النظام.
 * لا يظهر جوّال ولا تكلفة — فقط الاسم والمنصّة والمتابعين والسعر المعروض.
 */
export default function ClientRecommendationsIndex({ clientName, items, counts }: Props) {
  const [rejecting, setRejecting] = useState<Row | null>(null)
  const [reason, setReason] = useState('')

  const decide = (id: number, decision: 'approved' | 'rejected', why?: string) =>
    router.post(u(`/recommendations/${id}/decision`), { decision, reason: why ?? null },
      { preserveScroll: true, onSuccess: () => { setRejecting(null); setReason('') } })

  return (
    <AppShell heading="ترشيحات المؤثرين" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title="ترشيحات المؤثرين" />
      <ListHead eyebrow="بوابة العميل" title="ترشيحات المؤثرين"
        sub="مؤثرون رشّحهم فريقنا لك — اعتمد من يناسبك أو ارفض من لا يناسب." />

      <div className="ih-kpis">
        <Kpi label="بانتظار قرارك" icon="clipboard-check" tone={counts.pending ? 'warning' : 'success'}
          value={counts.pending.toLocaleString('en-US')} sub={counts.pending ? 'يحتاج ردّك' : 'لا شيء معلّق'} />
        <Kpi label="معتمدون" icon="shield-check" tone="success" value={counts.approved.toLocaleString('en-US')} sub="وافقتَ عليهم" />
        <Kpi label="مرفوضون" icon="x" value={counts.rejected.toLocaleString('en-US')} sub="استبعدتَهم" />
        <Kpi label="الإجمالي" icon="star" value={counts.total.toLocaleString('en-US')} sub="كل الترشيحات" />
      </div>

      {items.data.length === 0 ? (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="star" size={26} /></span>
          <div className="ih-empty__title">لا ترشيحات بعد</div>
          <div className="ih-empty__text">سيظهر هنا المؤثرون الذين يرشّحهم فريقنا لحملاتك.</div>
        </div>
      ) : (
        <>
          <div className="ih-recgrid">
            {items.data.map((c) => {
              const st = STATUS[c.status] ?? STATUS.recommended
              const pending = c.status === 'recommended'
              return (
                <div key={c.id} className="ih-gcard" style={{ cursor: 'default' }}>
                  <div className="ih-gcard__top">
                    <span className="badge" style={{ background: st.bg, color: st.fg, fontWeight: 700 }}>{st.label}</span>
                    <span className="ih-tag">{c.platformLabel}</span>
                  </div>
                  <div style={{ fontWeight: 800, fontSize: '.98rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.78rem', color: 'var(--ih-text-secondary)', direction: 'ltr' }}>
                    <span>{fnum(c.followers)} متابع</span>
                    {c.priceMinor != null && <span style={{ fontWeight: 700, color: 'var(--ih-text)' }}>{sar(c.priceMinor)}</span>}
                  </div>
                  {c.categories.length > 0 && (
                    <div style={{ display: 'flex', gap: '.25rem', flexWrap: 'wrap' }}>
                      {c.categories.slice(0, 3).map((cat, i) => <span key={i} className="ih-tag" style={{ fontSize: '.6rem' }}>{cat}</span>)}
                    </div>
                  )}
                  <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>
                    <Icon name="map-pin" size={11} /> {[c.city, c.region].filter(Boolean).join(' · ') || '—'}
                  </div>
                  {c.reason && <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', fontStyle: 'italic' }}>«{c.reason}»</div>}

                  <div style={{ marginTop: 'auto', display: 'flex', gap: '.35rem', paddingTop: '.4rem' }}>
                    {c.accountUrl && (
                      <a href={c.accountUrl} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline" style={{ flex: '0 0 auto' }}>
                        <Icon name="external-link" size={13} />
                      </a>
                    )}
                    {pending ? (
                      <>
                        <button className="btn btn-xs btn-primary" style={{ flex: 1 }} onClick={() => decide(c.id, 'approved')}>اعتماد</button>
                        <button className="btn btn-xs btn-outline" style={{ flex: 1 }} onClick={() => { setRejecting(c); setReason('') }}>رفض</button>
                      </>
                    ) : (
                      <button className="btn btn-xs btn-ghost" style={{ flex: 1 }}
                        onClick={() => decide(c.id, c.status === 'approved' ? 'rejected' : 'approved')}>
                        {c.status === 'approved' ? 'تغيير إلى رفض' : 'تغيير إلى اعتماد'}
                      </button>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
          <div style={{ marginTop: '1rem' }}><Pagination links={items.links} /></div>
        </>
      )}

      {/* نافذة الرفض — سبب اختياري */}
      {rejecting && (
        <div className="modal-backdrop" role="dialog" aria-modal="true" aria-label="رفض الترشيح"
          onClick={(e) => { if (e.target === e.currentTarget) setRejecting(null) }}>
          <div className="modal" style={{ width: 'min(440px, 100%)', padding: '1.3rem' }}>
            <h3 style={{ margin: '0 0 .3rem' }}>رفض «{rejecting.name}»</h3>
            <p style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)', marginBlockEnd: '1rem' }}>
              يمكنك إضافة سبب يساعد فريقنا على ترشيح أنسب (اختياري).
            </p>
            <textarea className="field" rows={3} value={reason} onChange={(e) => setReason(e.target.value)}
              placeholder="مثال: خارج الفئة المستهدفة، أو السعر أعلى من الميزانية…" autoFocus />
            <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.1rem', justifyContent: 'flex-end' }}>
              <button className="btn btn-sm btn-ghost" onClick={() => setRejecting(null)}>إلغاء</button>
              <button className="btn btn-sm btn-danger" onClick={() => decide(rejecting.id, 'rejected', reason || undefined)}>تأكيد الرفض</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  )
}
