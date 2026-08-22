import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { adminNav } from '@/lib/nav'
import { WorkTabs } from '@/Components/ui'
import { Icon } from '@/Components/Icon'
import {
  fnum, sar, margin, waLink, TierBadge, UgcBadge, PlatformTag,
  platColor, TIER_COLOR, type PoolCreatorRow,
} from '@/Components/PoolCreator'
import { u } from '@/lib/href'

interface ClientOption { id: number; name: string }
interface Props { creator: PoolCreatorRow; clients: ClientOption[] }

const GENDER: Record<string, string> = { female: 'أنثى', male: 'ذكر', أنثى: 'أنثى', ذكر: 'ذكر' }
const handleOf = (url: string | null): string | null => {
  if (!url) return null
  try { return decodeURIComponent(url.replace(/\/+$/, '').split('/').pop() || '') || null } catch { return null }
}

function Fact({ label, value, ltr, edit }: { label: string; value: React.ReactNode; ltr?: boolean; edit?: React.ReactNode }) {
  return (
    <div className="ih-prof-fact">
      <span className="ih-prof-fact__l">{label}</span>
      {edit !== undefined
        ? <span className="ih-prof-fact__edit">{edit}</span>
        : <span className="ih-prof-fact__v" style={ltr ? { direction: 'ltr', textAlign: 'end' } : undefined}>{value}</span>}
    </div>
  )
}

/**
 * الملفّ الكامل لمؤثر القاعدة — صفحة تبويبات احترافية لمدير النظام.
 * تعرض بيانات حقيقية فقط (سورسنغ/تسعير/تواصل)، بلا بيانات بنكية أو سجلّ حملات مُلفّق.
 */
const numOr = (v: string | number | null): number | null =>
  v === '' || v == null ? null : Number(v)

export default function CreatorProfile({ creator: c, clients }: Props) {
  const [tab, setTab] = useState('overview')
  const [transferOpen, setTransferOpen] = useState(false)
  const [clientId, setClientId] = useState('')

  // تعديل الأسعار — قيم بالريال، فارغ = بلا سعر
  const initPrices = () => ({
    cost_post: c.costPost ?? '', sell_post: c.sellPost ?? '',
    cost_coverage: c.costCoverage ?? '', sell_coverage: c.sellCoverage ?? '',
  })
  const [editPrice, setEditPrice] = useState(false)
  const [pf, setPf] = useState<Record<string, string | number>>(initPrices())
  const [saving, setSaving] = useState(false)
  const setP = (k: string, v: string) => setPf((p) => ({ ...p, [k]: v }))
  const savePricing = () => {
    setSaving(true)
    router.post(u(`/creator-pool/${c.id}/pricing`), pf, {
      preserveScroll: true,
      onFinish: () => setSaving(false),
      onSuccess: () => setEditPrice(false),
    })
  }

  // تعديل بيانات الملفّ العامّة
  const initProfile = () => ({
    name: c.name, tier: c.tier ?? '', rating: c.rating ?? '', gender: c.gender ?? '',
    city: c.city ?? '', region: c.region ?? '', shows_face: c.showsFace,
    followers: c.followers ?? '', account_url: c.accountUrl ?? '', phone: c.phone ?? '',
    store: c.store ?? '', source_type: c.sourceType,
  })
  const [editProfile, setEditProfile] = useState(false)
  const [ef, setEf] = useState<Record<string, string | number | boolean | null>>(initProfile())
  const [savingP, setSavingP] = useState(false)
  const setE = (k: string, v: string | boolean | null) => setEf((p) => ({ ...p, [k]: v }))
  const startEdit = () => { setEf(initProfile()); setEditProfile(true); setTab('overview') }
  const saveProfile = () => {
    setSavingP(true)
    router.post(u(`/creator-pool/${c.id}/profile`), ef as Record<string, string>, {
      preserveScroll: true,
      onFinish: () => setSavingP(false),
      onSuccess: () => setEditProfile(false),
    })
  }

  const transfer = () => {
    if (!clientId) return
    router.post(u('/creator-pool/transfer'), { client_id: clientId, pool_ids: [c.id] },
      { onSuccess: () => { setTransferOpen(false); setClientId('') } })
  }

  const tierC = c.tier ? (TIER_COLOR[c.tier] ?? TIER_COLOR.C) : 'var(--ih-gray-400)'
  const handle = handleOf(c.accountUrl)

  // مُدخلات التعديل (تظهر فقط في وضع التعديل)
  const iTxt = (k: string, ltr?: boolean) => (
    <input className="field ih-edit-input" value={String(ef[k] ?? '')} onChange={(e) => setE(k, e.target.value)}
      style={ltr ? { direction: 'ltr' } : undefined} />
  )
  const iNum = (k: string) => (
    <input className="field ih-edit-input" type="number" min="0" inputMode="numeric" value={String(ef[k] ?? '')}
      onChange={(e) => setE(k, e.target.value)} style={{ direction: 'ltr' }} />
  )
  const iSel = (k: string, opts: [string, string][]) => (
    <select className="field ih-edit-input" value={String(ef[k] ?? '')} onChange={(e) => setE(k, e.target.value)}>
      {opts.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
    </select>
  )
  const iFace = (
    <select className="field ih-edit-input" value={ef.shows_face === true ? '1' : ef.shows_face === false ? '0' : ''}
      onChange={(e) => setE('shows_face', e.target.value === '1' ? true : e.target.value === '0' ? false : null)}>
      <option value="">—</option><option value="1">نعم</option><option value="0">لا</option>
    </select>
  )

  const tabs = [
    { key: 'overview', label: 'نظرة عامة', icon: 'layout-dashboard' as const },
    { key: 'pricing', label: 'المنصّة والتسعير', icon: 'wallet' as const },
    { key: 'contact', label: 'التواصل', icon: 'phone' as const },
    { key: 'campaigns', label: 'الحملات', icon: 'megaphone' as const },
  ]

  return (
    <AppShell heading={c.name} nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title={c.name} />

      <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '.8rem' }}>
        <Link href={u('/creator-pool')} className="btn btn-sm btn-outline"><Icon name="chevron-left" size={15} /> العودة للقائمة</Link>
      </div>

      {/* رأس الملفّ */}
      <div className="ih-prof-hero" style={{ ['--tier' as string]: tierC }}>
        <div className="ih-prof-hero__id">
          <span className="ih-prof-hero__av" style={{ background: tierC }}>{c.name.slice(0, 1)}</span>
          <div style={{ minWidth: 0 }}>
            <h1 className="ih-prof-hero__name">{c.name}</h1>
            <div className="ih-prof-hero__badges">
              <TierBadge tier={c.tier} />
              {c.gender && <span className="badge">{GENDER[c.gender] ?? c.gender}</span>}
              <UgcBadge source={c.sourceType} />
              {c.categories.slice(0, 1).map((cat) => <span key={cat} className="ih-tag">{cat}</span>)}
              {(c.city || c.region) && <span className="ih-tag"><Icon name="map-pin" size={11} /> {[c.city, c.region].filter(Boolean).join(' · ')}</span>}
            </div>
          </div>
          <div className="ih-prof-hero__actions">
            {editProfile ? (
              <>
                <button className="btn btn-sm btn-ghost" onClick={() => setEditProfile(false)}>إلغاء</button>
                <button className="btn btn-sm btn-primary" disabled={savingP} onClick={saveProfile}><Icon name="check" size={14} /> {savingP ? 'جارٍ الحفظ…' : 'حفظ التعديلات'}</button>
              </>
            ) : (
              <>
                <button className="btn btn-sm btn-outline" onClick={startEdit}><Icon name="pencil" size={14} /> تعديل</button>
                <button className="btn btn-sm btn-primary" onClick={() => setTransferOpen(true)}><Icon name="share" size={14} /> تحويل إلى عميل</button>
                {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noopener noreferrer" className="btn btn-sm btn-outline"><Icon name="external-link" size={14} /> الحساب</a>}
              </>
            )}
          </div>
        </div>
        <div className="ih-prof-hero__kpis">
          <div className="ih-prof-kpi"><span className="ih-prof-kpi__v" style={{ direction: 'ltr' }}>{fnum(c.followers)}</span><span className="ih-prof-kpi__l">إجمالي المتابعين</span></div>
          <div className="ih-prof-kpi"><span className="ih-prof-kpi__v" style={{ direction: 'ltr' }}>{fnum(c.likes)}</span><span className="ih-prof-kpi__l">الإعجابات</span></div>
          <div className="ih-prof-kpi"><span className="ih-prof-kpi__v" style={{ direction: 'ltr', color: 'var(--ih-success-700, #067647)' }}>{c.sellCoverage != null ? `${sar(c.sellCoverage)}` : '—'}</span><span className="ih-prof-kpi__l">سعر التغطية (بيع)</span></div>
          <div className="ih-prof-kpi"><span className="ih-prof-kpi__v">{c.rating ? `★ ${c.rating}` : (c.tier ? `فئة ${c.tier}` : '—')}</span><span className="ih-prof-kpi__l">{c.rating ? 'التقييم' : 'الفئة'}</span></div>
        </div>
      </div>

      <WorkTabs tabs={tabs} active={tab} onChange={setTab} />

      {/* نظرة عامة */}
      {tab === 'overview' && (
        <div className="ih-prof-grid">
          <div className="ih-sec">
            <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="clipboard-check" size={16} /> البيانات الأساسية</span></div>
            <div className="ih-prof-facts">
              <Fact label="الاسم الكامل" value={c.name} edit={editProfile ? iTxt('name') : undefined} />
              <Fact label="الحساب" value={handle ?? '—'} ltr edit={editProfile ? iTxt('account_url', true) : undefined} />
              <Fact label="المنصّة" value={<PlatformTag label={c.platformLabel} />} />
              <Fact label="الفئة" value={c.tier ? `${c.tier}${c.tier === 'A' ? ' (VIP)' : ''}` : '—'} edit={editProfile ? iSel('tier', [['', '—'], ['A', 'A'], ['B', 'B'], ['C', 'C']]) : undefined} />
              <Fact label="الجنس" value={c.gender ? (GENDER[c.gender] ?? c.gender) : '—'} edit={editProfile ? iSel('gender', [['', '—'], ['female', 'أنثى'], ['male', 'ذكر']]) : undefined} />
              <Fact label="المصدر" value={c.sourceType === 'ugc' ? 'UGC' : 'مشاهير'} edit={editProfile ? iSel('source_type', [['celebrity', 'مشاهير'], ['ugc', 'UGC']]) : undefined} />
              <Fact label="المدينة" value={c.city ?? '—'} edit={editProfile ? iTxt('city') : undefined} />
              <Fact label="المنطقة" value={c.region ?? '—'} edit={editProfile ? iTxt('region') : undefined} />
              <Fact label="يظهر وجهه؟" value={c.showsFace == null ? '—' : c.showsFace ? 'نعم' : 'لا'} edit={editProfile ? iFace : undefined} />
            </div>
            {c.categories.length > 0 && (
              <div style={{ marginTop: '.8rem' }}>
                <div className="ih-prof-fact__l" style={{ marginBottom: '.4rem' }}>المجالات</div>
                <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
                  {c.categories.map((cat) => <span key={cat} className="ih-tag">{cat}</span>)}
                </div>
              </div>
            )}
          </div>

          <div className="ih-sec">
            <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="users" size={16} /> الجمهور والأداء</span></div>
            <div className="ih-prof-facts">
              <Fact label="إجمالي المتابعين" value={fnum(c.followers)} ltr edit={editProfile ? iNum('followers') : undefined} />
              <Fact label="الإعجابات" value={fnum(c.likes)} ltr />
              <Fact label="التقييم" value={c.rating ? `★ ${c.rating}` : '—'} edit={editProfile ? iTxt('rating') : undefined} />
              <Fact label="الفئة" value={c.tier ?? '—'} />
            </div>
          </div>
        </div>
      )}

      {/* المنصّة والتسعير — قابل للتعديل والاعتماد */}
      {tab === 'pricing' && (
        <div className="ih-sec">
          <div className="ih-sec__head">
            <span className="ih-sec__title" style={{ color: platColor(c.platformLabel) }}><Icon name="wallet" size={16} /> {c.platformLabel} — التسعير</span>
            {!editPrice ? (
              <button className="btn btn-xs btn-outline" onClick={() => { setPf(initPrices()); setEditPrice(true) }}><Icon name="pencil" size={13} /> تعديل الأسعار</button>
            ) : (
              <span style={{ display: 'flex', gap: '.4rem' }}>
                <button className="btn btn-xs btn-ghost" onClick={() => { setEditPrice(false); setPf(initPrices()) }}>إلغاء</button>
                <button className="btn btn-xs btn-primary" disabled={saving} onClick={savePricing}><Icon name="check" size={13} /> {saving ? 'جارٍ الحفظ…' : 'حفظ واعتماد'}</button>
              </span>
            )}
          </div>

          <div className="ih-prof-price">
            {([['منشور', 'cost_post', 'sell_post', c.costPost, c.sellPost],
              ['تغطية', 'cost_coverage', 'sell_coverage', c.costCoverage, c.sellCoverage]] as const).map(([label, ck, sk, vcost, vsell]) => {
              const cost = editPrice ? numOr(pf[ck]) : vcost
              const sell = editPrice ? numOr(pf[sk]) : vsell
              return (
                <div key={label} className="ih-prof-price__card">
                  <div className="ih-prof-price__t">{label}</div>
                  <div className="ih-prof-price__row">
                    <span className="ih-prof-fact__l">التكلفة (شراء)</span>
                    {editPrice
                      ? <input className="field ih-price-input" type="number" min="0" inputMode="numeric" value={pf[ck]} onChange={(e) => setP(ck, e.target.value)} placeholder="—" />
                      : <b style={{ direction: 'ltr' }}>{sar(cost)} ر.س</b>}
                  </div>
                  <div className="ih-prof-price__row">
                    <span className="ih-prof-fact__l">البيع</span>
                    {editPrice
                      ? <input className="field ih-price-input" type="number" min="0" inputMode="numeric" value={pf[sk]} onChange={(e) => setP(sk, e.target.value)} placeholder="—" />
                      : <b style={{ direction: 'ltr', color: 'var(--ih-success-700, #067647)' }}>{sar(sell)} ر.س</b>}
                  </div>
                  <div className="ih-prof-price__row" style={{ borderTop: '1px dashed var(--ih-border)', marginTop: '.3rem', paddingTop: '.4rem' }}>
                    <span className="ih-prof-fact__l">الهامش (الشركة)</span>
                    <b style={{ direction: 'ltr', color: margin(sell, cost) != null && (margin(sell, cost) as number) < 0 ? 'var(--ih-danger-ink)' : 'var(--ih-primary-700)' }}>{sar(margin(sell, cost))} ر.س</b>
                  </div>
                </div>
              )
            })}
          </div>
          {editPrice && (
            <p style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginTop: '.7rem' }}>
              القيم بالريال. اترك الحقل فارغًا لإزالة السعر. الهامش يُحسب تلقائيًّا (البيع − التكلفة).
            </p>
          )}
        </div>
      )}

      {/* التواصل — بلا بيانات بنكية */}
      {tab === 'contact' && (
        <div className="ih-sec">
          <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="phone" size={16} /> التواصل والموقع</span></div>
          <div className="ih-prof-facts">
            <Fact label="الجوّال" value={c.phone ?? '—'} ltr edit={editProfile ? iTxt('phone', true) : undefined} />
            <Fact label="جهة الحجز" value={c.store ?? '—'} edit={editProfile ? iTxt('store') : undefined} />
            <Fact label="المدينة" value={c.city ?? '—'} />
            <Fact label="المنطقة" value={c.region ?? '—'} />
          </div>
          <div style={{ display: 'flex', gap: '.5rem', marginTop: '1rem', flexWrap: 'wrap' }}>
            {c.phone && <a href={waLink(c.phone, c.name)} target="_blank" rel="noopener noreferrer" className="btn btn-sm ih-wa"><Icon name="message-circle" size={15} /> حجز عبر واتساب</a>}
            {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noopener noreferrer" className="btn btn-sm btn-outline"><Icon name="external-link" size={15} /> فتح الحساب</a>}
          </div>
        </div>
      )}

      {/* الحملات — حالة فارغة صادقة */}
      {tab === 'campaigns' && (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="megaphone" size={26} /></span>
          <div className="ih-empty__title">لا حملات مسجّلة بعد</div>
          <div className="ih-empty__text">مؤثرو القاعدة مستوردون للترشيح. يظهر هنا سجلّ الحجوزات والحملات والأداء الشهري بعد تحويل المؤثر إلى عميل وتنفيذ حملة عبر المنصّة.</div>
        </div>
      )}

      {transferOpen && (
        <div className="modal-backdrop" role="dialog" aria-modal="true" aria-label="تحويل إلى عميل"
          onClick={(e) => { if (e.target === e.currentTarget) setTransferOpen(false) }}>
          <div className="modal" style={{ width: 'min(440px, 100%)', padding: '1.3rem' }}>
            <h3 style={{ margin: '0 0 .3rem' }}>تحويل «{c.name}» إلى عميل</h3>
            <p style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)', marginBlockEnd: '1rem' }}>
              تُنشأ توصية للعميل بنسخة مستقلّة عن القاعدة — بلا الجوّال. يبقى للعميل قرار القبول أو الرفض.
            </p>
            <label style={{ display: 'grid', gap: '.3rem' }}>
              <span style={{ fontSize: '.82rem', fontWeight: 600 }}>العميل</span>
              <select className="field" value={clientId} onChange={(e) => setClientId(e.target.value)} autoFocus>
                <option value="">اختر عميلًا…</option>
                {clients.map((cl) => <option key={cl.id} value={cl.id}>{cl.name}</option>)}
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
