import { Icon } from '@/Components/Icon'
import { u } from '@/lib/href'

/** الصفّ الموحّد لمبدع القاعدة — يطابق toBookingArray في الخادم. */
export interface PoolCreatorRow {
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

/** منسّق أرقام مضغوط صحيح — يرقّي إلى M (لا 2800K) ويشذّب .0 */
export const fnum = (n: number | null): string => {
  if (n == null) return '—'
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M'
  if (n >= 1000) return (n / 1000).toFixed(n >= 100_000 ? 0 : 1).replace('.0', '') + 'K'
  return n.toLocaleString('en-US')
}
export const sar = (n: number | null): string => (n == null ? '—' : n.toLocaleString('en-US'))
export const margin = (sell: number | null, cost: number | null): number | null =>
  sell != null && cost != null ? sell - cost : null

/**
 * رابط واتساب لحجز إعلان — يطبّع الرقم السعودي إلى صيغة دولية (966…) ويُرفق رسالة
 * حجز جاهزة. الأرقام المحلّية 05… تُحوَّل بإسقاط الصفر ووضع 966، وما بدأ بـ5 وطوله
 * تسعة يُسبَق بـ966. يُفتَح في تبويب جديد (واجهة واتساب ويب/التطبيق).
 */
export const waLink = (phone: string, name: string): string => {
  let d = phone.replace(/\D/g, '')
  if (d.startsWith('00')) d = d.slice(2)
  if (d.startsWith('966')) { /* دولي جاهز */ }
  else if (d.startsWith('0')) d = '966' + d.slice(1)
  else if (d.length === 9 && d.startsWith('5')) d = '966' + d
  const msg = `السلام عليكم، أرغب في حجز إعلان مع ${name}. هل تفاصيل الباقات والتوفّر متاحة؟`
  return `https://wa.me/${d}?text=${encodeURIComponent(msg)}`
}

export const TIER_COLOR: Record<string, string> = { A: 'var(--ih-primary)', B: 'var(--ih-accent-600)', C: 'var(--ih-gray-500)' }
export const PLATFORM_TONE: Record<string, string> = {
  snapchat: '#F5C518', tiktok: '#EE1D52', instagram: '#C13584', youtube: '#FF0000', x: '#1DA1F2', linkedin: '#0A66C2',
}
export const platColor = (label: string): string | undefined =>
  PLATFORM_TONE[Object.keys(PLATFORM_TONE).find((k) => label.toLowerCase().includes(k)) ?? '']
export const scoreTone = (s: number): { fg: string; bg: string } =>
  s >= 70 ? { fg: 'var(--ih-success-700, #067647)', bg: 'var(--ih-success-soft, #ECFDF3)' }
    : s >= 45 ? { fg: 'var(--ih-warning-ink, #B54708)', bg: 'var(--ih-warning-soft, #FFFAEB)' }
      : { fg: 'var(--ih-gray-600)', bg: 'var(--ih-gray-100)' }

/** حلقة درجة الملاءمة — SVG دائريّ احترافيّ */
export function ScoreRing({ score, size = 56 }: { score: number; size?: number }) {
  const r = size / 2 - 6, c = 2 * Math.PI * r, off = c * (1 - score / 100)
  const t = scoreTone(score)
  return (
    <div style={{ position: 'relative', width: size, height: size, flexShrink: 0 }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ transform: 'rotate(-90deg)' }}>
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="var(--ih-border)" strokeWidth="5" />
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={t.fg} strokeWidth="5" strokeLinecap="round"
          strokeDasharray={c} strokeDashoffset={off} />
      </svg>
      <div style={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center', fontWeight: 800, fontSize: size < 50 ? '.74rem' : '.86rem', color: t.fg }}>{score}٪</div>
    </div>
  )
}

export function TierBadge({ tier }: { tier: string | null }) {
  if (!tier) return null
  return <span className="badge" style={{ background: (TIER_COLOR[tier] ?? TIER_COLOR.C) + '1f', color: TIER_COLOR[tier] ?? TIER_COLOR.C, fontWeight: 800 }}>{tier}</span>
}

export function PlatformTag({ label }: { label: string }) {
  return <span className="ih-tag" style={{ color: platColor(label) }}>{label}</span>
}

export function UgcBadge({ source }: { source: string }) {
  if (source !== 'ugc') return null
  return <span className="badge" style={{ background: 'var(--ih-accent-soft, #FDF2FA)', color: 'var(--ih-accent-600, #C13584)', fontSize: '.56rem' }}>UGC</span>
}

/**
 * لوحة تفاصيل المبدع — نافذة مركزيّة بحجم مناسب، مشتركة بين «الترشيح» و«القاعدة».
 * تعرض تحليل الملاءمة (إن وُجد) والحقائق والمجالات وجدول التسعير بالهامش والتواصل.
 */
export function CreatorDetailModal({
  creator, onClose, onTransfer, showMatch = true,
}: {
  creator: PoolCreatorRow
  onClose: () => void
  onTransfer: (id: number) => void
  showMatch?: boolean
}) {
  const c = creator
  const hasMatch = showMatch && c.matchScore != null
  return (
    <div className="modal-backdrop" role="dialog" aria-modal="true" aria-label={`تفاصيل ${c.name}`}
      onClick={(e) => { if (e.target === e.currentTarget) onClose() }}>
      <div className="modal ih-detailmodal">
        <div className="ih-detailmodal__head">
          <div className="ih-detailmodal__id">
            {hasMatch && <ScoreRing score={c.matchScore ?? 0} size={44} />}
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem', flexWrap: 'wrap' }}>
                <b style={{ fontSize: '1rem' }}>{c.name}</b>
                <TierBadge tier={c.tier} />
                <UgcBadge source={c.sourceType} />
              </div>
              <div style={{ fontSize: '.76rem', color: 'var(--ih-text-secondary)', display: 'flex', gap: '.5rem', flexWrap: 'wrap', marginTop: '.15rem' }}>
                <span style={{ color: platColor(c.platformLabel) }}>{c.platformLabel}</span>
                <span style={{ direction: 'ltr' }}>{fnum(c.followers)} متابع</span>
                {c.rating && <span>★ {c.rating}</span>}
              </div>
            </div>
            <button className="btn btn-xs btn-ghost" onClick={onClose} aria-label="إغلاق"><Icon name="x" size={16} /></button>
          </div>
          {/* إجراءات علوية: الحساب والجوّال ظاهران فورًا بلا تمرير */}
          <div className="ih-detailmodal__actions">
            {c.accountUrl && (
              <a href={c.accountUrl} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-primary">
                <Icon name="external-link" size={14} /> فتح الحساب
              </a>
            )}
            {c.phone
              ? <a href={waLink(c.phone, c.name)} target="_blank" rel="noopener noreferrer" className="btn btn-xs ih-wa" title="حجز إعلان عبر واتساب"><Icon name="message-circle" size={13} /> <span style={{ direction: 'ltr' }}>{c.phone}</span></a>
              : <span className="btn btn-xs btn-outline" style={{ opacity: .55, pointerEvents: 'none' }}><Icon name="phone" size={13} /> لا جوّال</span>}
            {c.store && <span className="ih-tag" style={{ alignSelf: 'center' }}>عبر {c.store}</span>}
          </div>
        </div>

        <div className="ih-detailmodal__body">
          {hasMatch && (
            <div className="ih-dsec ih-dsec--full">
              <div className="ih-dsec__title">تحليل الملاءمة</div>
              <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
                {c.matchReasons.map((r, i) => (
                  <span key={i} style={{ fontSize: '.72rem', background: 'var(--ih-success-soft, #ECFDF3)', color: 'var(--ih-success-700, #067647)', borderRadius: 6, padding: '.15rem .5rem' }}>✓ {r}</span>
                ))}
                {c.matchFlags.map((r, i) => (
                  <span key={i} style={{ fontSize: '.72rem', background: 'var(--ih-warning-soft, #FFFAEB)', color: 'var(--ih-warning-ink, #B54708)', borderRadius: 6, padding: '.15rem .5rem' }}>⚠ {r}</span>
                ))}
              </div>
            </div>
          )}

          <div className="ih-dcol">
            <div className="ih-dsec">
              <div className="ih-dsec__title">نظرة سريعة</div>
              <div className="ih-dfacts">
                <div><span className="ih-dfacts__l">المتابعون</span><span className="ih-dfacts__v ltr">{fnum(c.followers)}</span></div>
                <div><span className="ih-dfacts__l">الإعجابات</span><span className="ih-dfacts__v ltr">{fnum(c.likes)}</span></div>
                <div><span className="ih-dfacts__l">المصدر</span><span className="ih-dfacts__v">{c.sourceType === 'ugc' ? 'UGC' : 'مشاهير'}</span></div>
                <div><span className="ih-dfacts__l">يظهر وجهه</span><span className="ih-dfacts__v">{c.showsFace == null ? '—' : c.showsFace ? 'نعم' : 'لا'}</span></div>
              </div>
            </div>
            {c.categories.length > 0 && (
              <div className="ih-dsec">
                <div className="ih-dsec__title">المجالات</div>
                <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
                  {c.categories.map((cat, i) => <span key={i} className="ih-tag">{cat}</span>)}
                </div>
              </div>
            )}
          </div>

          <div className="ih-dcol">
            <div className="ih-dsec">
              <div className="ih-dsec__title">التسعير والهامش (ر.س)</div>
              <table className="ih-ptable">
                <thead><tr><th></th><th>التكلفة</th><th>البيع</th><th>الهامش</th></tr></thead>
                <tbody>
                  <tr><td>منشور</td><td>{sar(c.costPost)}</td><td>{sar(c.sellPost)}</td><td style={{ color: 'var(--ih-success-700, #067647)', fontWeight: 700 }}>{sar(margin(c.sellPost, c.costPost))}</td></tr>
                  <tr><td>تغطية</td><td>{sar(c.costCoverage)}</td><td>{sar(c.sellCoverage)}</td><td style={{ color: 'var(--ih-success-700, #067647)', fontWeight: 700 }}>{sar(margin(c.sellCoverage, c.costCoverage))}</td></tr>
                </tbody>
              </table>
            </div>
            <div className="ih-dsec">
              <div className="ih-dsec__title">الموقع</div>
              <div className="ih-dfacts">
                <div><span className="ih-dfacts__l">المدينة</span><span className="ih-dfacts__v">{c.city ?? '—'}</span></div>
                <div><span className="ih-dfacts__l">المنطقة</span><span className="ih-dfacts__v">{c.region ?? '—'}</span></div>
              </div>
            </div>
          </div>
        </div>

        <div className="ih-detailmodal__foot">
          <button className="btn btn-primary" style={{ flex: 1 }} onClick={() => onTransfer(c.id)}><Icon name="share" size={15} /> تحويل إلى عميل</button>
          <a href={u(`/creator-pool/${c.id}`)} className="btn btn-outline"><Icon name="external-link" size={14} /> الملفّ الكامل</a>
        </div>
      </div>
    </div>
  )
}
