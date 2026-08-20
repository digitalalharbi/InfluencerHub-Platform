import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { adminNav } from '@/lib/nav'
import { ListHead, Sec } from '@/Components/ui'
import { Pagination, type Paginated } from '@/Components/Pagination'
import { u } from '@/lib/href'

interface Row {
  id: number; name: string; platform: string; platformLabel: string
  accountUrl: string | null; phone: string | null; followers: number | null
  tier: string | null; gender: string | null; categories: string[]
  pricePost: number | null; priceCoverage: number | null; showsFace: boolean | null
  region: string | null; city: string | null; rating: string | null
  likes: number | null; store: string | null; sourceType: string
}
interface Facets {
  total: number
  platforms: Record<string, number>
  sources: Record<string, number>
  regions: string[]
}
interface ClientOption { id: number; name: string }
interface Props {
  pool: Paginated<Row>
  clients: ClientOption[]
  filters: { platform?: string; source?: string; tier?: string; region?: string; min_followers?: string; q?: string }
  facets: Facets
}

const fmt = (n: number | null) =>
  n == null ? '—' : n >= 1000 ? (n / 1000).toFixed(n >= 10000 ? 0 : 1).replace('.0', '') + 'K' : String(n)

/**
 * قاعدة مبدعي مدير النظام — لمدير النظام وحده (محميّة بوسيط system_admin).
 * للاستخدام الشخصي للترشيح والتحويل، مع زرّ حذف كامل قبل أي استعراض للنظام.
 */
export default function CreatorPool({ pool, filters, facets, clients }: Props) {
  const { errors } = usePage().props as { errors?: Record<string, string> }
  const [q, setQ] = useState(filters.q ?? '')
  const [confirm, setConfirm] = useState('')
  const [danger, setDanger] = useState(false)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [transferOpen, setTransferOpen] = useState(false)
  const [clientId, setClientId] = useState('')

  const toggle = (id: number) => setSelected((prev) => {
    const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n
  })
  const transfer = () => {
    if (!clientId || selected.size === 0) return
    router.post(u('/creator-pool/transfer'),
      { client_id: clientId, pool_ids: [...selected] },
      { onSuccess: () => { setSelected(new Set()); setTransferOpen(false); setClientId('') } })
  }

  const apply = (patch: Record<string, string | undefined>) =>
    router.get(u('/creator-pool'), { ...filters, ...patch }, { preserveState: true, replace: true })

  const purge = () => {
    if (confirm !== 'حذف') return
    router.post(u('/creator-pool/purge'), { confirm }, {
      onFinish: () => { setConfirm(''); setDanger(false) },
    })
  }

  return (
    <AppShell heading="قاعدة المبدعين" nav={adminNav} portal="admin"
      wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="قاعدة المبدعين" />
      <ListHead eyebrow="مدير النظام · خاص" title="قاعدة المبدعين"
        sub={`${facets.total.toLocaleString('en-US')} مبدعًا — للترشيح والتحويل إلى العملاء. مرئية لك وحدك.`} />

      {/* الفلاتر */}
      <div className="ih-filterbar" style={{ marginBottom: '1rem', gap: '.5rem', flexWrap: 'wrap', alignItems: 'center' }}>
        <input className="field" style={{ minWidth: 200 }} value={q} placeholder="ابحث بالاسم/المجال/المدينة…"
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && apply({ q: q || undefined })} />
        <select className="field" value={filters.platform ?? ''} onChange={(e) => apply({ platform: e.target.value || undefined })}>
          <option value="">كل المنصّات</option>
          {Object.entries(facets.platforms).map(([p, c]) => <option key={p} value={p}>{p} ({c})</option>)}
        </select>
        <select className="field" value={filters.source ?? ''} onChange={(e) => apply({ source: e.target.value || undefined })}>
          <option value="">الكل</option>
          {Object.entries(facets.sources).map(([s, c]) => <option key={s} value={s}>{s === 'ugc' ? 'UGC' : 'مشاهير'} ({c})</option>)}
        </select>
        <select className="field" value={filters.tier ?? ''} onChange={(e) => apply({ tier: e.target.value || undefined })}>
          <option value="">كل التصنيفات</option>
          {['A', 'B', 'C'].map((t) => <option key={t} value={t}>{t}</option>)}
        </select>
        <select className="field" value={filters.min_followers ?? ''} onChange={(e) => apply({ min_followers: e.target.value || undefined })}>
          <option value="">أي متابعين</option>
          <option value="10000">≥ 10K</option>
          <option value="100000">≥ 100K</option>
          <option value="500000">≥ 500K</option>
          <option value="1000000">≥ 1M</option>
        </select>
        {Object.keys(filters).some((k) => (filters as Record<string, string>)[k]) && (
          <button className="btn btn-sm btn-ghost" onClick={() => router.get(u('/creator-pool'))}>مسح</button>
        )}
      </div>

      {/* شريط التحويل — يظهر عند الاختيار */}
      {selected.size > 0 && (
        <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem', flexWrap: 'wrap',
          background: 'var(--ih-primary-soft)', border: '1px solid var(--ih-primary-300)',
          borderRadius: 10, padding: '.7rem 1rem', marginBottom: '1rem' }}>
          <b style={{ color: 'var(--ih-primary-700)' }}>{selected.size} مختار</b>
          <button className="btn btn-sm" onClick={() => setTransferOpen(true)}>تحويل إلى عميل…</button>
          <button className="btn btn-sm btn-ghost" onClick={() => setSelected(new Set())}>إلغاء الاختيار</button>
        </div>
      )}

      {transferOpen && (
        <div className="ih-modal-backdrop" role="dialog" aria-modal="true" aria-label="تحويل إلى عميل">
          <div className="ih-modal" style={{ maxWidth: 460 }}>
            <h3 style={{ margin: '0 0 .3rem' }}>تحويل {selected.size} مبدعًا إلى عميل</h3>
            <p style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)', marginBlockEnd: '1rem' }}>
              تُنشأ توصية للعميل بنسخة مستقلّة عن القاعدة — بلا الجوّال. يبقى للعميل قرار قبول أو رفض.
            </p>
            <label className="pub-field" style={{ display: 'grid', gap: '.3rem' }}>
              <span style={{ fontSize: '.82rem', fontWeight: 600 }}>العميل</span>
              <select className="field" value={clientId} onChange={(e) => setClientId(e.target.value)} autoFocus>
                <option value="">اختر عميلًا…</option>
                {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </label>
            <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.1rem', justifyContent: 'flex-end' }}>
              <button className="btn btn-sm btn-ghost" onClick={() => setTransferOpen(false)}>إلغاء</button>
              <button className="btn btn-sm" disabled={!clientId} onClick={transfer}>تأكيد التحويل</button>
            </div>
          </div>
        </div>
      )}

      {pool.data.length === 0 ? (
        <Sec title="لا نتائج"><p style={{ color: 'var(--ih-text-secondary)' }}>
          {facets.total === 0 ? 'القاعدة فارغة (حُذفت أو لم تُستورَد بعد).' : 'لا مبدعين مطابقين للفلاتر.'}
        </p></Sec>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))', gap: '.8rem' }}>
          {pool.data.map((c) => (
            <div key={c.id} className="card" style={{ padding: '.9rem 1rem', display: 'grid', gap: '.4rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '.5rem' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '.4rem', cursor: 'pointer' }}>
                  <input type="checkbox" checked={selected.has(c.id)} onChange={() => toggle(c.id)}
                    aria-label={`اختيار ${c.name}`} />
                  <b style={{ fontSize: '.95rem' }}>{c.name}</b>
                </label>
                {c.tier && <span className="badge">{c.tier}</span>}
              </div>
              <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap', fontSize: '.78rem', color: 'var(--ih-text-secondary)' }}>
                <span>{c.platformLabel}</span>
                <span style={{ direction: 'ltr' }}>{fmt(c.followers)} متابع</span>
                {c.sourceType === 'ugc' && <span className="badge" style={{ fontSize: '.6rem' }}>UGC</span>}
              </div>
              {c.categories.length > 0 && (
                <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
                  {c.categories.slice(0, 3).map((cat, i) => (
                    <span key={i} style={{ fontSize: '.7rem', background: 'var(--ih-surface-sunken)', borderRadius: 6, padding: '.1rem .4rem' }}>{cat}</span>
                  ))}
                </div>
              )}
              <div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>
                {[c.city, c.region].filter(Boolean).join(' · ') || '—'}
                {c.pricePost ? ` · منشور ${c.pricePost.toLocaleString('en-US')} ر.س` : ''}
              </div>
              {c.accountUrl && (
                <a href={c.accountUrl} target="_blank" rel="noopener noreferrer"
                  className="btn btn-xs btn-outline" style={{ justifySelf: 'start' }}>فتح الحساب ↗</a>
              )}
            </div>
          ))}
        </div>
      )}

      <Pagination links={pool.links} />

      {/* منطقة الخطر: حذف كامل قبل استعراض النظام */}
      <div style={{ marginTop: '2rem', border: '1px solid var(--ih-danger-200, #FECDCA)', borderRadius: 12, padding: '1.1rem 1.25rem', background: 'var(--ih-danger-50, #FEF3F2)' }}>
        <h3 style={{ margin: '0 0 .4rem', color: 'var(--ih-danger-700, #B42318)' }}>حذف القاعدة بالكامل</h3>
        <p style={{ fontSize: '.85rem', color: 'var(--ih-danger-700, #B42318)', margin: '0 0 .8rem' }}>
          قبل استعراض النظام لأحد، احذف هذه البيانات كليًّا. الحذف نهائي، ويمسح ملفّ الاستيراد أيضًا. لإعادتها تحتاج ملفّ المصدر ثانيةً.
        </p>
        {errors?.confirm && <div className="pub-error-banner" style={{ marginBottom: '.6rem' }}>{errors.confirm}</div>}
        {!danger ? (
          <button className="btn btn-sm btn-danger" onClick={() => setDanger(true)}>حذف كل القاعدة…</button>
        ) : (
          <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center', flexWrap: 'wrap' }}>
            <span style={{ fontSize: '.82rem' }}>اكتب «حذف» للتأكيد:</span>
            <input className="field" style={{ maxWidth: 120 }} value={confirm} onChange={(e) => setConfirm(e.target.value)} autoFocus />
            <button className="btn btn-sm btn-danger" disabled={confirm !== 'حذف'} onClick={purge}>تأكيد الحذف النهائي</button>
            <button className="btn btn-sm btn-ghost" onClick={() => { setDanger(false); setConfirm('') }}>تراجع</button>
          </div>
        )}
      </div>
    </AppShell>
  )
}
