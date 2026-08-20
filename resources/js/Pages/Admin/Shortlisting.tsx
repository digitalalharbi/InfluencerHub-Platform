import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { adminNav } from '@/lib/nav'
import { ListHead } from '@/Components/ui'
import { Icon } from '@/Components/Icon'
import { u } from '@/lib/href'

interface Result {
  id: number; name: string; platform: string; platformLabel: string
  accountUrl: string | null; phone: string | null; followers: number | null
  tier: string | null; categories: string[]
  costPost: number | null; costCoverage: number | null
  sellPost: number | null; sellCoverage: number | null
  region: string | null; city: string | null; sourceType: string
  matchScore: number | null; matchReasons: string[]; matchFlags: string[]
}
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

/** منسّق أرقام مضغوط صحيح — يرقّي إلى M (لا 2800K) ويشذّب .0 */
const fnum = (n: number | null): string => {
  if (n == null) return '—'
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M'
  if (n >= 1000) return (n / 1000).toFixed(n >= 100_000 ? 0 : 1).replace('.0', '') + 'K'
  return n.toLocaleString('en-US')
}
const sar = (n: number | null): string => (n == null ? '—' : n.toLocaleString('en-US'))

const TIER_COLOR: Record<string, string> = { A: 'var(--ih-primary)', B: 'var(--ih-accent-600)', C: 'var(--ih-gray-500)' }
const PLATFORM_TONE: Record<string, string> = {
  snapchat: '#F5C518', tiktok: '#EE1D52', instagram: '#C13584', youtube: '#FF0000', x: '#1DA1F2', linkedin: '#0A66C2',
}
const scoreTone = (s: number): { fg: string; bg: string } =>
  s >= 70 ? { fg: 'var(--ih-success-700, #067647)', bg: 'var(--ih-success-soft, #ECFDF3)' }
    : s >= 45 ? { fg: 'var(--ih-warning-ink, #B54708)', bg: 'var(--ih-warning-soft, #FFFAEB)' }
      : { fg: 'var(--ih-gray-600)', bg: 'var(--ih-gray-100)' }

/** حلقة درجة الملاءمة — SVG دائريّ احترافيّ */
function ScoreRing({ score }: { score: number }) {
  const r = 22, c = 2 * Math.PI * r, off = c * (1 - score / 100)
  const t = scoreTone(score)
  return (
    <div style={{ position: 'relative', width: 56, height: 56, flexShrink: 0 }}>
      <svg width="56" height="56" viewBox="0 0 56 56" style={{ transform: 'rotate(-90deg)' }}>
        <circle cx="28" cy="28" r={r} fill="none" stroke="var(--ih-border)" strokeWidth="5" />
        <circle cx="28" cy="28" r={r} fill="none" stroke={t.fg} strokeWidth="5" strokeLinecap="round"
          strokeDasharray={c} strokeDashoffset={off} />
      </svg>
      <div style={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center', fontWeight: 800, fontSize: '.86rem', color: t.fg }}>{score}٪</div>
    </div>
  )
}

/** شريط توزيع مصغّر */
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

/**
 * ترشيح المؤثرين — محرّك بحث/مساعد تحليليّ فوق القاعدة. لمدير النظام وحده.
 * يفهم الطلب النصّي (قواعد اليوم، OpenAI لاحقًا)، يعرض ما فهمه بشفافية، يحلّل
 * مجموعة النتائج، ويتيح تحويل المختار إلى عميل مباشرةً (دمج مع مسار التوصيات).
 */
export default function Shortlisting({ query, understood, results, analytics, clients, hasSearch, assistant, poolSize }: Props) {
  const [text, setText] = useState(query)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [transferOpen, setTransferOpen] = useState(false)
  const [clientId, setClientId] = useState('')

  const search = (q: string) => {
    setText(q); setSelected(new Set())
    router.get(u('/shortlisting'), q ? { query: q } : {}, { preserveState: true })
  }
  const toggle = (id: number) => setSelected((prev) => {
    const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n
  })
  const selectAll = () => setSelected(new Set(results.map((r) => r.id)))
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

      {/* صندوق البحث/المساعد */}
      <div className="ih-searchhero">
        <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center', flexWrap: 'wrap' }}>
          <label className="ih-search" style={{ flex: 1, minWidth: 260 }}>
            <Icon name="sparkles" size={17} />
            <input placeholder="صِف من تبحث عنه… مثال: مؤثرة عناية في الرياض بميزانية 20000"
              value={text} onChange={(e) => setText(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && search(text)} autoFocus />
          </label>
          <button className="btn btn-primary" onClick={() => search(text)}><Icon name="search" size={15} /> ترشيح</button>
          {text && <button className="btn btn-ghost btn-sm" onClick={() => search('')}>مسح</button>}
        </div>
        <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', marginTop: '.7rem', alignItems: 'center' }}>
          <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>جرّب:</span>
          {EXAMPLES.map((ex) => (
            <button key={ex} className="btn btn-xs btn-outline" onClick={() => search(ex)}>{ex}</button>
          ))}
        </div>
        <div style={{ marginTop: '.7rem', fontSize: '.72rem', color: 'var(--ih-text-muted)', display: 'flex', alignItems: 'center', gap: '.35rem' }}>
          <Icon name="shield-check" size={13} />
          {assistant.openaiReady ? 'المساعد: OpenAI (مربوط)' : 'المساعد: قواعد لغوية · مساعد OpenAI جاهز للربط لاحقًا (يحتاج مفتاحًا)'}
        </div>
      </div>

      {/* ما فهمه المساعد — شفافية قابلة للتصحيح */}
      {understood.length > 0 && (
        <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', alignItems: 'center', marginBottom: '1rem' }}>
          <span style={{ fontSize: '.8rem', color: 'var(--ih-text-secondary)', fontWeight: 600 }}>فهمتُ:</span>
          {understood.map((u2, i) => (
            <span key={i} className="badge" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{u2}</span>
          ))}
        </div>
      )}

      {/* لوحة التحليلات — تظهر مع النتائج */}
      {analytics && results.length > 0 && (
        <div className="ih-analytics">
          <div className="ih-analytics__stats">
            <div className="ih-stat"><span className="ih-stat__v" style={{ color: 'var(--ih-primary-700)' }}>{analytics.avgScore}٪</span><span className="ih-stat__l">متوسط الملاءمة</span></div>
            <div className="ih-stat"><span className="ih-stat__v">{fnum(analytics.reach)}</span><span className="ih-stat__l">إجمالي الوصول</span></div>
            <div className="ih-stat"><span className="ih-stat__v">{analytics.avgCoverage != null ? sar(analytics.avgCoverage) : '—'}</span><span className="ih-stat__l">متوسط سعر التغطية (ر.س)</span></div>
            <div className="ih-stat">
              <span className="ih-stat__v" style={{ fontSize: '.95rem' }}>
                {analytics.minCoverage != null ? `${sar(analytics.minCoverage)}–${sar(analytics.maxCoverage)}` : '—'}
              </span>
              <span className="ih-stat__l">نطاق التغطية (ر.س)</span>
            </div>
            <div className="ih-stat"><span className="ih-stat__v">{analytics.contactable}/{analytics.shown}</span><span className="ih-stat__l">قابلون للتواصل</span></div>
          </div>
          {platEntries.length > 0 && (
            <div className="ih-analytics__dist">
              <div className="ih-analytics__dist-title">توزيع المنصّات</div>
              {platEntries.map(([p, c]) => <DistBar key={p} label={p} count={c} total={platMax} color={PLATFORM_TONE[Object.keys(PLATFORM_TONE).find((k) => p.includes(k) || k === p) ?? '']} />)}
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

      {/* شريط الاختيار والتحويل (الدمج) */}
      {selected.size > 0 && (
        <div className="ih-selbar">
          <b style={{ color: 'var(--ih-primary-700)' }}>{selected.size} مختار</b>
          <button className="btn btn-sm" onClick={() => setTransferOpen(true)}><Icon name="share" size={14} /> تحويل إلى عميل…</button>
          <button className="btn btn-sm btn-ghost" onClick={() => setSelected(new Set())}>إلغاء</button>
        </div>
      )}

      {transferOpen && (
        <div className="ih-modal-backdrop" role="dialog" aria-modal="true" aria-label="تحويل إلى عميل">
          <div className="ih-modal" style={{ maxWidth: 460 }}>
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
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '.6rem', flexWrap: 'wrap', marginBottom: '.7rem' }}>
            <span style={{ fontSize: '.82rem', color: 'var(--ih-text-secondary)' }}>
              أفضل {results.length} ترشيحًا — مرتّبة بالملاءمة{analytics && analytics.candidates > results.length ? ` · من ${analytics.candidates.toLocaleString('en-US')} مطابقًا للمعايير` : ''}
            </span>
            <button className="btn btn-xs btn-outline" onClick={selectAll}>اختيار الكل</button>
          </div>
          <div style={{ display: 'grid', gap: '.6rem' }}>
            {results.map((c, rank) => {
              const isSel = selected.has(c.id)
              return (
                <div key={c.id} className="ih-reccard" style={isSel ? { borderColor: 'var(--ih-primary)', background: 'var(--ih-primary-50, #F5F8FF)' } : undefined}>
                  <label className="ih-reccard__pick">
                    <input type="checkbox" checked={isSel} onChange={() => toggle(c.id)} aria-label={`اختيار ${c.name}`} />
                    <span className="ih-reccard__rank">#{rank + 1}</span>
                  </label>
                  <ScoreRing score={c.matchScore ?? 0} />
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', flexWrap: 'wrap' }}>
                      <b style={{ fontSize: '.98rem' }}>{c.name}</b>
                      {c.tier && <span className="badge" style={{ background: (TIER_COLOR[c.tier] ?? TIER_COLOR.C) + '1f', color: TIER_COLOR[c.tier] ?? TIER_COLOR.C, fontWeight: 800 }}>{c.tier}</span>}
                      <span className="ih-tag" style={{ color: PLATFORM_TONE[c.platform] }}>{c.platformLabel}</span>
                      <span style={{ fontSize: '.8rem', color: 'var(--ih-text-secondary)', direction: 'ltr' }}>{fnum(c.followers)} متابع</span>
                      {c.sourceType === 'ugc' && <span className="badge" style={{ background: 'var(--ih-accent-soft, #FDF2FA)', color: 'var(--ih-accent-600, #C13584)', fontSize: '.58rem' }}>UGC</span>}
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
                      <span><Icon name="users" size={12} /> {[c.city, c.region].filter(Boolean).join(' · ') || '—'}</span>
                      {c.phone && <span style={{ direction: 'ltr' }}><Icon name="phone" size={12} /> {c.phone}</span>}
                      {(c.sellCoverage || c.costCoverage) && <span style={{ direction: 'ltr' }}><Icon name="tag" size={12} /> تغطية {sar(c.costCoverage)}/{sar(c.sellCoverage)} ر.س</span>}
                      {(c.sellPost || c.costPost) && <span style={{ direction: 'ltr' }}>منشور {sar(c.costPost)}/{sar(c.sellPost)} ر.س</span>}
                    </div>
                  </div>
                  <div style={{ display: 'grid', gap: '.35rem', flexShrink: 0 }}>
                    {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline">الحساب ↗</a>}
                    <button className="btn btn-xs" onClick={() => { setSelected(new Set([c.id])); setTransferOpen(true) }}>تحويل</button>
                  </div>
                </div>
              )
            })}
          </div>
        </>
      )}
    </AppShell>
  )
}
