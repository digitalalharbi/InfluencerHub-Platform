import { Head, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { adminNav } from '@/lib/nav'
import { Kpi, ListHead, numFmt } from '@/Components/ui'
import { Icon, type IconName } from '@/Components/Icon'
import { Pagination, type Paginated } from '@/Components/Pagination'
import {
  CreatorDetailModal, ScoreRing, TierBadge, PlatformTag, UgcBadge,
  fnum, sar, scoreTone, type PoolCreatorRow,
} from '@/Components/PoolCreator'
import { u } from '@/lib/href'

type Row = PoolCreatorRow
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

type ViewMode = 'grid' | 'list' | 'table'
const VIEWS: [ViewMode, IconName, string][] = [['grid', 'grid', 'بطاقات'], ['list', 'rows', 'قائمة'], ['table', 'table', 'جدول']]

/**
 * قاعدة مبدعي مدير النظام — لمدير النظام وحده (محميّة بوسيط system_admin).
 * عرض احترافي متكامل: مؤشّرات + شرائح + بحث موحّد + ٣ أوضاع (بطاقات افتراضيًّا/
 * قائمة/جدول) + نافذة تفاصيل مركزيّة عند النقر، مع محرّك ترشيح وتحويل وحذف كامل.
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
  const [view, setView] = useState<ViewMode>('grid')
  const [detail, setDetail] = useState<Row | null>(null)
  const first = useRef(true)

  const toggle = (id: number) => setSelected((prev) => {
    const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n
  })
  const openTransfer = (ids?: number[]) => { if (ids) setSelected(new Set(ids)); setDetail(null); setTransferOpen(true) }
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
        <Kpi label="إجمالي المبدعين" icon="users" value={numFmt(facets.total)} sub={`${numFmt(celeb)} مشاهير · ${numFmt(ugc)} UGC`} />
        <Kpi label="الوصول الإجمالي" icon="trending-up" tone="accent" value={fnum(facets.reach)} sub="مجموع المتابعين عبر القاعدة" />
        <Kpi label="بأسعار حجز" icon="tag" tone="success" value={numFmt(facets.priced)} sub={`${Math.round((facets.priced / Math.max(1, facets.total)) * 100)}٪ من القاعدة مسعّرة`} />
        <Kpi label="قابلون للتواصل" icon="phone" tone="warning" value={numFmt(facets.contactable)} sub="لديهم جوّال محفوظ" />
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

      {/* محرّك الترشيح */}
      <div className="ih-matchbar">
        <span className="ih-matchbar__label"><Icon name="sparkles" size={15} /> محرّك الترشيح</span>
        <input className="field" style={{ minWidth: 170 }} placeholder="مجالات (بفواصل): رياضة، عناية"
          value={cats} onChange={(e) => setCats(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && apply({ match_categories: cats || undefined })} />
        <input className="field" type="number" style={{ maxWidth: 140 }} placeholder="الميزانية ر.س"
          value={budget} onChange={(e) => setBudget(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && apply({ budget_riyals: budget || undefined })} />
        <button className="btn btn-sm" onClick={() => apply({ match_categories: cats || undefined, budget_riyals: budget || undefined })}>رتّب بالملاءمة</button>
        <span className="ih-matchbar__hint">{matching ? '✓ مُرتَّب بالملاءمة الآن' : 'أدخِل مجالًا أو ميزانية للترتيب'}</span>
      </div>

      {/* شريط التحويل */}
      {selected.size > 0 && (
        <div className="ih-selbar">
          <b style={{ color: 'var(--ih-primary-700)' }}>{selected.size} مختار</b>
          <button className="btn btn-sm" onClick={() => openTransfer()}><Icon name="share" size={14} /> تحويل إلى عميل…</button>
          <button className="btn btn-sm btn-ghost" onClick={() => setSelected(new Set())}>إلغاء الاختيار</button>
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
          {/* شريط الأدوات: عدد + مبدّل العرض */}
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '.6rem', flexWrap: 'wrap', marginBottom: '.8rem' }}>
            <span style={{ fontSize: '.82rem', color: 'var(--ih-text-secondary)' }}>
              {pool.total.toLocaleString('en-US')} مبدع{hasFilters ? ' · مُرشَّح' : ''}{matching ? ' · مُرتَّب بالملاءمة' : ''}
            </span>
            <div className="ih-viewtoggle">
              {VIEWS.map(([v, icon, label]) => (
                <button key={v} className={`ih-viewbtn${view === v ? ' active' : ''}`} onClick={() => setView(v)}
                  title={label} aria-label={label} aria-pressed={view === v}><Icon name={icon} size={15} /></button>
              ))}
            </div>
          </div>

          {/* ═══ بطاقات مربّعة (افتراضي) ═══ */}
          {view === 'grid' && (
            <div className="ih-recgrid">
              {pool.data.map((c) => {
                const isSel = selected.has(c.id)
                return (
                  <div key={c.id} className="ih-gcard" onClick={() => setDetail(c)} style={isSel ? { borderColor: 'var(--ih-primary)', background: 'var(--ih-primary-50, #F5F8FF)' } : undefined}>
                    <div className="ih-gcard__top">
                      <label onClick={(e) => e.stopPropagation()} style={{ display: 'flex', alignItems: 'center', gap: '.3rem', cursor: 'pointer' }}>
                        <input type="checkbox" checked={isSel} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} />
                      </label>
                      {matching && c.matchScore != null
                        ? <ScoreRing score={c.matchScore} size={46} />
                        : <TierBadge tier={c.tier} />}
                    </div>
                    <div style={{ fontWeight: 800, fontSize: '.95rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</div>
                    <div style={{ display: 'flex', gap: '.35rem', alignItems: 'center', flexWrap: 'wrap' }}>
                      {matching && <TierBadge tier={c.tier} />}<PlatformTag label={c.platformLabel} /><UgcBadge source={c.sourceType} />
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.76rem', color: 'var(--ih-text-secondary)', direction: 'ltr' }}>
                      <span>{fnum(c.followers)} متابع</span>
                      {c.sellCoverage != null && <span>{sar(c.sellCoverage)} ر.س</span>}
                    </div>
                    {c.categories.length > 0 && (
                      <div style={{ display: 'flex', gap: '.25rem', flexWrap: 'wrap' }}>
                        {c.categories.slice(0, 2).map((cat, i) => <span key={i} className="ih-tag" style={{ fontSize: '.6rem' }}>{cat}</span>)}
                        {c.categories.length > 2 && <span className="ih-tag" style={{ fontSize: '.6rem' }}>+{c.categories.length - 2}</span>}
                      </div>
                    )}
                    <div style={{ marginTop: 'auto', display: 'flex', gap: '.35rem' }} onClick={(e) => e.stopPropagation()}>
                      <button className="btn btn-xs btn-outline" style={{ flex: 1 }} onClick={() => setDetail(c)}>تفاصيل</button>
                      <button className="btn btn-xs" style={{ flex: 1 }} onClick={() => openTransfer([c.id])}>تحويل</button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}

          {/* ═══ قائمة ═══ */}
          {view === 'list' && (
            <div style={{ display: 'grid', gap: '.5rem' }}>
              {pool.data.map((c) => {
                const isSel = selected.has(c.id)
                return (
                  <div key={c.id} className="ih-reccard" onClick={() => setDetail(c)} style={{ cursor: 'pointer', ...(isSel ? { borderColor: 'var(--ih-primary)', background: 'var(--ih-primary-50, #F5F8FF)' } : {}) }}>
                    <label className="ih-reccard__pick" onClick={(e) => e.stopPropagation()}>
                      <input type="checkbox" checked={isSel} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} />
                    </label>
                    {matching && c.matchScore != null && <ScoreRing score={c.matchScore} />}
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', flexWrap: 'wrap' }}>
                        <b style={{ fontSize: '.98rem' }}>{c.name}</b>
                        <TierBadge tier={c.tier} /><PlatformTag label={c.platformLabel} />
                        <span style={{ fontSize: '.8rem', color: 'var(--ih-text-secondary)', direction: 'ltr' }}>{fnum(c.followers)} متابع</span>
                        <UgcBadge source={c.sourceType} />
                      </div>
                      {matching && c.matchReasons.length > 0 && (
                        <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginTop: '.4rem' }}>
                          {c.matchReasons.slice(0, 4).map((r, i) => (
                            <span key={i} style={{ fontSize: '.68rem', background: 'var(--ih-success-soft, #ECFDF3)', color: 'var(--ih-success-700, #067647)', borderRadius: 6, padding: '.1rem .45rem' }}>{r}</span>
                          ))}
                        </div>
                      )}
                      <div className="ih-reccard__meta">
                        <span><Icon name="map-pin" size={12} /> {[c.city, c.region].filter(Boolean).join(' · ') || '—'}</span>
                        {c.phone && <span style={{ direction: 'ltr' }}><Icon name="phone" size={12} /> {c.phone}</span>}
                        {(c.sellCoverage || c.costCoverage) && <span style={{ direction: 'ltr' }}><Icon name="tag" size={12} /> تغطية {sar(c.costCoverage)}/{sar(c.sellCoverage)} ر.س</span>}
                      </div>
                    </div>
                    <div style={{ display: 'grid', gap: '.35rem', flexShrink: 0 }} onClick={(e) => e.stopPropagation()}>
                      <button className="btn btn-xs btn-outline" onClick={() => setDetail(c)}>تفاصيل</button>
                      <button className="btn btn-xs" onClick={() => openTransfer([c.id])}>تحويل</button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}

          {/* ═══ جدول ═══ */}
          {view === 'table' && (
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr>
                  <th style={{ width: 30 }}></th><th>المبدع</th><th>الفئة</th><th>المنصّة</th><th>المتابعون</th><th>المجالات</th>
                  {matching && <th>الملاءمة</th>}
                  <th>تكلفة/بيع (تغطية)</th><th>التواصل</th><th>الموقع</th><th></th>
                </tr></thead>
                <tbody>
                  {pool.data.map((c) => (
                    <tr key={c.id} className={selected.has(c.id) ? 'is-selected' : ''} onClick={() => setDetail(c)} style={{ cursor: 'pointer' }}>
                      <td onClick={(e) => e.stopPropagation()}><input type="checkbox" checked={selected.has(c.id)} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} /></td>
                      <td><b>{c.name}</b> <UgcBadge source={c.sourceType} />{c.rating && <span style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)', marginInlineStart: 4 }}>★ {c.rating}</span>}</td>
                      <td><TierBadge tier={c.tier} /></td>
                      <td><PlatformTag label={c.platformLabel} /></td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{fnum(c.followers)}</td>
                      <td>
                        <div style={{ display: 'flex', gap: '.25rem', flexWrap: 'wrap', maxWidth: 180 }}>
                          {c.categories.slice(0, 2).map((cat, i) => <span key={i} className="ih-tag" style={{ fontSize: '.6rem' }}>{cat}</span>)}
                          {c.categories.length > 2 && <span className="ih-tag" style={{ fontSize: '.6rem' }}>+{c.categories.length - 2}</span>}
                        </div>
                      </td>
                      {matching && (
                        <td>{c.matchScore != null ? <span className="badge" title={c.matchReasons.join(' · ')} style={{ ...(() => { const t = scoreTone(c.matchScore); return { background: t.bg, color: t.fg, fontWeight: 800 } })() }}>{c.matchScore}٪</span> : '—'}</td>
                      )}
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right', whiteSpace: 'nowrap' }}>{sar(c.costCoverage)} / {sar(c.sellCoverage)}</td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontFamily: 'var(--ih-font-mono, monospace)', fontSize: '.76rem' }}>{c.phone ?? '—'}</td>
                      <td style={{ fontSize: '.76rem', color: 'var(--ih-text-secondary)' }}>{[c.city, c.region].filter(Boolean).join(' · ') || '—'}</td>
                      <td onClick={(e) => e.stopPropagation()} style={{ textAlign: 'end' }}>
                        <button className="btn btn-xs btn-outline" onClick={() => setDetail(c)}>تفاصيل</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div></div>
          )}

          <div style={{ marginTop: '1rem' }}><Pagination links={pool.links} /></div>
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

      {/* نافذة التفاصيل — مركزيّة (مكوّن مشترك) */}
      {detail && (
        <CreatorDetailModal creator={detail} onClose={() => setDetail(null)} onTransfer={(id) => openTransfer([id])} showMatch={matching} />
      )}

      {/* نافذة التحويل — مركزيّة */}
      {transferOpen && (
        <div className="modal-backdrop" role="dialog" aria-modal="true" aria-label="تحويل إلى عميل"
          onClick={(e) => { if (e.target === e.currentTarget) setTransferOpen(false) }}>
          <div className="modal" style={{ width: 'min(460px, 100%)', padding: '1.3rem' }}>
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
    </AppShell>
  )
}
