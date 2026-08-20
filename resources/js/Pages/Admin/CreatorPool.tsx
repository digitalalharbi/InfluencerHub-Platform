import { Head, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { adminNav } from '@/lib/nav'
import { Kpi, ListHead, numFmt } from '@/Components/ui'
import { Icon } from '@/Components/Icon'
import { Pagination, type Paginated } from '@/Components/Pagination'
import { u } from '@/lib/href'

interface Row {
  id: number; name: string; platform: string; platformLabel: string
  accountUrl: string | null; phone: string | null; followers: number | null
  tier: string | null; gender: string | null; categories: string[]
  costPost: number | null; costCoverage: number | null
  sellPost: number | null; sellCoverage: number | null
  showsFace: boolean | null
  region: string | null; city: string | null; rating: string | null
  likes: number | null; store: string | null; sourceType: string
  matchScore: number | null; matchReasons: string[]; matchFlags: string[]
}
interface Facets {
  total: number
  platforms: Record<string, number>
  sources: Record<string, number>
  tiers: Record<string, number>
  regions: string[]
  reach: number
  priced: number
  contactable: number
}
interface ClientOption { id: number; name: string }
interface Props {
  matching: boolean
  pool: Paginated<Row>
  clients: ClientOption[]
  filters: { platform?: string; source?: string; tier?: string; region?: string; min_followers?: string; q?: string; match_categories?: string; budget_riyals?: string }
  facets: Facets
}

const fnum = (n: number | null): string => {
  if (n == null) return '—'
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M'
  if (n >= 1000) return Math.round(n / 1000) + 'K'
  return n.toLocaleString('en-US')
}
const sar = (n: number | null): string => (n == null ? '—' : n.toLocaleString('en-US'))

const TIER_COLOR: Record<string, string> = { A: 'var(--ih-primary)', B: 'var(--ih-accent-600)', C: 'var(--ih-gray-500)' }
const PLATFORM_TONE: Record<string, string> = {
  snapchat: '#F5C518', tiktok: '#EE1D52', instagram: '#C13584', youtube: '#FF0000', x: '#1DA1F2', linkedin: '#0A66C2',
}

/**
 * قاعدة مبدعي مدير النظام — لمدير النظام وحده (محميّة بوسيط system_admin).
 * عرض احترافي: بطاقات مؤشّرات + شرائح تصنيف + شريط بحث موحّد + جدول/بطاقات جوال،
 * مع محرّك ترشيح بالملاءمة وبيانات الحجز الكاملة وزرّ حذف كامل قبل أي استعراض.
 */
export default function CreatorPool({ matching, pool, filters, facets, clients }: Props) {
  const { errors } = usePage().props as { errors?: Record<string, string> }
  const [q, setQ] = useState(filters.q ?? '')
  const [cats, setCats] = useState(filters.match_categories ?? '')
  const [budget, setBudget] = useState(filters.budget_riyals ?? '')
  const [confirm, setConfirm] = useState('')
  const [danger, setDanger] = useState(false)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [transferOpen, setTransferOpen] = useState(false)
  const [clientId, setClientId] = useState('')
  const first = useRef(true)

  const toggle = (id: number) => setSelected((prev) => {
    const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n
  })
  const transfer = () => {
    if (!clientId || selected.size === 0) return
    router.post(u('/creator-pool/transfer'),
      { client_id: clientId, pool_ids: [...selected] },
      { onSuccess: () => { setSelected(new Set()); setTransferOpen(false); setClientId('') } })
  }

  const clean = (obj: Record<string, string | undefined>) =>
    Object.fromEntries(Object.entries(obj).filter(([, v]) => v != null && v !== ''))
  const apply = (patch: Record<string, string | undefined>) =>
    router.get(u('/creator-pool'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true })

  // بحث مع تأخير (debounced) — يطابق نمط بقية النظام
  useEffect(() => {
    if (first.current) { first.current = false; return }
    const t = setTimeout(() => apply({ q: q || undefined }), 350)
    return () => clearTimeout(t)
  }, [q])

  const purge = () => {
    if (confirm !== 'حذف') return
    router.post(u('/creator-pool/purge'), { confirm }, { onFinish: () => { setConfirm(''); setDanger(false) } })
  }

  const hasFilters = !!(filters.platform || filters.source || filters.tier || filters.region || filters.min_followers || filters.q || filters.match_categories || filters.budget_riyals)
  const celeb = facets.sources['celebrity'] ?? 0
  const ugc = facets.sources['ugc'] ?? 0

  // شرائح المنصّة (مصدر الحقيقة: facets)
  const platformChips: [string, string, number][] = [
    ['', 'كل المنصّات', facets.total],
    ...Object.entries(facets.platforms).map(([p, c]) => [p, p, c] as [string, string, number]),
  ]

  return (
    <AppShell heading="قاعدة المبدعين" nav={adminNav} portal="admin"
      wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="قاعدة المبدعين" />

      <ListHead eyebrow="مدير النظام · خاص" title="قاعدة المبدعين"
        sub="قاعدة ترشيح شاملة عبر المنصّات — بيانات الحجز والتواصل والتسعير، مرئية لك وحدك."
        actions={<a href={u('/shortlisting')} className="btn btn-sm btn-primary"><Icon name="clipboard-check" size={15} /> ترشيح ذكيّ</a>} />

      {/* بطاقات المؤشّرات */}
      <div className="ih-kpis">
        <Kpi label="إجمالي المبدعين" icon="users" value={numFmt(facets.total)}
          sub={`${numFmt(celeb)} مشاهير · ${numFmt(ugc)} UGC`} />
        <Kpi label="الوصول الإجمالي" icon="trending-up" tone="accent" value={fnum(facets.reach)}
          sub="مجموع المتابعين عبر القاعدة" />
        <Kpi label="بأسعار حجز" icon="tag" tone="success" value={numFmt(facets.priced)}
          sub={`${Math.round((facets.priced / Math.max(1, facets.total)) * 100)}٪ من القاعدة مسعّرة`} />
        <Kpi label="قابلون للتواصل" icon="phone" tone="warning" value={numFmt(facets.contactable)}
          sub="لديهم جوّال محفوظ" />
      </div>

      {/* شرائح المنصّة */}
      <div className="ih-chips" style={{ marginBottom: '.7rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {platformChips.map(([key, label, count]) => (
          <button key={key || 'all'} onClick={() => apply({ platform: key || undefined })}
            className={`ih-chip${(filters.platform ?? '') === key ? ' active' : ''}`}>
            {label} <span className="ih-chip__count">{count}</span>
          </button>
        ))}
      </div>

      {/* شرائح المصدر + التصنيف */}
      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        <button onClick={() => apply({ source: undefined })} className={`ih-chip${!filters.source ? ' active' : ''}`}>الكل</button>
        <button onClick={() => apply({ source: 'celebrity' })} className={`ih-chip${filters.source === 'celebrity' ? ' active' : ''}`}>مشاهير <span className="ih-chip__count">{celeb}</span></button>
        <button onClick={() => apply({ source: 'ugc' })} className={`ih-chip${filters.source === 'ugc' ? ' active' : ''}`}>UGC <span className="ih-chip__count">{ugc}</span></button>
        <span style={{ width: 1, alignSelf: 'stretch', background: 'var(--ih-border)', margin: '0 .3rem' }} />
        {(['A', 'B', 'C'] as const).map((t) => (
          <button key={t} onClick={() => apply({ tier: filters.tier === t ? undefined : t })}
            className={`ih-chip${filters.tier === t ? ' active' : ''}`}>فئة {t} <span className="ih-chip__count">{facets.tiers[t] ?? 0}</span></button>
        ))}
      </div>

      {/* البحث والفلاتر */}
      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالاسم أو المجال أو المدينة…" />
        </label>
        <select className="field" style={{ maxWidth: 150 }} value={filters.region ?? ''} onChange={(e) => apply({ region: e.target.value || undefined })}>
          <option value="">كل المناطق</option>
          {facets.regions.map((rg) => <option key={rg} value={rg}>{rg}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 130 }} value={filters.min_followers ?? ''} onChange={(e) => apply({ min_followers: e.target.value || undefined })}>
          <option value="">أي متابعين</option>
          <option value="10000">≥ 10K</option>
          <option value="100000">≥ 100K</option>
          <option value="500000">≥ 500K</option>
          <option value="1000000">≥ 1M</option>
        </select>
        {hasFilters && <button className="btn btn-sm btn-ghost" onClick={() => router.get(u('/creator-pool'))}>مسح</button>}
      </div>

      {/* محرّك الترشيح: معايير الحملة → ترتيب بالملاءمة */}
      <div className="ih-matchbar">
        <span className="ih-matchbar__label"><Icon name="sparkles" size={15} /> محرّك الترشيح</span>
        <input className="field" style={{ minWidth: 170 }} placeholder="مجالات (بفواصل): رياضة، عناية"
          value={cats} onChange={(e) => setCats(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && apply({ match_categories: cats || undefined })} />
        <input className="field" type="number" style={{ maxWidth: 140 }} placeholder="الميزانية ر.س"
          value={budget} onChange={(e) => setBudget(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && apply({ budget_riyals: budget || undefined })} />
        <button className="btn btn-sm" onClick={() => apply({ match_categories: cats || undefined, budget_riyals: budget || undefined })}>رتّب بالملاءمة</button>
        <span className="ih-matchbar__hint">
          {matching ? '✓ مُرتَّب بالملاءمة الآن' : 'أدخِل مجالًا أو ميزانية (أو اختر منصّة/متابعين) للترتيب'}
        </span>
      </div>

      {/* شريط التحويل — يظهر عند الاختيار */}
      {selected.size > 0 && (
        <div className="ih-selbar">
          <b style={{ color: 'var(--ih-primary-700)' }}>{selected.size} مختار</b>
          <button className="btn btn-sm" onClick={() => setTransferOpen(true)}><Icon name="share" size={14} /> تحويل إلى عميل…</button>
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
            <label style={{ display: 'grid', gap: '.3rem' }}>
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
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="users" size={26} /></span>
          <div className="ih-empty__title">{facets.total === 0 ? 'القاعدة فارغة' : 'لا نتائج'}</div>
          <div className="ih-empty__text">{facets.total === 0 ? 'حُذفت البيانات أو لم تُستورَد بعد.' : 'لا مبدعين مطابقين للفلاتر — جرّب توسيعها.'}</div>
        </div>
      ) : (
        <>
          {/* جدول سطح المكتب */}
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr>
                  <th style={{ width: 34 }}></th>
                  <th>المبدع</th><th>الفئة</th><th>المنصّة</th><th>المتابعون</th><th>المجالات</th>
                  {matching && <th>الملاءمة</th>}
                  <th>تكلفة/بيع (تغطية)</th><th>التواصل</th><th>الموقع</th><th></th>
                </tr></thead>
                <tbody>
                  {pool.data.map((c) => (
                    <tr key={c.id} className={selected.has(c.id) ? 'is-selected' : ''}>
                      <td>
                        <input type="checkbox" checked={selected.has(c.id)} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} />
                      </td>
                      <td>
                        <div className="ih-idc">
                          <span className="ih-idc__av ih-idc__av--round" style={c.tier ? { background: (TIER_COLOR[c.tier] ?? TIER_COLOR.C) + '22', color: TIER_COLOR[c.tier] ?? TIER_COLOR.C } : undefined}>{c.name.slice(0, 1)}</span>
                          <span className="ih-idc__main">
                            <span className="ih-idc__name">{c.name}{c.sourceType === 'ugc' && <span className="badge" style={{ background: 'var(--ih-accent-soft, #FDF2FA)', color: 'var(--ih-accent-600, #C13584)', fontSize: '.56rem' }}>UGC</span>}</span>
                            {c.rating && <span className="ih-idc__sub">★ {c.rating}</span>}
                          </span>
                        </div>
                      </td>
                      <td>{c.tier ? <span className="badge" style={{ background: (TIER_COLOR[c.tier] ?? TIER_COLOR.C) + '1f', color: TIER_COLOR[c.tier] ?? TIER_COLOR.C, fontWeight: 800 }}>{c.tier}</span> : '—'}</td>
                      <td><span className="ih-tag" style={{ color: PLATFORM_TONE[c.platform] }}>{c.platformLabel}</span></td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{fnum(c.followers)}</td>
                      <td>
                        <div style={{ display: 'flex', gap: '.25rem', flexWrap: 'wrap', maxWidth: 180 }}>
                          {c.categories.slice(0, 2).map((cat, i) => <span key={i} className="ih-tag" style={{ fontSize: '.6rem' }}>{cat}</span>)}
                          {c.categories.length > 2 && <span className="ih-tag" style={{ fontSize: '.6rem' }}>+{c.categories.length - 2}</span>}
                        </div>
                      </td>
                      {matching && (
                        <td>
                          {c.matchScore != null ? (
                            <span className="badge" title={c.matchReasons.join(' · ')}
                              style={{ background: c.matchScore >= 60 ? 'var(--ih-success-soft, #ECFDF3)' : 'var(--ih-warning-soft, #FFFAEB)', color: c.matchScore >= 60 ? 'var(--ih-success-700, #067647)' : 'var(--ih-warning-ink, #B54708)', fontWeight: 800 }}>{c.matchScore}٪</span>
                          ) : '—'}
                        </td>
                      )}
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right', whiteSpace: 'nowrap' }}>
                        {sar(c.costCoverage)} / {sar(c.sellCoverage)}
                      </td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontFamily: 'var(--ih-font-mono, monospace)', fontSize: '.76rem' }}>{c.phone ?? '—'}</td>
                      <td style={{ fontSize: '.76rem', color: 'var(--ih-text-secondary)' }}>{[c.city, c.region].filter(Boolean).join(' · ') || '—'}</td>
                      <td style={{ textAlign: 'end' }}>
                        {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline">الحساب ↗</a>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot">
                <span>{pool.total.toLocaleString('en-US')} مبدع{hasFilters ? ' · مُرشَّح' : ''}{matching ? ' · مُرتَّب بالملاءمة' : ''}</span>
                <Pagination links={pool.links} />
              </div>
            </div>
          </div>

          {/* بطاقات الجوال */}
          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {pool.data.map((c) => (
                <div key={c.id} className="ih-mcard" style={selected.has(c.id) ? { borderColor: 'var(--ih-primary)' } : undefined}>
                  <div className="ih-mcard__top">
                    <input type="checkbox" checked={selected.has(c.id)} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} style={{ marginTop: 4 }} />
                    <span className="ih-idc__av ih-idc__av--round" style={{ width: 42, height: 42, ...(c.tier ? { background: (TIER_COLOR[c.tier] ?? TIER_COLOR.C) + '22', color: TIER_COLOR[c.tier] ?? TIER_COLOR.C } : {}) }}>{c.name.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{c.name}
                        {c.tier && <span className="badge" style={{ background: (TIER_COLOR[c.tier] ?? TIER_COLOR.C) + '1f', color: TIER_COLOR[c.tier] ?? TIER_COLOR.C, fontSize: '.6rem', fontWeight: 800 }}>{c.tier}</span>}
                        {c.sourceType === 'ugc' && <span className="badge" style={{ background: 'var(--ih-accent-soft, #FDF2FA)', color: 'var(--ih-accent-600, #C13584)', fontSize: '.56rem' }}>UGC</span>}
                      </div>
                      <div className="ih-idc__sub" style={{ direction: 'ltr', textAlign: 'right' }}>{c.platformLabel} · {fnum(c.followers)}</div>
                    </div>
                    {matching && c.matchScore != null && (
                      <span className="badge" style={{ background: c.matchScore >= 60 ? 'var(--ih-success-soft, #ECFDF3)' : 'var(--ih-warning-soft, #FFFAEB)', color: c.matchScore >= 60 ? 'var(--ih-success-700, #067647)' : 'var(--ih-warning-ink, #B54708)', fontWeight: 800 }}>{c.matchScore}٪</span>
                    )}
                  </div>
                  {c.categories.length > 0 && (
                    <div style={{ display: 'flex', gap: '.25rem', flexWrap: 'wrap', margin: '.5rem 0' }}>
                      {c.categories.slice(0, 3).map((cat, i) => <span key={i} className="ih-tag" style={{ fontSize: '.62rem' }}>{cat}</span>)}
                    </div>
                  )}
                  <div className="ih-mcard__grid">
                    <div><span className="ih-mcard__lbl">تغطية — تكلفة/بيع</span><span className="ih-mcard__val" style={{ direction: 'ltr' }}>{sar(c.costCoverage)} / {sar(c.sellCoverage)} ر.س</span></div>
                    <div><span className="ih-mcard__lbl">التواصل</span><span className="ih-mcard__val" style={{ direction: 'ltr' }}>{c.phone ?? '—'}</span></div>
                    <div><span className="ih-mcard__lbl">الموقع</span><span className="ih-mcard__val">{[c.city, c.region].filter(Boolean).join(' · ') || '—'}</span></div>
                  </div>
                  {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline" style={{ marginTop: '.5rem' }}>فتح الحساب ↗</a>}
                </div>
              ))}
            </div>
            <Pagination links={pool.links} />
          </div>
        </>
      )}

      {/* منطقة الخطر: حذف كامل قبل استعراض النظام */}
      <div style={{ marginTop: '2rem', border: '1px solid var(--ih-danger-200, #FECDCA)', borderRadius: 12, padding: '1.1rem 1.25rem', background: 'var(--ih-danger-50, #FEF3F2)' }}>
        <h3 style={{ margin: '0 0 .4rem', color: 'var(--ih-danger-700, #B42318)', display: 'flex', alignItems: 'center', gap: '.4rem' }}><Icon name="alert-triangle" size={17} /> حذف القاعدة بالكامل</h3>
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
