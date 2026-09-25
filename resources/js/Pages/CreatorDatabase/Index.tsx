import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

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

const SORT_KEYS = ['followers', 'price', 'recent'];
const TOP_CATEGORIES = 8;
interface Props {
  base: string;
  creators: Paginated<Creator>;
  filters: Filters;
  canContact: boolean;
  canUseInCampaign: boolean;
  facets: { platforms: Record<string, number>; creatorTypes: Record<string, number>; categories: Record<string, number>; regions: Record<string, number>; tiers: Record<string, number> };
  platformLabels: Record<string, string>;
  summary: { total: number };
  campaignContext?: CampaignContext | null;
}

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

export default function CreatorDatabaseIndex({ creators, filters, canContact, canUseInCampaign, facets, platformLabels, summary, campaignContext }: Props) {
  const t = useT();
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
    <AppShell heading={t('creator_database.title')}>
      <Head title={t('creator_database.title')} />

      <div className="ih-listhead">
        <div>
          <div className="ih-listhead__eyebrow">{t('creator_database.eyebrow')}</div>
          <h1 className="ih-listhead__title">{t('creator_database.title')}</h1>
          <p className="ih-listhead__sub">{t('creator_database.sub')}</p>
        </div>
        <div className="ih-listhead__meta" style={{ color: 'var(--ih-text-muted)', fontSize: '.82rem' }}>{t('creator_database.count_item', { n: summary.total.toLocaleString('en-US') })}</div>
      </div>

      {/* شريط سياق الحملة — لاصق أعلى الصفحة: الاكتشاف يتدفّق مباشرةً إلى الترشيح */}
      {campaignContext && (
        <div className="ih-nom-context" role="region" aria-label={t('creator_database.ctx_aria')}>
          <div style={{ minWidth: 0 }}>
            <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{t('creator_database.ctx_label')}</div>
            <div style={{ fontWeight: 800, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{campaignContext.name}</div>
          </div>
          <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center' }}>
            <span className="ih-tag" title={t('creator_database.ctx_primary_title')}>{t('creator_database.ctx_primary', { n: campaignContext.primaryCount })}</span>
            <span className="ih-tag" title={t('creator_database.ctx_backup_title')}>{t('creator_database.ctx_backup', { n: campaignContext.backupCount })}</span>
          </div>
          <a href={campaignContext.shortlistUrl} className="btn btn-sm btn-primary" style={{ marginInlineStart: 'auto' }}>
            <Icon name="clipboard-check" size={14} /> {t('creator_database.ctx_review')}
          </a>
        </div>
      )}
      {campaignContext && !campaignContext.editable && (
        <div className="ih-note" style={{ marginBottom: '.6rem' }}>
          <Icon name="clipboard-check" size={14} /> {t('creator_database.ctx_locked')}
        </div>
      )}

      {/* الشريط الأساسي: عناصر التحكّم الأعلى قيمة فقط — بحث · منصّة · موقع · ترتيب · فلاتر إضافية */}
      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('creator_database.search_placeholder')} />
        </label>
        <select className="field" style={{ maxWidth: 130 }} value={filters.platform ?? ''} onChange={(e) => update({ platform: e.target.value })} aria-label={t('creator_database.f_platform')}>
          <option value="">{t('creator_database.all_platforms')}</option>
          {Object.keys(facets.platforms).map((p) => <option key={p} value={p}>{platformLabels[p] ?? p}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 130 }} value={filters.region ?? ''} onChange={(e) => update({ region: e.target.value })} aria-label={t('creator_database.f_region')}>
          <option value="">{t('creator_database.all_regions')}</option>
          {Object.keys(facets.regions).map((rg) => <option key={rg} value={rg}>{rg}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 150 }} value={currentSort} onChange={(e) => update({ sort: e.target.value })} aria-label={t('creator_database.f_sort')} title={t('creator_database.f_sort')}>
          {SORT_KEYS.map((s) => <option key={s} value={s}>{t(`creator_database.sort_${s}`)}</option>)}
        </select>
        <button
          onClick={() => setShowAdvanced((v) => !v)}
          className={`btn btn-sm btn-outline${advActive ? ' active' : ''}`}
          aria-expanded={showAdvanced}
          title={t('creator_database.more_filters')}
        >
          <Icon name="sliders-horizontal" size={14} /> {t('creator_database.more_filters')}{advActive ? ' •' : ''}
        </button>
        {activeCount > 0 && (
          <button onClick={resetAll} className="btn btn-sm btn-outline" title={t('creator_database.clear_filters_title')}>
            <Icon name="x" size={14} /> {t('creator_database.clear_filters')} ({activeCount})
          </button>
        )}
      </div>

      {/* لوحة الفلاتر المتقدّمة — مخفيّة افتراضيًّا كي لا يُغرَق الشريط الأساسي */}
      {showAdvanced && (
        <div className="ih-filterbar" style={{ marginTop: '.4rem', paddingTop: '.6rem', borderTop: '1px solid var(--ih-border)' }}>
          <select className="field" style={{ maxWidth: 140 }} value={filters.creator_type ?? ''} onChange={(e) => update({ creator_type: e.target.value })} aria-label={t('creator_database.f_type')}>
            <option value="">{t('creator_database.all_types')}</option>
            <option value="celebrity">{t('creator_database.type_celebrity')}</option>
            <option value="ugc">{t('creator_database.type_ugc')}</option>
          </select>
          <select className="field" style={{ maxWidth: 110 }} value={filters.tier ?? ''} onChange={(e) => update({ tier: e.target.value })} aria-label={t('creator_database.f_tier')}>
            <option value="">{t('creator_database.all_tiers')}</option>
            {Object.keys(facets.tiers).map((tk) => <option key={tk} value={tk}>{t('creator_database.tier_label', { t: tk })}</option>)}
          </select>
          <select className="field" style={{ maxWidth: 110 }} value={filters.gender ?? ''} onChange={(e) => update({ gender: e.target.value })} aria-label={t('creator_database.f_gender')}>
            <option value="">{t('creator_database.f_gender')}</option>
            <option value="female">{t('creator_database.gender_female')}</option>
            <option value="male">{t('creator_database.gender_male')}</option>
          </select>
          <select className="field" style={{ maxWidth: 130 }} value={filters.has_price ?? ''} onChange={(e) => update({ has_price: e.target.value })} aria-label={t('creator_database.f_price_aria')}>
            <option value="">{t('creator_database.f_price')}</option>
            <option value="1">{t('creator_database.price_available')}</option>
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
            {t('creator_database.cat_all')}
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
              {showAllCats ? t('creator_database.cat_less') : t('creator_database.cat_all_count', { n: categoryEntries.length })}
            </button>
          )}
        </div>
      )}

      {creators.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="users" size={26} /></span>
          <div className="ih-empty__title">{t('creator_database.empty_title')}</div>
          <div className="ih-empty__text">{t('creator_database.empty_text')}</div>
          <a href={u('/creator-database')} className="btn btn-sm btn-outline">{t('creator_database.clear_filters')}</a>
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
                    {c.tier && <> · {t('creator_database.tier_label', { t: c.tier })}</>}{c.city && <> · {c.city}</>}
                  </div>
                </div>
              </div>
              <div className="ih-mcard__grid">
                <div className="ih-metric"><span className="ih-metric__v">{kfmt(c.followers)}</span><span className="ih-metric__k">{t('creator_database.m_followers')}</span></div>
                <div className="ih-metric"><span className="ih-metric__v">{kfmt(c.likes)}</span><span className="ih-metric__k">{t('creator_database.m_likes')}</span></div>
                <div className="ih-metric"><span className="ih-metric__v">{c.showsFace ? t('creator_database.yes') : '—'}</span><span className="ih-metric__k">{t('creator_database.m_shows_face')}</span></div>
              </div>
              {c.categories.length > 0 && (
                <div style={{ marginTop: '.5rem', display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
                  {c.categories.slice(0, 4).map((cat, i) => (
                    <button key={i} onClick={() => update({ category: cat })} className="ih-tag" style={{ cursor: 'pointer', border: 0 }} title={t('creator_database.filter_by', { cat })}>{cat}</button>
                  ))}
                </div>
              )}
              <div style={{ marginTop: '.5rem', fontSize: '.8rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ color: 'var(--ih-text-muted)' }} title={c.referenceRateNote}>{t('creator_database.reference_rate')}</span>
                {c.referenceRate != null
                  ? <span style={{ fontWeight: 700 }}>{c.referenceRate.toLocaleString('en-US')} ر.س</span>
                  : <span className="ih-tag" style={{ color: 'var(--ih-warning-ink)' }}>{t('creator_database.rate_missing')}</span>}
              </div>
              <div style={{ marginTop: '.6rem', display: 'flex', gap: '.4rem', alignItems: 'center', flexWrap: 'wrap' }}>
                <a href={u(`/creator-database/${c.id}`)} className="btn btn-xs btn-outline">{t('creator_database.profile')}</a>
                <button
                  onClick={() => toggleCompare(c)}
                  disabled={!inCompare(c.id) && compare.length >= 4}
                  className={`btn btn-xs${inCompare(c.id) ? ' btn-primary' : ' btn-outline'}`}
                  aria-pressed={inCompare(c.id)}
                  title={!inCompare(c.id) && compare.length >= 4 ? t('creator_database.compare_max') : t('creator_database.compare_add')}
                >
                  {inCompare(c.id) ? t('creator_database.in_compare') : t('creator_database.compare')}
                </button>
                {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noreferrer" className="btn btn-xs btn-outline">{t('creator_database.account')}</a>}
                {canContact && c.contact?.hasPhone && (
                  <>
                    <button onClick={() => copyPhone(c.contact!.phone!)} className="btn btn-xs">{t('creator_database.copy_phone')}</button>
                    <a href={waLink(c.contact.whatsapp!)} target="_blank" rel="noreferrer" className="btn btn-xs btn-primary">{t('creator_database.whatsapp')}</a>
                  </>
                )}
                {/* بسياق حملة: ترشيح بنقرة (أساسي/احتياط)؛ بلا سياق: انتقال للملف لاختيار حملة */}
                {canUseInCampaign && campaignContext && campaignContext.editable && (
                  c.shortlistRole ? (
                    <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)', fontWeight: 700 }}>
                      {t('creator_database.in_shortlist_toggle', { role: c.shortlistRole === 'backup' ? t('creator_database.role_backup') : t('creator_database.role_primary') })}
                      <button onClick={() => nominate(c, c.shortlistRole === 'backup' ? 'primary' : 'backup')} disabled={nomBusy === c.id}
                        className="btn btn-xs" style={{ marginInlineStart: '.3rem', padding: '0 .3rem' }} title={t('creator_database.role_toggle_title')}>↺</button>
                    </span>
                  ) : (
                    <>
                      <button onClick={() => nominate(c, 'primary')} disabled={nomBusy === c.id} className="btn btn-xs btn-primary">{t('creator_database.add_primary')}</button>
                      <button onClick={() => nominate(c, 'backup')} disabled={nomBusy === c.id} className="btn btn-xs btn-secondary">{t('creator_database.add_backup')}</button>
                    </>
                  )
                )}
                {canUseInCampaign && !campaignContext && <a href={u(`/creator-database/${c.id}`)} className="btn btn-xs btn-secondary">{t('creator_database.nominate_to_campaign')}</a>}
              </div>
            </div>
          ))}
        </div>
      )}

      <div style={{ marginTop: '1rem', paddingBottom: compare.length > 0 ? 72 : 0 }}><Pagination links={creators.links} /></div>

      {/* شريط المقارنة اللاصق — يظهر عند اختيار مؤثر واحد على الأقل */}
      {compare.length > 0 && (
        <div className="ih-comparebar" role="region" aria-label={t('creator_database.comparebar_aria')}>
          <span style={{ fontWeight: 700 }}>{compare.length === 1 ? t('creator_database.selected_one') : t('creator_database.selected_many', { n: compare.length })}</span>
          <div style={{ display: 'flex', gap: '.4rem', alignItems: 'center', flexWrap: 'wrap' }}>
            {compare.map((cr) => (
              <span key={cr.id} className="ih-tag" style={{ display: 'inline-flex', alignItems: 'center', gap: '.25rem' }}>
                {cr.name}
                <button onClick={() => toggleCompare(cr)} aria-label={t('creator_database.remove_x', { name: cr.name })} style={{ border: 0, background: 'none', cursor: 'pointer', lineHeight: 1, padding: 0 }}>
                  <Icon name="x" size={12} />
                </button>
              </span>
            ))}
          </div>
          <div style={{ marginInlineStart: 'auto', display: 'flex', gap: '.4rem' }}>
            <button onClick={() => setShowCompare(true)} disabled={compare.length < 2} className="btn btn-sm btn-primary" title={compare.length < 2 ? t('creator_database.compare_min') : t('creator_database.compare')}>
              {t('creator_database.compare_btn', { n: compare.length })}
            </button>
            <button onClick={() => { setCompare([]); setShowCompare(false); }} className="btn btn-sm btn-outline">{t('creator_database.clear_selection')}</button>
          </div>
        </div>
      )}

      {/* لوحة المقارنة — أبعاد قرارية من بيانات حقيقية فقط (لا مقاييس مُختلَقة) */}
      {showCompare && compare.length >= 2 && (
        <div className="ih-modal-backdrop" role="dialog" aria-modal="true" aria-label={t('creator_database.compare_modal_aria')} onClick={() => setShowCompare(false)}>
          <div className="ih-modal" style={{ maxWidth: 860 }} onClick={(e) => e.stopPropagation()}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '.8rem' }}>
              <h3 style={{ fontWeight: 800, margin: 0 }}>{t('creator_database.compare_title', { n: compare.length })}</h3>
              <button onClick={() => setShowCompare(false)} className="ih-icon-btn" aria-label={t('creator_database.close')}><Icon name="x" size={18} /></button>
            </div>
            <div style={{ overflowX: 'auto' }}>
              <table className="ih-compare-table">
                <thead>
                  <tr>
                    <th style={{ textAlign: 'start' }}>{t('creator_database.dim')}</th>
                    {compare.map((cr) => (
                      <th key={cr.id} style={{ minWidth: 140 }}>
                        <a href={u(`/creator-database/${cr.id}`)} style={{ textDecoration: 'none', fontWeight: 700 }}>{cr.name}</a>
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {([
                    [t('creator_database.dim_platform'), (cr: Creator) => cr.platformLabel],
                    [t('creator_database.dim_type'), (cr: Creator) => cr.creatorTypeLabel],
                    [t('creator_database.dim_followers'), (cr: Creator) => kfmt(cr.followers)],
                    [t('creator_database.dim_likes'), (cr: Creator) => kfmt(cr.likes)],
                    [t('creator_database.dim_tier'), (cr: Creator) => (cr.tier ? t('creator_database.tier_label', { t: cr.tier }) : '—')],
                    [t('creator_database.dim_location'), (cr: Creator) => cr.city || cr.region || '—'],
                    [t('creator_database.dim_shows_face'), (cr: Creator) => (cr.showsFace === null ? '—' : cr.showsFace ? t('creator_database.yes') : t('creator_database.no'))],
                    [t('creator_database.dim_rating'), (cr: Creator) => cr.rating || '—'],
                    [t('creator_database.dim_categories'), (cr: Creator) => (cr.categories.length ? cr.categories.slice(0, 3).join('، ') : '—')],
                    [t('creator_database.dim_rate'), (cr: Creator) => (cr.referenceRate != null ? `${cr.referenceRate.toLocaleString('en-US')} ر.س` : t('creator_database.rate_not_added'))],
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
              {t('creator_database.compare_note')}
            </p>
          </div>
        </div>
      )}
    </AppShell>
  );
}
