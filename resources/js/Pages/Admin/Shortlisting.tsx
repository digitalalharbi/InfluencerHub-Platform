import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { adminNav } from '@/lib/nav'
import { ListHead } from '@/Components/ui'
import { Icon, type IconName } from '@/Components/Icon'
import {
  CreatorDetailModal, ScoreRing, TierBadge, PlatformTag, UgcBadge,
  fnum, sar, platColor, scoreTone, TIER_COLOR, type PoolCreatorRow,
} from '@/Components/PoolCreator'
import { u } from '@/lib/href'

type Result = PoolCreatorRow
interface Analytics {
  shown: number; candidates: number; avgScore: number; topScore: number
  reach: number; contactable: number; pricedCount: number
  avgCoverage: number | null; minCoverage: number | null; maxCoverage: number | null
  platforms: Record<string, number>; tiers: Record<string, number>
}
interface ClientOption { id: number; name: string }
interface Props {
  query: string
  criteria: Record<string, unknown>
  understood: string[]
  results: Result[]
  analytics: Analytics | null
  clients: ClientOption[]
  hasSearch: boolean
  assistant: { driver: string; openaiReady: boolean }
  poolSize: number
}

const EXAMPLES = [
  'مؤثرة عناية في الرياض بمتابعين فوق 500 ألف وميزانية 20000',
  'مشاهير سناب رياضة أقل من 10000 ريال',
  'تيك توك تغطيات جدة',
]

function DistBar({ label, count, total, color }: { label: string; count: number; total: number; color?: string }) {
  const pct = total ? Math.round((count / total) * 100) : 0
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', fontSize: '.74rem' }}>
      <span style={{ minWidth: 68, color: 'var(--ih-text-secondary)' }}>{label}</span>
      <div style={{ flex: 1, height: 7, background: 'var(--ih-surface-sunken)', borderRadius: 4, overflow: 'hidden' }}>
        <span style={{ display: 'block', height: '100%', width: `${pct}%`, background: color ?? 'var(--ih-primary)', borderRadius: 4 }} />
      </div>
      <span style={{ minWidth: 30, textAlign: 'end', fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{count}</span>
    </div>
  )
}

type ViewMode = 'grid' | 'list' | 'table'
const VIEWS: [ViewMode, IconName, string][] = [['grid', 'grid', 'بطاقات'], ['list', 'rows', 'قائمة'], ['table', 'table', 'جدول']]

/**
 * ترشيح المؤثرين — محرّك بحث/مساعد تحليليّ فوق القاعدة. لمدير النظام وحده.
 * تطوير متكامل: ٣ أوضاع عرض (بطاقات مربّعة افتراضيًّا/قائمة/جدول)، نافذة تفاصيل
 * مركزيّة عند النقر (مكوّن مشترك)، واختيار وتحويل مباشر إلى عميل (دمج).
 */
export default function Shortlisting({ query, understood, results, analytics, clients, hasSearch, assistant, poolSize }: Props) {
  const [text, setText] = useState(query)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [transferOpen, setTransferOpen] = useState(false)
  const [clientId, setClientId] = useState('')
  const [view, setView] = useState<ViewMode>('grid')
  const [detail, setDetail] = useState<Result | null>(null)

  const search = (q: string) => {
    setText(q); setSelected(new Set()); setDetail(null)
    router.get(u('/shortlisting'), q ? { query: q } : {}, { preserveState: true })
  }
  const toggle = (id: number) => setSelected((prev) => {
    const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n
  })
  const selectAll = () => setSelected(new Set(results.map((r) => r.id)))
  const openTransfer = (ids?: number[]) => { if (ids) setSelected(new Set(ids)); setDetail(null); setTransferOpen(true) }
  const transfer = () => {
    if (!clientId || selected.size === 0) return
    router.post(u('/creator-pool/transfer'),
      { client_id: clientId, pool_ids: [...selected] },
      { onSuccess: () => { setSelected(new Set()); setTransferOpen(false); setClientId('') } })
  }

  const platEntries = analytics ? Object.entries(analytics.platforms) : []
  const platMax = platEntries.reduce((m, [, c]) => Math.max(m, c), 0)

  return (
    <AppShell heading="ترشيح المؤثرين" nav={adminNav} portal="admin"
      wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="ترشيح المؤثرين" />
      <ListHead eyebrow="مدير النظام · محرّك ذكيّ تحليليّ" title="ترشيح المؤثرين"
        sub={`ابحث في ${poolSize.toLocaleString('en-US')} مؤثرًا بالوصف الطبيعي — المساعد يفهم طلبك، يحلّل النتائج، ويحوّلها إلى عميل.`}
        actions={<a href={u('/creator-pool')} className="btn btn-sm btn-outline"><Icon name="users" size={15} /> القاعدة الكاملة</a>} />

      {/* صندوق البحث/المساعد — كبير واحترافيّ */}
      <div className="ih-searchhero">
        <div style={{ fontSize: '.92rem', fontWeight: 700, marginBottom: '.7rem', display: 'flex', alignItems: 'center', gap: '.4rem' }}>
          <Icon name="sparkles" size={16} style={{ color: 'var(--ih-primary-600)' }} />
          صِف من تبحث عنه بالعربية الطبيعية
        </div>
        <div className="ih-searchhero__row">
          <label className="ih-search">
            <Icon name="search" size={20} />
            <input placeholder="مثال: مؤثرة عناية في الرياض بمتابعين فوق 500 ألف وميزانية 20000"
              value={text} onChange={(e) => setText(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && search(text)} autoFocus />
            {text && <button type="button" className="btn btn-xs btn-ghost" style={{ flexShrink: 0 }} onClick={() => search('')} aria-label="مسح"><Icon name="x" size={16} /></button>}
          </label>
          <button className="btn btn-primary ih-searchhero__go" onClick={() => search(text)}><Icon name="sparkles" size={17} /> ترشيح</button>
        </div>
        <div className="ih-searchhero__ex">
          <span style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', fontWeight: 600 }}>جرّب:</span>
          {EXAMPLES.map((ex) => (
            <button key={ex} className="btn btn-xs btn-outline" onClick={() => search(ex)}>{ex}</button>
          ))}
        </div>
        <div style={{ marginTop: '.8rem', fontSize: '.74rem', color: 'var(--ih-text-muted)', display: 'flex', alignItems: 'center', gap: '.35rem' }}>
          <Icon name="shield-check" size={13} />
          {assistant.openaiReady ? 'المساعد: OpenAI (مربوط)' : 'المساعد: قواعد لغوية · مساعد OpenAI جاهز للربط لاحقًا (يحتاج مفتاحًا)'}
        </div>
      </div>

      {/* ما فهمه المساعد */}
      {understood.length > 0 && (
        <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', alignItems: 'center', marginBottom: '1rem' }}>
          <span style={{ fontSize: '.8rem', color: 'var(--ih-text-secondary)', fontWeight: 600 }}>فهمتُ:</span>
          {understood.map((u2, i) => (
            <span key={i} className="badge" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{u2}</span>
          ))}
        </div>
      )}

      {/* لوحة التحليلات */}
      {analytics && results.length > 0 && (
        <div className="ih-analytics">
          <div className="ih-analytics__stats">
            <div className="ih-stat"><span className="ih-stat__v" style={{ color: 'var(--ih-primary-700)' }}>{analytics.avgScore}٪</span><span className="ih-stat__l">متوسط الملاءمة</span></div>
            <div className="ih-stat"><span className="ih-stat__v">{fnum(analytics.reach)}</span><span className="ih-stat__l">إجمالي الوصول</span></div>
            <div className="ih-stat"><span className="ih-stat__v">{analytics.avgCoverage != null ? sar(analytics.avgCoverage) : '—'}</span><span className="ih-stat__l">متوسط سعر التغطية (ر.س)</span></div>
            <div className="ih-stat"><span className="ih-stat__v" style={{ fontSize: '.95rem' }}>{analytics.minCoverage != null ? `${sar(analytics.minCoverage)}–${sar(analytics.maxCoverage)}` : '—'}</span><span className="ih-stat__l">نطاق التغطية (ر.س)</span></div>
            <div className="ih-stat"><span className="ih-stat__v">{analytics.contactable}/{analytics.shown}</span><span className="ih-stat__l">قابلون للتواصل</span></div>
          </div>
          {platEntries.length > 0 && (
            <div className="ih-analytics__dist">
              <div className="ih-analytics__dist-title">توزيع المنصّات</div>
              {platEntries.map(([p, c]) => <DistBar key={p} label={p} count={c} total={platMax} color={platColor(p)} />)}
              <div className="ih-analytics__dist-title" style={{ marginTop: '.6rem' }}>حسب الفئة</div>
              <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap' }}>
                {Object.entries(analytics.tiers).map(([t, c]) => (
                  <span key={t} className="badge" style={{ background: (TIER_COLOR[t] ?? 'var(--ih-gray-400)') + '1f', color: TIER_COLOR[t] ?? 'var(--ih-gray-600)', fontWeight: 700 }}>{t === '—' ? 'بلا فئة' : `فئة ${t}`}: {c}</span>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* شريط الاختيار والتحويل */}
      {selected.size > 0 && (
        <div className="ih-selbar">
          <b style={{ color: 'var(--ih-primary-700)' }}>{selected.size} مختار</b>
          <button className="btn btn-sm" onClick={() => openTransfer()}><Icon name="share" size={14} /> تحويل إلى عميل…</button>
          <button className="btn btn-sm btn-ghost" onClick={() => setSelected(new Set())}>إلغاء</button>
        </div>
      )}

      {!hasSearch ? (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="sparkles" size={26} /></span>
          <div className="ih-empty__title">ابدأ بوصف حملتك أو المؤثر</div>
          <div className="ih-empty__text">اكتب طلبك بالعربية الطبيعية، أو اختر مثالًا أعلاه. سيفهم المساعد المنصّة والمجال والميزانية والمتابعين ويرتّب الأنسب.</div>
        </div>
      ) : results.length === 0 ? (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="search" size={26} /></span>
          <div className="ih-empty__title">لا مطابقات</div>
          <div className="ih-empty__text">وسّع المعايير أو جرّب وصفًا آخر.</div>
        </div>
      ) : (
        <>
          {/* شريط الأدوات */}
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '.6rem', flexWrap: 'wrap', marginBottom: '.8rem' }}>
            <span style={{ fontSize: '.82rem', color: 'var(--ih-text-secondary)' }}>
              أفضل {results.length} ترشيحًا — مرتّبة بالملاءمة{analytics && analytics.candidates > results.length ? ` · من ${analytics.candidates.toLocaleString('en-US')} مطابقًا` : ''}
            </span>
            <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center' }}>
              <button className="btn btn-xs btn-outline" onClick={selectAll}>اختيار الكل</button>
              <div className="ih-viewtoggle">
                {VIEWS.map(([v, icon, label]) => (
                  <button key={v} className={`ih-viewbtn${view === v ? ' active' : ''}`} onClick={() => setView(v)}
                    title={label} aria-label={label} aria-pressed={view === v}><Icon name={icon} size={15} /></button>
                ))}
              </div>
            </div>
          </div>

          {/* ═══ بطاقات مربّعة (افتراضي) ═══ */}
          {view === 'grid' && (
            <div className="ih-recgrid">
              {results.map((c, rank) => {
                const isSel = selected.has(c.id)
                return (
                  <div key={c.id} className="ih-gcard" onClick={() => setDetail(c)} style={isSel ? { borderColor: 'var(--ih-primary)', background: 'var(--ih-primary-50, #F5F8FF)' } : undefined}>
                    <div className="ih-gcard__top">
                      <label onClick={(e) => e.stopPropagation()} style={{ display: 'flex', alignItems: 'center', gap: '.3rem', cursor: 'pointer' }}>
                        <input type="checkbox" checked={isSel} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} />
                        <span className="ih-reccard__rank">#{rank + 1}</span>
                      </label>
                      <ScoreRing score={c.matchScore ?? 0} size={46} />
                    </div>
                    <div style={{ fontWeight: 800, fontSize: '.95rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</div>
                    <div style={{ display: 'flex', gap: '.35rem', alignItems: 'center', flexWrap: 'wrap' }}>
                      <TierBadge tier={c.tier} /><PlatformTag label={c.platformLabel} /><UgcBadge source={c.sourceType} />
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
              {results.map((c, rank) => {
                const isSel = selected.has(c.id)
                return (
                  <div key={c.id} className="ih-reccard" onClick={() => setDetail(c)} style={{ cursor: 'pointer', ...(isSel ? { borderColor: 'var(--ih-primary)', background: 'var(--ih-primary-50, #F5F8FF)' } : {}) }}>
                    <label className="ih-reccard__pick" onClick={(e) => e.stopPropagation()}>
                      <input type="checkbox" checked={isSel} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} />
                      <span className="ih-reccard__rank">#{rank + 1}</span>
                    </label>
                    <ScoreRing score={c.matchScore ?? 0} />
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', flexWrap: 'wrap' }}>
                        <b style={{ fontSize: '.98rem' }}>{c.name}</b>
                        <TierBadge tier={c.tier} /><PlatformTag label={c.platformLabel} />
                        <span style={{ fontSize: '.8rem', color: 'var(--ih-text-secondary)', direction: 'ltr' }}>{fnum(c.followers)} متابع</span>
                        <UgcBadge source={c.sourceType} />
                      </div>
                      <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginTop: '.4rem' }}>
                        {c.matchReasons.slice(0, 4).map((r, i) => (
                          <span key={i} style={{ fontSize: '.68rem', background: 'var(--ih-success-soft, #ECFDF3)', color: 'var(--ih-success-700, #067647)', borderRadius: 6, padding: '.1rem .45rem' }}>{r}</span>
                        ))}
                        {c.matchFlags.slice(0, 2).map((r, i) => (
                          <span key={i} style={{ fontSize: '.68rem', background: 'var(--ih-warning-soft, #FFFAEB)', color: 'var(--ih-warning-ink, #B54708)', borderRadius: 6, padding: '.1rem .45rem' }}>⚠ {r}</span>
                        ))}
                      </div>
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
                  <th style={{ width: 30 }}></th><th>#</th><th>المبدع</th><th>الملاءمة</th><th>الفئة</th><th>المنصّة</th>
                  <th>المتابعون</th><th>تغطية (بيع)</th><th>التواصل</th><th></th>
                </tr></thead>
                <tbody>
                  {results.map((c, rank) => (
                    <tr key={c.id} className={selected.has(c.id) ? 'is-selected' : ''} onClick={() => setDetail(c)} style={{ cursor: 'pointer' }}>
                      <td onClick={(e) => e.stopPropagation()}><input type="checkbox" checked={selected.has(c.id)} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} /></td>
                      <td className="ih-dt__num" style={{ color: 'var(--ih-text-muted)' }}>{rank + 1}</td>
                      <td><b>{c.name}</b> <UgcBadge source={c.sourceType} /></td>
                      <td><span className="badge" style={{ ...(() => { const t = scoreTone(c.matchScore ?? 0); return { background: t.bg, color: t.fg, fontWeight: 800 } })() }}>{c.matchScore ?? 0}٪</span></td>
                      <td><TierBadge tier={c.tier} /></td>
                      <td><PlatformTag label={c.platformLabel} /></td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{fnum(c.followers)}</td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{sar(c.sellCoverage)}</td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontSize: '.74rem' }}>{c.phone ?? '—'}</td>
                      <td onClick={(e) => e.stopPropagation()} style={{ textAlign: 'end' }}>
                        <span style={{ display: 'inline-flex', gap: '.3rem' }}>
                          <button className="btn btn-xs btn-outline" onClick={() => setDetail(c)}>تفاصيل</button>
                          <button className="btn btn-xs" onClick={() => openTransfer([c.id])}>تحويل</button>
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div></div>
          )}
        </>
      )}

      {/* نافذة التفاصيل — مركزيّة (مكوّن مشترك) */}
      {detail && (
        <CreatorDetailModal creator={detail} onClose={() => setDetail(null)} onTransfer={(id) => openTransfer([id])} />
      )}

      {/* نافذة التحويل — مركزيّة */}
      {transferOpen && (
        <div className="modal-backdrop" role="dialog" aria-modal="true" aria-label="تحويل إلى عميل"
          onClick={(e) => { if (e.target === e.currentTarget) setTransferOpen(false) }}>
          <div className="modal" style={{ width: 'min(440px, 100%)', padding: '1.3rem' }}>
            <h3 style={{ margin: '0 0 .3rem' }}>تحويل {selected.size} مرشّحًا إلى عميل</h3>
            <p style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)', marginBlockEnd: '1rem' }}>
              تُنشأ توصية للعميل بنسخة مستقلّة عن القاعدة — بلا الجوّال. يبقى للعميل قرار القبول أو الرفض.
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
