import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

type TFn = ReturnType<typeof useT>;

interface Contact { phone: string | null; phoneDisplay: string | null; whatsapp: string | null; hasPhone: boolean }
interface Overlay { favorite: boolean; tags: string[]; notes: string | null; negotiatedRate: number | null; relationshipStatus: string | null; tenantRating: string | null; lastContactedAt: string | null }
interface Creator {
  id: number; name: string; platform: string; platformLabel: string; accountUrl: string | null;
  followers: number | null; likes: number | null; tier: string | null; gender: string | null;
  categories: string[]; showsFace: boolean | null; region: string | null; city: string | null;
  rating: string | null; creatorType: string; creatorTypeLabel: string;
  referenceRate: number | null; referenceRateNote: string; dataFreshness: string; lastImportedAt: string | null;
  contact?: Contact; overlay?: Overlay | null;
  shortlistRole?: 'primary' | 'backup' | null;
}
interface CampaignContext { id: number; name: string; primaryCount: number; backupCount: number; editable: boolean; shortlistUrl: string }
interface Filters { platform?: string; creator_type?: string; category?: string; city?: string; region?: string; gender?: string; shows_face?: string; tier?: string; min_followers?: string; has_price?: string; q?: string; sort?: string }

const SORT_KEYS = ['followers', 'price', 'recent'];
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
function sar(n: number | null): string {
  return n != null ? n.toLocaleString('en-US') + ' ر.س' : '—';
}
function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function CreatorDatabaseIndex({ creators, filters, canContact, canUseInCampaign, facets, platformLabels, summary, campaignContext }: Props) {
  const t = useT();
  const [q, setQ] = useState(filters.q ?? '');
  const advActive = Boolean(filters.creator_type || filters.tier || filters.gender || filters.has_price);
  const [showAdvanced, setShowAdvanced] = useState(advActive);
  const [loading, setLoading] = useState(false);
  const [preview, setPreview] = useState<Creator | null>(null);
  // سياق الحملة يُحفَظ في كل تنقّل تصفية/ترتيب حتى لا يضيع التدفّق نحو الترشيح
  const ctx = campaignContext ? { campaign: String(campaignContext.id) } : {};

  const go = (params: Record<string, unknown>, opts: Record<string, unknown> = {}) =>
    router.get(u('/creator-database'), clean(params), {
      preserveState: true, replace: true, preserveScroll: true,
      onStart: () => setLoading(true), onFinish: () => setLoading(false), ...opts,
    });

  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const h = setTimeout(() => go({ ...filters, ...ctx, q }), 350);
    return () => clearTimeout(h);
  }, [q]); // eslint-disable-line react-hooks/exhaustive-deps
  const update = (patch: Filters) => go({ ...filters, ...ctx, ...patch });
  const resetAll = () => { setQ(''); setShowAdvanced(false); go({ ...ctx }); };

  // ترشيح مباشر من الاكتشاف (أساسي/احتياط) بلا قفزة صفحة — back() يُحدِّث الأعداد والحالة
  const [nomBusy, setNomBusy] = useState(0);
  const nominate = (cr: Creator, role: 'primary' | 'backup') => {
    if (!campaignContext) return;
    setNomBusy(cr.id);
    router.post(u(`/creator-database/${cr.id}/nominate`), { campaign_id: campaignContext.id, role },
      { preserveScroll: true, preserveState: true, onFinish: () => setNomBusy(0) });
  };

  // مقارنة خفيفة: حتى 4 مؤثرين. الحالة محليّة وتبقى عبر الترقيم/التصفية (preserveState).
  const [compare, setCompare] = useState<Creator[]>([]);
  const [showCompare, setShowCompare] = useState(false);
  const inCompare = (id: number) => compare.some((x) => x.id === id);
  const toggleCompare = (cr: Creator) =>
    setCompare((prev) => (inCompare(cr.id) ? prev.filter((x) => x.id !== cr.id) : prev.length >= 4 ? prev : [...prev, cr]));

  const copyPhone = (p: string) => navigator.clipboard?.writeText(p);
  const waLink = (p: string) => `https://wa.me/${p}`;

  // رقائق الفلاتر النشطة (القيمة وحدها + إزالة) — «الترتيب» ليس فلترًا
  const chips: { key: keyof Filters; label: string; clear: () => void }[] = [];
  if (filters.q) chips.push({ key: 'q', label: filters.q, clear: () => setQ('') });
  if (filters.platform) chips.push({ key: 'platform', label: platformLabels[filters.platform] ?? filters.platform, clear: () => update({ platform: '' }) });
  if (filters.category) chips.push({ key: 'category', label: filters.category, clear: () => update({ category: '' }) });
  if (filters.region) chips.push({ key: 'region', label: filters.region, clear: () => update({ region: '' }) });
  if (filters.creator_type) chips.push({ key: 'creator_type', label: t(`creator_database.type_${filters.creator_type === 'ugc' ? 'ugc' : 'celebrity'}`), clear: () => update({ creator_type: '' }) });
  if (filters.tier) chips.push({ key: 'tier', label: t('creator_database.tier_label', { t: filters.tier }), clear: () => update({ tier: '' }) });
  if (filters.gender) chips.push({ key: 'gender', label: t(`creator_database.gender_${filters.gender}`), clear: () => update({ gender: '' }) });
  if (filters.has_price) chips.push({ key: 'has_price', label: t('creator_database.price_available'), clear: () => update({ has_price: '' }) });

  const currentSort = filters.sort ?? 'followers';
  const categoryKeys = Object.keys(facets.categories ?? {});

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

      {/* شريط سياق الحملة — لاصق: الاكتشاف يتدفّق مباشرةً إلى الترشيح */}
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

      {/* بحث أولًا — المرساة البصرية */}
      <div className="ih-discover-search">
        <Icon name="search" size={20} style={{ color: 'var(--ih-text-muted)', flex: 'none' }} />
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('creator_database.search_placeholder')} aria-label={t('creator_database.search_placeholder')} />
        {q && <button onClick={() => setQ('')} className="ih-icon-btn" aria-label={t('creator_database.close')} style={{ flex: 'none' }}><Icon name="x" size={16} /></button>}
      </div>

      {/* فلاتر سريعة — الأعلى قيمة فقط، والبقيّة خلف «فلاتر إضافية» */}
      <div className="ih-quickfilters">
        <select className="field" value={filters.platform ?? ''} onChange={(e) => update({ platform: e.target.value })} aria-label={t('creator_database.f_platform')}>
          <option value="">{t('creator_database.all_platforms')}</option>
          {Object.keys(facets.platforms).map((p) => <option key={p} value={p}>{platformLabels[p] ?? p}</option>)}
        </select>
        <select className="field" value={filters.category ?? ''} onChange={(e) => update({ category: e.target.value })} aria-label={t('creator_database.f_category')}>
          <option value="">{t('creator_database.all_categories')}</option>
          {categoryKeys.map((cat) => <option key={cat} value={cat}>{cat}</option>)}
        </select>
        <select className="field" value={filters.region ?? ''} onChange={(e) => update({ region: e.target.value })} aria-label={t('creator_database.f_region')}>
          <option value="">{t('creator_database.all_regions')}</option>
          {Object.keys(facets.regions).map((rg) => <option key={rg} value={rg}>{rg}</option>)}
        </select>
        <select className="field" value={currentSort} onChange={(e) => update({ sort: e.target.value })} aria-label={t('creator_database.f_sort')} title={t('creator_database.f_sort')}>
          {SORT_KEYS.map((s) => <option key={s} value={s}>{t(`creator_database.sort_${s}`)}</option>)}
        </select>
        <button onClick={() => setShowAdvanced((v) => !v)} className={`btn btn-sm btn-outline${advActive ? ' active' : ''}`} aria-expanded={showAdvanced} title={t('creator_database.more_filters')}>
          <Icon name="sliders-horizontal" size={14} /> {t('creator_database.more_filters')}{advActive ? ' •' : ''}
        </button>
      </div>

      {/* لوحة الفلاتر المتقدّمة — مخفيّة افتراضيًّا */}
      {showAdvanced && (
        <div className="ih-quickfilters" style={{ paddingBottom: '.6rem', borderBottom: '1px solid var(--ih-border)' }}>
          <select className="field" value={filters.creator_type ?? ''} onChange={(e) => update({ creator_type: e.target.value })} aria-label={t('creator_database.f_type')}>
            <option value="">{t('creator_database.all_types')}</option>
            <option value="celebrity">{t('creator_database.type_celebrity')}</option>
            <option value="ugc">{t('creator_database.type_ugc')}</option>
          </select>
          <select className="field" value={filters.tier ?? ''} onChange={(e) => update({ tier: e.target.value })} aria-label={t('creator_database.f_tier')}>
            <option value="">{t('creator_database.all_tiers')}</option>
            {Object.keys(facets.tiers).map((tk) => <option key={tk} value={tk}>{t('creator_database.tier_label', { t: tk })}</option>)}
          </select>
          <select className="field" value={filters.gender ?? ''} onChange={(e) => update({ gender: e.target.value })} aria-label={t('creator_database.f_gender')}>
            <option value="">{t('creator_database.f_gender')}</option>
            <option value="female">{t('creator_database.gender_female')}</option>
            <option value="male">{t('creator_database.gender_male')}</option>
          </select>
          <select className="field" value={filters.has_price ?? ''} onChange={(e) => update({ has_price: e.target.value })} aria-label={t('creator_database.f_price_aria')}>
            <option value="">{t('creator_database.f_price')}</option>
            <option value="1">{t('creator_database.price_available')}</option>
          </select>
        </div>
      )}

      {/* رقائق الفلاتر النشطة — قابلة للإزالة + مسح الكل */}
      {chips.length > 0 && (
        <div className="ih-fchips" role="region" aria-label={t('creator_database.active_filters')}>
          {chips.map((c) => (
            <span key={c.key} className="ih-fchip">
              {c.label}
              <button onClick={c.clear} aria-label={`${t('creator_database.clear_all')}: ${c.label}`}><Icon name="x" size={12} /></button>
            </span>
          ))}
          <button className="ih-fchip__clear" onClick={resetAll}>{t('creator_database.clear_all')}</button>
        </div>
      )}

      {/* النتائج: هيكل أثناء التحميل، بطاقات فاخرة قابلة للمعاينة، أو حالة فارغة ذكيّة */}
      {loading ? (
        <div className="ih-cgrid" aria-busy="true" aria-label={t('creator_database.loading')}>
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="ih-skelcard">
              <div style={{ display: 'flex', gap: '.7rem', alignItems: 'center' }}>
                <div className="ih-skeleton" style={{ width: 46, height: 46, borderRadius: '50%' }} />
                <div style={{ flex: 1, display: 'grid', gap: '.4rem' }}>
                  <div className="ih-skeleton ih-skel-line" style={{ width: '60%' }} />
                  <div className="ih-skeleton ih-skel-line" style={{ width: '40%' }} />
                </div>
              </div>
              <div className="ih-skeleton ih-skel-line" style={{ width: '100%', height: 32 }} />
              <div className="ih-skeleton ih-skel-line" style={{ width: '50%' }} />
            </div>
          ))}
        </div>
      ) : creators.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="users" size={26} /></span>
          <div className="ih-empty__title">{t('creator_database.empty_title')}</div>
          <div className="ih-empty__text">{t('creator_database.empty_hint')}</div>
          <button onClick={resetAll} className="btn btn-sm btn-outline">{t('creator_database.clear_filters')}</button>
        </div></div>
      ) : (
        <div className="ih-cgrid">
          {creators.data.map((c) => (
            <div
              key={c.id}
              className="ih-ccard"
              role="button"
              tabIndex={0}
              onClick={() => setPreview(c)}
              onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setPreview(c); } }}
              aria-label={`${c.name} — ${t('creator_database.preview_aria')}`}
            >
              <div className="ih-ccard__top">
                <span className="ih-ccard__av">{c.name.slice(0, 1)}</span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="ih-ccard__name" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</div>
                  {c.accountUrl
                    ? <div className="ih-ccard__handle" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{new URL(c.accountUrl).pathname.replace(/^\//, '@').replace(/\/$/, '')}</div>
                    : <div className="ih-ccard__handle">{c.platformLabel}</div>}
                </div>
              </div>

              <div className="ih-ccard__meta">
                <span className="ih-tag">{c.platformLabel}</span>
                <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{c.creatorTypeLabel}</span>
                {c.tier && <span className="ih-tag">{t('creator_database.tier_label', { t: c.tier })}</span>}
                {(c.city || c.region) && <span style={{ color: 'var(--ih-text-muted)' }}><Icon name="map-pin" size={12} /> {c.city || c.region}</span>}
              </div>

              <div className="ih-ccard__stats">
                <div className="ih-ccard__stat"><b>{kfmt(c.followers)}</b><span>{t('creator_database.m_followers')}</span></div>
                <div className="ih-ccard__stat"><b>{kfmt(c.likes)}</b><span>{t('creator_database.m_likes')}</span></div>
                <div className="ih-ccard__stat"><b>{c.showsFace ? t('creator_database.yes') : '—'}</b><span>{t('creator_database.m_shows_face')}</span></div>
              </div>

              {c.categories.length > 0 && (
                <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
                  {c.categories.slice(0, 3).map((cat, i) => <span key={i} className="ih-tag" style={{ fontSize: '.68rem' }}>{cat}</span>)}
                </div>
              )}

              <div className="ih-ccard__foot">
                {c.referenceRate != null
                  ? <span className="ih-ccard__price">{c.referenceRate.toLocaleString('en-US')} <small>ر.س</small></span>
                  : <span style={{ color: 'var(--ih-warning-ink)', fontSize: '.78rem' }}>{t('creator_database.rate_missing')}</span>}

                <div style={{ display: 'flex', gap: '.35rem', alignItems: 'center' }} onClick={(e) => e.stopPropagation()}>
                  {canUseInCampaign && campaignContext && campaignContext.editable ? (
                    c.shortlistRole ? (
                      <button onClick={() => nominate(c, c.shortlistRole === 'backup' ? 'primary' : 'backup')} disabled={nomBusy === c.id}
                        className="btn btn-xs" title={t('creator_database.role_toggle_title')}>
                        ✓ {c.shortlistRole === 'backup' ? t('creator_database.role_backup') : t('creator_database.role_primary')} ↺
                      </button>
                    ) : (
                      <>
                        <button onClick={() => nominate(c, 'primary')} disabled={nomBusy === c.id} className="btn btn-xs btn-primary">{t('creator_database.add_primary')}</button>
                        <button onClick={() => nominate(c, 'backup')} disabled={nomBusy === c.id} className="btn btn-xs btn-outline">{t('creator_database.add_backup')}</button>
                      </>
                    )
                  ) : (
                    <button
                      onClick={() => toggleCompare(c)}
                      disabled={!inCompare(c.id) && compare.length >= 4}
                      className={`btn btn-xs${inCompare(c.id) ? ' btn-primary' : ' btn-outline'}`}
                      aria-pressed={inCompare(c.id)}
                      title={!inCompare(c.id) && compare.length >= 4 ? t('creator_database.compare_max') : t('creator_database.compare_add')}
                    >
                      {inCompare(c.id) ? t('creator_database.in_compare') : t('creator_database.compare')}
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {!loading && creators.data.length > 0 && (
        <div style={{ marginTop: '1rem', paddingBottom: compare.length > 0 ? 72 : 0 }}><Pagination links={creators.links} /></div>
      )}

      {/* درج المعاينة السريعة — مراجعة دون مغادرة الاكتشاف */}
      {preview && (
        <PreviewDrawer
          c={preview} t={t} canContact={canContact} canUseInCampaign={canUseInCampaign}
          campaignContext={campaignContext} nomBusy={nomBusy === preview.id}
          inCompare={inCompare(preview.id)} compareFull={!inCompare(preview.id) && compare.length >= 4}
          onClose={() => setPreview(null)}
          onNominate={(role) => nominate(preview, role)}
          onCompare={() => toggleCompare(preview)}
          copyPhone={copyPhone} waLink={waLink}
        />
      )}

      {/* شريط المقارنة اللاصق */}
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

/** درج المعاينة السريعة — كلّ البيانات من صفّ القائمة (لا نداء خادم إضافيّ). بيانات حقيقية فقط. */
function PreviewDrawer({ c, t, canContact, canUseInCampaign, campaignContext, nomBusy, inCompare, compareFull, onClose, onNominate, onCompare, copyPhone, waLink }: {
  c: Creator; t: TFn; canContact: boolean; canUseInCampaign: boolean; campaignContext?: CampaignContext | null;
  nomBusy: boolean; inCompare: boolean; compareFull: boolean;
  onClose: () => void; onNominate: (role: 'primary' | 'backup') => void; onCompare: () => void;
  copyPhone: (p: string) => void; waLink: (p: string) => string;
}) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const row = (label: string, value: string) => (
    <div className="ih-preview__row"><span>{label}</span><span>{value}</span></div>
  );
  const inShortlist = campaignContext && campaignContext.editable && canUseInCampaign;
  const notes = c.overlay?.notes;

  return (
    <>
      <div className="ih-preview-backdrop" onClick={onClose} />
      <aside className="ih-preview" role="dialog" aria-modal="true" aria-label={`${c.name} — ${t('creator_database.preview_aria')}`}>
        <div className="ih-preview__head">
          <span className="ih-ccard__av" style={{ width: 52, height: 52, fontSize: '1.3rem' }}>{c.name.slice(0, 1)}</span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontWeight: 800, fontSize: '1.1rem' }}>{c.name}</div>
            <div className="ih-ccard__meta" style={{ marginTop: '.3rem' }}>
              <span className="ih-tag">{c.platformLabel}</span>
              <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{c.creatorTypeLabel}</span>
              {c.tier && <span className="ih-tag">{t('creator_database.tier_label', { t: c.tier })}</span>}
            </div>
          </div>
          <button onClick={onClose} className="ih-icon-btn" aria-label={t('creator_database.close')} style={{ flex: 'none' }}><Icon name="x" size={18} /></button>
        </div>

        <div className="ih-preview__body">
          <section>
            <div className="ih-preview__sec-title">{t('creator_database.sec_reach')}</div>
            <div className="ih-preview__rows">
              {row(t('creator_database.dim_followers'), kfmt(c.followers))}
              {row(t('creator_database.dim_likes'), kfmt(c.likes))}
              {row(t('creator_database.m_shows_face'), c.showsFace === null ? '—' : c.showsFace ? t('creator_database.yes') : t('creator_database.no'))}
              {row(t('creator_database.dim_rating'), c.rating || '—')}
            </div>
          </section>

          <section>
            <div className="ih-preview__sec-title">{t('creator_database.sec_about')}</div>
            <div className="ih-preview__rows">
              {row(t('creator_database.dim_location'), c.city || c.region || '—')}
              {row(t('creator_database.f_gender'), c.gender === 'female' ? t('creator_database.gender_female') : c.gender === 'male' ? t('creator_database.gender_male') : '—')}
            </div>
            {c.categories.length > 0 && (
              <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginTop: '.55rem' }}>
                {c.categories.map((cat, i) => <span key={i} className="ih-tag">{cat}</span>)}
              </div>
            )}
          </section>

          <section>
            <div className="ih-preview__sec-title">{t('creator_database.sec_pricing')}</div>
            <div className="ih-preview__rows">
              {row(t('creator_database.reference_rate'), c.referenceRate != null ? sar(c.referenceRate) : t('creator_database.rate_not_added'))}
            </div>
            <p style={{ color: 'var(--ih-text-muted)', fontSize: '.72rem', marginTop: '.35rem' }}>{c.referenceRateNote}</p>
            <p style={{ color: 'var(--ih-text-muted)', fontSize: '.72rem' }}>{c.dataFreshness}{c.lastImportedAt ? ` · ${t('creator_database.last_updated', { date: c.lastImportedAt })}` : ''}</p>
          </section>

          {canContact && c.contact?.hasPhone && (
            <section>
              <div className="ih-preview__sec-title">{t('creator_database.contact')}</div>
              <div style={{ display: 'flex', gap: '.4rem', alignItems: 'center', flexWrap: 'wrap' }}>
                <span style={{ direction: 'ltr', fontWeight: 600 }}>{c.contact.phoneDisplay}</span>
                <button onClick={() => copyPhone(c.contact!.phone!)} className="btn btn-xs">{t('creator_database.copy')}</button>
                <a href={waLink(c.contact.whatsapp!)} target="_blank" rel="noreferrer" className="btn btn-xs btn-primary">{t('creator_database.whatsapp')}</a>
                {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noreferrer" className="btn btn-xs btn-outline">{t('creator_database.account')}</a>}
              </div>
            </section>
          )}

          <section>
            <div className="ih-preview__sec-title">{t('creator_database.sec_notes')}</div>
            <p style={{ fontSize: '.85rem', color: notes ? 'var(--ih-text)' : 'var(--ih-text-muted)', margin: 0, whiteSpace: 'pre-wrap' }}>{notes || t('creator_database.notes_none')}</p>
          </section>
        </div>

        <div className="ih-preview__foot">
          <a href={u(`/creator-database/${c.id}`)} className="btn btn-sm btn-outline" style={{ flex: 1 }}>{t('creator_database.view_profile')}</a>
          {inShortlist ? (
            c.shortlistRole ? (
              <button onClick={() => onNominate(c.shortlistRole === 'backup' ? 'primary' : 'backup')} disabled={nomBusy} className="btn btn-sm" style={{ flex: 1 }}>
                ✓ {c.shortlistRole === 'backup' ? t('creator_database.role_backup') : t('creator_database.role_primary')} ↺
              </button>
            ) : (
              <>
                <button onClick={() => onNominate('primary')} disabled={nomBusy} className="btn btn-sm btn-primary" style={{ flex: 1 }}>{t('creator_database.add_primary')}</button>
                <button onClick={() => onNominate('backup')} disabled={nomBusy} className="btn btn-sm btn-outline" style={{ flex: 1 }}>{t('creator_database.add_backup')}</button>
              </>
            )
          ) : (
            <button onClick={onCompare} disabled={compareFull} className={`btn btn-sm${inCompare ? ' btn-primary' : ' btn-outline'}`} style={{ flex: 1 }}>
              {inCompare ? t('creator_database.in_compare') : t('creator_database.compare')}
            </button>
          )}
        </div>
      </aside>
    </>
  );
}
