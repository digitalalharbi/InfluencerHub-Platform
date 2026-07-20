import { useForm } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'
import { u } from '@/lib/href'

type Rel = {
  id: number
  agency: string
  status: string
  services: string[]
  startedAt: string | null
  endedAt: string | null
  isLive: boolean
}

const SERVICE_LABELS: Record<string, string> = {
  campaigns: 'الحملات',
  shortlists: 'الترشيحات',
  content: 'المحتوى',
  contracts: 'العقود',
  finance: 'المالية',
  reports: 'التقارير',
  ads: 'الإعلانات المدفوعة',
  commerce: 'التجارة',
  analytics: 'التحليلات',
}

const STATUS_LABELS: Record<string, string> = {
  pending: 'بانتظار موافقة الوكالة',
  active: 'فعّال',
  declined: 'مرفوض',
  suspended: 'موقوف',
  ended: 'منتهٍ',
}

/**
 * الوكالات المفوَّضة.
 *
 * **النطاق ظاهر لكل وكالة**، وما لم يُمنح يُعرض مرفوعًا عنه صراحةً. تفويضٌ بلا
 * نطاق ظاهر يُقرأ وصولًا شاملًا، فيُمنح ولا يُراجَع — وهذه الصفحة موجودة لتمنع
 * ذلك بالضبط.
 *
 * ولا خانة اختيار محدَّدة مسبقًا في نموذج الدعوة: كل خدمة تُمنح بفعلٍ واعٍ.
 */
export default function Agencies({
  brand,
  items,
  available,
  allServices,
  canManage,
}: {
  brand: { name: string }
  items: Rel[]
  available: { id: number; name: string }[]
  allServices: string[]
  canManage: boolean
}) {
  const [inviting, setInviting] = useState(false)

  const invite = useForm<{ agency_tenant_id: string; services: string[] }>({
    agency_tenant_id: '',
    services: [],
  })

  const toggle = (s: string) =>
    invite.setData(
      'services',
      invite.data.services.includes(s)
        ? invite.data.services.filter((x) => x !== s)
        : [...invite.data.services, s],
    )

  return (
    <AppShell heading="الوكالات" nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      <section className="ih-sec">
        <div className="ih-listhead">
          <h2>التفويضات</h2>
          {canManage && !inviting && available.length > 0 && (
            <button type="button" className="btn btn-primary btn-sm" onClick={() => setInviting(true)}>
              فوّض وكالة
            </button>
          )}
        </div>

        <p className="ih-mline">
          التفويض ليس ملكية: علامتك تبقى لك، والوصول ينتهي متى سحبته — وكل ما أُنتج
          تحته يبقى في مساحتك.
        </p>

        {inviting && canManage && (
          <form
            className="ih-panel"
            onSubmit={(e) => {
              e.preventDefault()
              invite.post(u('/agencies/invite'), {
                onSuccess: () => {
                  invite.reset()
                  setInviting(false)
                },
              })
            }}
          >
            <label className="pub-field">
              <span>الوكالة</span>
              <select
                className="field"
                value={invite.data.agency_tenant_id}
                onChange={(e) => invite.setData('agency_tenant_id', e.target.value)}
              >
                <option value="">اختر وكالة…</option>
                {available.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name}
                  </option>
                ))}
              </select>
              {invite.errors.agency_tenant_id && (
                <em className="pub-field-error">{invite.errors.agency_tenant_id}</em>
              )}
            </label>

            <fieldset style={{ border: 0, padding: 0, margin: '1rem 0' }}>
              <legend>
                نطاق الخدمات — <b>ما لا تختاره لا يُفوَّض</b>
              </legend>

              <div className="ih-chips">
                {allServices.map((s) => (
                  <label key={s} className="ih-chip" style={{ cursor: 'pointer' }}>
                    <input
                      type="checkbox"
                      checked={invite.data.services.includes(s)}
                      onChange={() => toggle(s)}
                    />{' '}
                    {SERVICE_LABELS[s] ?? s}
                  </label>
                ))}
              </div>
              {invite.errors.services && <em className="pub-field-error">{invite.errors.services}</em>}
            </fieldset>

            <div style={{ display: 'flex', gap: '.5rem' }}>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={
                  invite.processing || !invite.data.agency_tenant_id || invite.data.services.length === 0
                }
              >
                {invite.processing ? 'جارٍ الإرسال…' : 'أرسل الدعوة'}
              </button>
              <button type="button" className="btn btn-ghost" onClick={() => setInviting(false)}>
                إلغاء
              </button>
            </div>
          </form>
        )}

        {items.length === 0 ? (
          <div className="ih-empty">
            <strong>لا وكالة مفوَّضة</strong>
            <p>
              تُشغّل علامتك بنفسك الآن. يمكنك تفويض وكالة بنطاق تحدّده — وتسحبه متى شئت
              بلا فقد أيّ بيانات.
            </p>
          </div>
        ) : (
          <ul className="ih-mlist">
            {items.map((rel) => (
              <RelationshipCard key={rel.id} rel={rel} allServices={allServices} canManage={canManage} />
            ))}
          </ul>
        )}
      </section>
    </AppShell>
  )
}

function RelationshipCard({
  rel,
  allServices,
  canManage,
}: {
  rel: Rel
  allServices: string[]
  canManage: boolean
}) {
  const granted = new Set(rel.services)
  const revoke = useForm({ reason: '' })

  return (
    <li className="ih-mcard" style={{ opacity: rel.isLive ? 1 : 0.65 }}>
      <div className="ih-mcard__top" style={{ justifyContent: 'space-between' }}>
        <strong>{rel.agency}</strong>
        <span className="badge">{STATUS_LABELS[rel.status] ?? rel.status}</span>
      </div>

      <p className="ih-delta">
        {rel.startedAt ? `منذ ${rel.startedAt}` : ''}
        {rel.endedAt ? ` · انتهى ${rel.endedAt}` : ''}
      </p>

      <p className="ih-mline">النطاق المفوَّض:</p>
      <ul className="ih-chips">
        {allServices.map((s) => (
          <li key={s} className="ih-chip" style={{ opacity: granted.has(s) ? 1 : 0.45 }}>
            {granted.has(s) ? '✓ ' : '✕ '}
            {SERVICE_LABELS[s] ?? s}
          </li>
        ))}
      </ul>

      {canManage && rel.isLive && (
        <button
          type="button"
          className="btn btn-danger btn-sm"
          disabled={revoke.processing}
          onClick={() => revoke.post(u(`/agencies/${rel.id}/revoke`))}
        >
          {revoke.processing ? 'جارٍ الإنهاء…' : 'أنهِ التفويض'}
        </button>
      )}
    </li>
  )
}
