import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Contact { phone: string | null; phoneDisplay: string | null; whatsapp: string | null; hasPhone: boolean }
interface Creator {
  id: number; name: string; platform: string; platformLabel: string; accountUrl: string | null;
  followers: number | null; likes: number | null; tier: string | null; gender: string | null;
  categories: string[]; showsFace: boolean | null; region: string | null; city: string | null;
  rating: string | null; creatorType: string; creatorTypeLabel: string;
  referenceRate: number | null; referenceRateNote: string; dataFreshness: string; lastImportedAt: string | null;
  contact?: Contact;
  shortlistRole?: 'primary' | 'backup' | null;
}
interface CampaignContext { id: number; name: string; primaryCount: number; backupCount: number; editable: boolean; shortlistUrl: string }
interface Filters { platform?: string; creator_type?: string; category?: string; city?: string; region?: string; gender?: string; shows_face?: string; tier?: string; min_followers?: string; has_price?: string; q?: string; sort?: string }

const SORTS: { value: string; label: string }[] = [
  { value: 'followers', label: 'الأكثر متابعة' },
  { value: 'price', label: 'الأعلى سعرًا' },
  { value: 'recent', label: 'الأحدث بيانات' },
];
const TOP_CATEGORIES = 8;
interface Props {
  base: string;
  creators: Paginated<Creator>;
  filters: Filters;
  canContact: boolean;
  canUseInCampaign: boolean;
  facets: { platforms: Record<string, number>; creatorTypes: Record<string, number>; categories: Record<string, number>; regions: Record<string, number>; tiers: Record<string, number> };
  summary: { total: number };
  campaignContext?: CampaignContext | null;
}

const PLATFORM_LABELS: Record<string, string> = { snapchat: 'سناب شات', tiktok: 'تيك توك', linkedin: 'لينكدإن', x: 'إكس', instagram: 'إنستغرام' };
function kfmt(n: number | null): string {
  if (n === null) return '—';
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1000) return Math.round(n / 1000) + 'K';
  return String(n);
}
function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function CreatorDatabaseIndex({ creators, filters, canContact, canUseInCampaign, facets, summary, campaignContext }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  // فلاتر متقدّمة مخفية افتراضيًّا (تقليل العبء البصري) — تُفتح تلقائيًّا إن كان أحدها مفعّلًا
  const advActive = Boolean(filters.creator_type || filters.tier || filters.gender || filters.has_price);
  const [showAdvanced, setShowAdvanced] = useState(advActive);
  const [showAllCats, setShowAllCats] = useState(false);
  // سياق الحملة يُحفَظ في كل تنقّل تصفية/ترتيب حتى لا يضيع التدفّق نحو الترشيح
  const ctx = campaignContext ? { campaign: String(campaignContext.id) } : {};
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/creator-database'), clean({ ...filters, ...ctx, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/creator-database'), clean({ ...filters, ...ctx, ...patch }), { preserveState: true, replace: true, preserveScroll: true });
  const resetAll = () => { setQ(''); setShowAdvanced(false); setShowAllCats(false); router.get(u('/creator-database'), clean({ ...ctx }), { preserveScroll: true }); };

  // ترشيح مباشر من الاكتشاف (أساسي/احتياط) بلا قفزة صفحة — back() يُحدِّث الأعداد والحالة
  const [nomBusy, setNomBusy] = useState(0);
  const nominate = (cr: Creator, role: 'primary' | 'backup') => {
    if (!campaignContext) return;
    setNomBusy(cr.id);
    router.post(u(`/creator-database/${cr.id}/nominate`), { campaign_id: campaignContext.id, role },
      { preserveScroll: true, preserveState: true, onFinish: () => setNomBusy(0) });
  };
  // «الترتيب» ليس فلترًا نشطًا — يُستثنى من عدّاد الفلاتر ومن «مسح الكل»
  const activeCount = Object.entries(filters).filter(([k, v]) => k !== 'sort' && v !== '' && v != null).length;
  const categoryEntries = Object.entries(facets.categories ?? {});
  const shownCats = showAllCats ? categoryEntries : categoryEntries.slice(0, TOP_CATEGORIES);
  const currentSort = filters.sort ?? 'followers';

  // مقارنة خفيفة: حتى 4 مؤثرين. الحالة محليّة وتبقى عبر الترقيم/التصفية (preserveState).
  const [compare, setCompare] = useState<Creator[]>([]);
  const [showCompare, setShowCompare] = useState(false);
  const inCompare = (id: number) => compare.some((x) => x.id === id);
  const toggleCompare = (cr: Creator) =>
    setCompare((prev) => (inCompare(cr.id) ? prev.filter((x) => x.id !== cr.id) : prev.length >= 4 ? prev : [...prev, cr]));

  const copyPhone = (p: string) => navigator.clipboard?.writeText(p);
  const waLink = (p: string) => `https://wa.me/${p}`;

  return (
    <AppShell heading="قاعدة المؤثرين">
      <Head title="قاعدة المؤثرين" />

      <div className="ih-listhead">
        <div>
          <div className="ih-listhead__eyebrow">اكتشاف المبدعين · منتج مميّز</div>
          <h1 className="ih-listhead__title">قاعدة المؤثرين</h1>
          <p className="ih-listhead__sub">قاعدة مؤثرين واسعة داخل المنصّة — ابحث، تواصل، واحفظ علاقتك ورشّح مباشرةً لحملتك.</p>
        </div>
        <div className="ih-listhead__meta" style={{ color: 'var(--ih-text-muted)', fontSize: '.82rem' }}>{summary.total.toLocaleString('en-US')} مبدع</div>
      </div>

      {/* شريط سياق الحملة — لاصق أعلى الصفحة: الاكتشاف يتدفّق مباشرةً إلى الترشيح */}
      {campaignContext && (
        <div className="ih-nom-context" role="region" aria-label="سياق الترشيح للحملة">
          <div style={{ minWidth: 0 }}>
            <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>ترشيح لحملة</div>
            <div style={{ fontWeight: 800, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{campaignContext.name}</div>
          </div>
          <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center' }}>
            <span className="ih-tag" title="المرشّحون الأساسيّون">الأساسي {campaignContext.primaryCount}</span>
            <span className="ih-tag" title="مرشّحو الاحتياط">الاحتياط {campaignContext.backupCount}</span>
          </div>
          <a href={campaignContext.shortlistUrl} className="btn btn-sm btn-primary" style={{ marginInlineStart: 'auto' }}>
            <Icon name="clipboard-check" size={14} /> مراجعة القائمة
          </a>
        </div>
      )}
      {campaignContext && !campaignContext.editable && (
        <div className="ih-note" style={{ marginBottom: '.6rem' }}>
          <Icon name="clipboard-check" size={14} /> هذه القائمة أُرسلت للعميل — لإضافة مرشّحين أنشئ إصدارًا جديدًا من مساحة الترشيح.
        </div>
      )}

      {/* الشريط الأساسي: عناصر التحكّم الأعلى قيمة فقط — بحث · منصّة · موقع · ترتيب · فلاتر إضافية */}
      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالاسم أو المدينة أو الحساب…" />
        </label>
        <select className="field" style={{ maxWidth: 130 }} value={filters.platform ?? ''} onChange={(e) => update({ platform: e.target.value })} aria-label="المنصّة">
          <option value="">كل المنصّات</option>
          {Object.keys(facets.platforms).map((p) => <option key={p} value={p}>{PLATFORM_LABELS[p] ?? p}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 130 }} value={filters.region ?? ''} onChange={(e) => update({ region: e.target.value })} aria-label="الموقع">
          <option value="">كل المناطق</option>
          {Object.keys(facets.regions).map((rg) => <option key={rg} value={rg}>{rg}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 150 }} value={currentSort} onChange={(e) => update({ sort: e.target.value })} aria-label="ترتيب النتائج" title="ترتيب النتائج">
          {SORTS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </select>
        <button
          onClick={() => setShowAdvanced((v) => !v)}
          className={`btn btn-sm btn-outline${advActive ? ' active' : ''}`}
          aria-expanded={showAdvanced}
          title="فلاتر إضافية"
        >
          <Icon name="sliders-horizontal" size={14} /> فلاتر إضافية{advActive ? ' •' : ''}
        </button>
        {activeCount > 0 && (
          <button onClick={resetAll} className="btn btn-sm btn-outline" title="مسح كل الفلاتر">
            <Icon name="x" size={14} /> مسح الفلاتر ({activeCount})
          </button>
        )}
      </div>

      {/* لوحة الفلاتر المتقدّمة — مخفيّة افتراضيًّا كي لا يُغرَق الشريط الأساسي */}
      {showAdvanced && (
        <div className="ih-filterbar" style={{ marginTop: '.4rem', paddingTop: '.6rem', borderTop: '1px solid var(--ih-border)' }}>
          <select className="field" style={{ maxWidth: 140 }} value={filters.creator_type ?? ''} onChange={(e) => update({ creator_type: e.target.value })} aria-label="نوع المبدع">
            <option value="">كل الأنواع</option>
            <option value="celebrity">مؤثّر</option>
            <option value="ugc">صانع UGC</option>
          </select>
          <select className="field" style={{ maxWidth: 110 }} value={filters.tier ?? ''} onChange={(e) => update({ tier: e.target.value })} aria-label="الفئة">
            <option value="">كل الفئات</option>
            {Object.keys(facets.tiers).map((t) => <option key={t} value={t}>فئة {t}</option>)}
          </select>
          <select className="field" style={{ maxWidth: 110 }} value={filters.gender ?? ''} onChange={(e) => update({ gender: e.target.value })} aria-label="الجنس">
            <option value="">الجنس</option>
            <option value="female">أنثى</option>
            <option value="male">ذكر</option>
          </select>
          <select className="field" style={{ maxWidth: 130 }} value={filters.has_price ?? ''} onChange={(e) => update({ has_price: e.target.value })} aria-label="توفّر السعر">
            <option value="">السعر</option>
            <option value="1">سعر متاح</option>
          </select>
        </div>
      )}

      {/* التصنيفات: الأكثر استخدامًا فقط + «عرض الكل» — لا جدار رقائق. أعداد حقيقية. */}
      {categoryEntries.length > 0 && (
        <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', margin: '.6rem 0 1rem', alignItems: 'center' }}>
          <button
            onClick={() => update({ category: '' })}
            className="ih-chip"
            aria-pressed={!filters.category}
            style={!filters.category ? { background: 'var(--ih-primary)', color: '#fff', borderColor: 'var(--ih-primary)' } : undefined}
          >
            الكل
          </button>
          {shownCats.map(([cat, count]) => {
            const active = filters.category === cat;
            return (
              <button
                key={cat}
                onClick={() => update({ category: active ? '' : cat })}
                className="ih-chip"
                aria-pressed={active}
                style={active ? { background: 'var(--ih-primary)', color: '#fff', borderColor: 'var(--ih-primary)' } : undefined}
              >
                {cat} <span className="ih-chip__count">{count.toLocaleString('en-US')}</span>
              </button>
            );
          })}
          {categoryEntries.length > TOP_CATEGORIES && (
            <button onClick={() => setShowAllCats((v) => !v)} className="ih-chip" style={{ borderStyle: 'dashed' }}>
              {showAllCats ? 'عرض أقل' : `عرض جميع التصنيفات (${categoryEntries.length})`}
            </button>
          )}
        </div>
      )}

      {creators.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="users" size={26} /></span>
          <div className="ih-empty__title">لا مبدعين مطابقين</div>
          <div className="ih-empty__text">لا نتائج للبحث أو الفلاتر الحالية.</div>
          <a href={u('/creator-database')} className="btn btn-sm btn-outline">مسح الفلاتر</a>
        </div></div>
      ) : (
        <div className="ih-mlist">
          {creators.data.map((c) => (
            <div key={c.id} className="ih-mcard">
              <div className="ih-mcard__top">
                <span className="ih-idc__av" style={{ width: 42, height: 42 }}>{c.name.slice(0, 1)}</span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <a href={u(`/creator-database/${c.id}`)} className="ih-idc__name" style={{ textDecoration: 'none' }}>{c.name}</a>
                  <div className="ih-idc__sub">
                    <span className="ih-tag">{c.platformLabel}</span>{' '}
                    <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{c.creatorTypeLabel}</span>
                    {c.tier && <> · فئة {c.tier}</>}{c.city && <> · {c.city}</>}
                  </div>
                </div>
              </div>
              <div className="ih-mcard__grid">
                <div className="ih-metric"><span className="ih-metric__v">{kfmt(c.followers)}</span><span className="ih-metric__k">متابع</span></div>
                <div className="ih-metric"><span className="ih-metric__v">{kfmt(c.likes)}</span><span className="ih-metric__k">إعجاب</span></div>
                <div className="ih-metric"><span className="ih-metric__v">{c.showsFace ? 'نعم' : '—'}</span><span className="ih-metric__k">يظهر الوجه</span></div>
              </div>
              {c.categories.length > 0 && (
                <div style={{ marginTop: '.5rem', display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
                  {c.categories.slice(0, 4).map((cat, i) => (
                    <button key={i} onClick={() => update({ category: cat })} className="ih-tag" style={{ cursor: 'pointer', border: 0 }} title={`تصفية: ${cat}`}>{cat}</button>
                  ))}
                </div>
              )}
              <div style={{ marginTop: '.5rem', fontSize: '.8rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ color: 'var(--ih-text-muted)' }} title={c.referenceRateNote}>السعر المرجعي</span>
                {c.referenceRate != null
                  ? <span style={{ fontWeight: 700 }}>{c.referenceRate.toLocaleString('en-US')} ر.س</span>
                  : <span className="ih-tag" style={{ color: 'var(--ih-warning-ink)' }}>السعر غير مضاف</span>}
              </div>
              <div style={{ marginTop: '.6rem', display: 'flex', gap: '.4rem', alignItems: 'center', flexWrap: 'wrap' }}>
                <a href={u(`/creator-database/${c.id}`)} className="btn btn-xs btn-outline">الملف</a>
                <button
                  onClick={() => toggleCompare(c)}
                  disabled={!inCompare(c.id) && compare.length >= 4}
                  className={`btn btn-xs${inCompare(c.id) ? ' btn-primary' : ' btn-outline'}`}
                  aria-pressed={inCompare(c.id)}
                  title={!inCompare(c.id) && compare.length >= 4 ? 'الحد الأقصى 4 للمقارنة' : 'أضِف للمقارنة'}
                >
                  {inCompare(c.id) ? '✓ في المقارنة' : 'قارن'}
                </button>
                {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noreferrer" className="btn btn-xs btn-outline">الحساب</a>}
                {canContact && c.contact?.hasPhone && (
                  <>
                    <button onClick={() => copyPhone(c.contact!.phone!)} className="btn btn-xs">نسخ الجوال</button>
                    <a href={waLink(c.contact.whatsapp!)} target="_blank" rel="noreferrer" className="btn btn-xs btn-primary">واتساب</a>
                  </>
                )}
                {/* بسياق حملة: ترشيح بنقرة (أساسي/احتياط)؛ بلا سياق: انتقال للملف لاختيار حملة */}
                {canUseInCampaign && campaignContext && campaignContext.editable && (
                  c.shortlistRole ? (
                    <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)', fontWeight: 700 }}>
                      ✓ {c.shortlistRole === 'backup' ? 'احتياط' : 'أساسي'} — اضغط للتبديل
                      <button onClick={() => nominate(c, c.shortlistRole === 'backup' ? 'primary' : 'backup')} disabled={nomBusy === c.id}
                        className="btn btn-xs" style={{ marginInlineStart: '.3rem', padding: '0 .3rem' }} title="بدّل بين أساسي/احتياط">↺</button>
                    </span>
                  ) : (
                    <>
                      <button onClick={() => nominate(c, 'primary')} disabled={nomBusy === c.id} className="btn btn-xs btn-primary">+ أساسي</button>
                      <button onClick={() => nominate(c, 'backup')} disabled={nomBusy === c.id} className="btn btn-xs btn-secondary">+ احتياط</button>
                    </>
                  )
                )}
                {canUseInCampaign && !campaignContext && <a href={u(`/creator-database/${c.id}`)} className="btn btn-xs btn-secondary">ترشيح لحملة</a>}
              </div>
            </div>
          ))}
        </div>
      )}

      <div style={{ marginTop: '1rem', paddingBottom: compare.length > 0 ? 72 : 0 }}><Pagination links={creators.links} /></div>

      {/* شريط المقارنة اللاصق — يظهر عند اختيار مؤثر واحد على الأقل */}
      {compare.length > 0 && (
        <div className="ih-comparebar" role="region" aria-label="شريط المقارنة">
          <span style={{ fontWeight: 700 }}>{`تم اختيار ${compare.length} ${compare.length === 1 ? 'مؤثر' : 'مؤثرين'}`}</span>
          <div style={{ display: 'flex', gap: '.4rem', alignItems: 'center', flexWrap: 'wrap' }}>
            {compare.map((cr) => (
              <span key={cr.id} className="ih-tag" style={{ display: 'inline-flex', alignItems: 'center', gap: '.25rem' }}>
                {cr.name}
                <button onClick={() => toggleCompare(cr)} aria-label={`إزالة ${cr.name}`} style={{ border: 0, background: 'none', cursor: 'pointer', lineHeight: 1, padding: 0 }}>
                  <Icon name="x" size={12} />
                </button>
              </span>
            ))}
          </div>
          <div style={{ marginInlineStart: 'auto', display: 'flex', gap: '.4rem' }}>
            <button onClick={() => setShowCompare(true)} disabled={compare.length < 2} className="btn btn-sm btn-primary" title={compare.length < 2 ? 'اختر مؤثرَين على الأقل' : 'قارن'}>
              مقارنة ({compare.length})
            </button>
            <button onClick={() => { setCompare([]); setShowCompare(false); }} className="btn btn-sm btn-outline">إلغاء التحديد</button>
          </div>
        </div>
      )}

      {/* لوحة المقارنة — أبعاد قرارية من بيانات حقيقية فقط (لا مقاييس مُختلَقة) */}
      {showCompare && compare.length >= 2 && (
        <div className="ih-modal-backdrop" role="dialog" aria-modal="true" aria-label="مقارنة المؤثرين" onClick={() => setShowCompare(false)}>
          <div className="ih-modal" style={{ maxWidth: 860 }} onClick={(e) => e.stopPropagation()}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '.8rem' }}>
              <h3 style={{ fontWeight: 800, margin: 0 }}>مقارنة {compare.length} مؤثرين</h3>
              <button onClick={() => setShowCompare(false)} className="ih-icon-btn" aria-label="إغلاق"><Icon name="x" size={18} /></button>
            </div>
            <div style={{ overflowX: 'auto' }}>
              <table className="ih-compare-table">
                <thead>
                  <tr>
                    <th style={{ textAlign: 'start' }}>البُعد</th>
                    {compare.map((cr) => (
                      <th key={cr.id} style={{ minWidth: 140 }}>
                        <a href={u(`/creator-database/${cr.id}`)} style={{ textDecoration: 'none', fontWeight: 700 }}>{cr.name}</a>
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {([
                    ['المنصّة', (cr: Creator) => cr.platformLabel],
                    ['النوع', (cr: Creator) => cr.creatorTypeLabel],
                    ['المتابعون', (cr: Creator) => kfmt(cr.followers)],
                    ['الإعجابات', (cr: Creator) => kfmt(cr.likes)],
                    ['الفئة', (cr: Creator) => (cr.tier ? `فئة ${cr.tier}` : '—')],
                    ['الموقع', (cr: Creator) => cr.city || cr.region || '—'],
                    ['يظهر الوجه', (cr: Creator) => (cr.showsFace === null ? '—' : cr.showsFace ? 'نعم' : 'لا')],
                    ['التقييم', (cr: Creator) => cr.rating || '—'],
                    ['التصنيفات', (cr: Creator) => (cr.categories.length ? cr.categories.slice(0, 3).join('، ') : '—')],
                    ['السعر المرجعي', (cr: Creator) => (cr.referenceRate != null ? `${cr.referenceRate.toLocaleString('en-US')} ر.س` : 'غير مضاف')],
                  ] as [string, (cr: Creator) => string][]).map(([label, val]) => (
                    <tr key={label}>
                      <td style={{ color: 'var(--ih-text-muted)', fontWeight: 600 }}>{label}</td>
                      {compare.map((cr) => <td key={cr.id} style={{ textAlign: 'center' }}>{val(cr)}</td>)}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p style={{ color: 'var(--ih-text-muted)', fontSize: '.74rem', marginTop: '.7rem' }}>
              أبعاد من بيانات القاعدة الفعلية فقط. القيم الغائبة تظهر «—» ولا تُقدَّر.
            </p>
          </div>
        </div>
      )}
    </AppShell>
  );
}
