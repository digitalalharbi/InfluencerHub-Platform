import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { adminNav } from '@/lib/nav'
import { ListHead, Sec, StatusBadge, Kpi, numFmt } from '@/Components/ui'
import { Pagination, type Paginated } from '@/Components/Pagination'
import { u } from '@/lib/href'

interface Row {
  id: number
  reference: string
  type: string
  typeLabel: string
  contactName: string
  email: string
  phone: string | null
  company: string
  website: string | null
  teamSize: string | null
  monthlyCampaigns: string | null
  notes: string | null
  status: string
  statusLabel: string
  statusTone: string
  reviewNotes: string | null
  reviewedAt: string | null
  createdAt: string | null
  isDecided: boolean
}

interface Props {
  requests: Paginated<Row>
  filters: { status: string; type: string | null }
  counts: Record<string, number>
  statusLabels: Record<string, string>
}

/**
 * مراجعة طلبات فتح الحساب.
 * الرفض يفرض سببًا: قرار بلا سبب لا يُفيد صاحب الطلب ولا المراجع بعدك.
 */
export default function SignupReview({ requests, filters, counts, statusLabels }: Props) {
  const { errors } = usePage().props as { errors?: Record<string, string> }
  const [openId, setOpenId] = useState<number | null>(null)
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  const decide = (row: Row, action: 'approve' | 'reject' | 'contacted') => {
    if (action === 'reject' && !note.trim()) return
    setBusy(true)
    router.post(
      u(`/signup-requests/${row.id}/${action}`),
      { review_notes: note },
      {
        preserveScroll: true,
        onFinish: () => {
          setBusy(false)
          setNote('')
          setOpenId(null)
        },
      },
    )
  }

  const setStatus = (status: string) =>
    router.get(u('/signup-requests'), { status }, { preserveState: true, replace: true })

  return (
    <AppShell
      heading="طلبات فتح الحساب"
      nav={adminNav}
      portal="admin"
      wsName="إدارة المنصّة"
      wsPlan="مدير النظام"
      brand="InfluencerHub"
    >
      <Head title="طلبات فتح الحساب" />
      <ListHead
        eyebrow="المنصّة · الانضمام"
        title="طلبات فتح الحساب"
        sub="طلبات المنشآت الراغبة في الانضمام للمنصّة — راجِعها وتواصَل واعتمِد أو ارفُض."
      />

      <div className="ih-kpis">
        <Kpi label="جديدة" icon="inbox" tone={counts.submitted ? 'warning' : 'success'}
          value={numFmt(counts.submitted ?? 0)} sub={counts.submitted ? 'بانتظار المراجعة' : 'لا جديد'} />
        <Kpi label="جرى التواصل" icon="phone" tone="accent" value={numFmt(counts.contacted ?? 0)} sub="قيد المتابعة" />
        <Kpi label="معتمَدة" icon="shield-check" tone="success" value={numFmt(counts.approved ?? 0)} sub="حُوّلت لحساب" />
        <Kpi label="إجمالي الطلبات" icon="clipboard-check" value={numFmt(counts.total ?? 0)} sub={`${counts.rejected ?? 0} مرفوضة`} />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        <button onClick={() => setStatus('all')} className={`ih-chip${filters.status === 'all' ? ' active' : ''}`}>الكل <span className="ih-chip__count">{counts.total ?? 0}</span></button>
        {Object.entries(statusLabels).map(([key, label]) => (
          <button key={key} onClick={() => setStatus(key)} className={`ih-chip${filters.status === key ? ' active' : ''}`}>
            {label}{counts[key] !== undefined ? <span className="ih-chip__count">{counts[key]}</span> : null}
          </button>
        ))}
      </div>

      {errors?.review && (
        <div className="pub-error-banner" style={{ marginBottom: '1rem' }}>
          {errors.review}
        </div>
      )}

      {requests.data.length === 0 ? (
        <Sec title="لا طلبات">
          <p style={{ color: 'var(--ih-text-secondary)' }}>لا طلبات في هذا التصنيف.</p>
        </Sec>
      ) : (
        requests.data.map((row) => (
          <Sec
            key={row.id}
            title={`${row.company} — ${row.typeLabel} · ${row.reference}`}
          >
            <p style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', marginBlockEnd: '.5rem' }}>
              وصل {row.createdAt}
            </p>
            <div style={{ marginBottom: '.75rem' }}>
              <StatusBadge tone={row.statusTone} label={row.statusLabel} />
            </div>

            <dl className="ih-facts">
              <div>
                <dt>مقدّم الطلب</dt>
                <dd>{row.contactName}</dd>
              </div>
              <div>
                <dt>البريد</dt>
                <dd style={{ direction: 'ltr' }}>{row.email}</dd>
              </div>
              {row.phone && (
                <div>
                  <dt>الجوال</dt>
                  <dd style={{ direction: 'ltr' }}>{row.phone}</dd>
                </div>
              )}
              {row.website && (
                <div>
                  <dt>الموقع</dt>
                  <dd style={{ direction: 'ltr' }}>{row.website}</dd>
                </div>
              )}
              {row.teamSize && (
                <div>
                  <dt>حجم الفريق</dt>
                  <dd>{row.teamSize}</dd>
                </div>
              )}
              {row.monthlyCampaigns && (
                <div>
                  <dt>حملات شهريًّا</dt>
                  <dd>{row.monthlyCampaigns}</dd>
                </div>
              )}
            </dl>

            {row.notes && (
              <p style={{ fontSize: '.875rem', color: 'var(--ih-text-secondary)', marginBlock: '.75rem' }}>
                {row.notes}
              </p>
            )}

            {row.isDecided ? (
              <div style={{ fontSize: '.85rem', color: 'var(--ih-text-secondary)' }}>
                <b>{row.statusLabel}</b> · {row.reviewedAt}
                {row.reviewNotes && <p style={{ marginBlockStart: '.35rem' }}>{row.reviewNotes}</p>}
              </div>
            ) : openId === row.id ? (
              <div style={{ display: 'grid', gap: '.75rem' }}>
                <label className="pub-field">
                  <span>ملاحظة المراجعة (إلزامية عند الرفض)</span>
                  <textarea
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    className="field"
                    rows={3}
                    autoFocus
                  />
                </label>
                <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center', flexWrap: 'wrap' }}>
                  <button onClick={() => decide(row, 'approve')} className="btn btn-sm" disabled={busy}>
                    اعتماد وإرسال رابط الإكمال
                  </button>
                  <button
                    onClick={() => decide(row, 'contacted')}
                    className="btn btn-sm btn-outline"
                    disabled={busy}
                  >
                    سجّل التواصل
                  </button>
                  <button
                    onClick={() => decide(row, 'reject')}
                    className="btn btn-sm btn-outline"
                    disabled={busy || !note.trim()}
                  >
                    رفض
                  </button>
                  <button onClick={() => setOpenId(null)} className="btn btn-sm btn-ghost">
                    إلغاء
                  </button>
                  {!note.trim() && (
                    <span style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>
                      اكتب سببًا ليُصبح الرفض متاحًا
                    </span>
                  )}
                </div>
              </div>
            ) : (
              <button
                onClick={() => {
                  setOpenId(row.id)
                  setNote('')
                }}
                className="btn btn-sm btn-outline"
              >
                مراجعة الطلب
              </button>
            )}
          </Sec>
        ))
      )}

      <Pagination links={requests.links} />
    </AppShell>
  )
}
