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
}
interface Filters { platform?: string; creator_type?: string; category?: string; city?: string; region?: string; gender?: string; shows_face?: string; tier?: string; min_followers?: string; has_price?: string; q?: string }
interface Props {
  base: string;
  creators: Paginated<Creator>;
  filters: Filters;
  canContact: boolean;
  canUseInCampaign: boolean;
  facets: { platforms: Record<string, number>; creatorTypes: Record<string, number>; categories: Record<string, number>; regions: Record<string, number>; tiers: Record<string, number> };
  summary: { total: number };
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

export default function CreatorDatabaseIndex({ creators, filters, canContact, canUseInCampaign, facets, summary }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/creator-database'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/creator-database'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });
  const resetAll = () => { setQ(''); router.get(u('/creator-database'), {}, { preserveScroll: true }); };
  const activeCount = Object.entries(filters).filter(([, v]) => v !== '' && v != null).length;
  const categoryEntries = Object.entries(facets.categories ?? {});

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

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالاسم أو المدينة أو الحساب…" />
        </label>
        <select className="field" style={{ maxWidth: 130 }} value={filters.platform ?? ''} onChange={(e) => update({ platform: e.target.value })}>
          <option value="">كل المنصّات</option>
          {Object.keys(facets.platforms).map((p) => <option key={p} value={p}>{PLATFORM_LABELS[p] ?? p}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 140 }} value={filters.creator_type ?? ''} onChange={(e) => update({ creator_type: e.target.value })}>
          <option value="">كل الأنواع</option>
          <option value="celebrity">مؤثّر</option>
          <option value="ugc">صانع UGC</option>
        </select>
        <select className="field" style={{ maxWidth: 110 }} value={filters.tier ?? ''} onChange={(e) => update({ tier: e.target.value })}>
          <option value="">كل الفئات</option>
          {Object.keys(facets.tiers).map((t) => <option key={t} value={t}>فئة {t}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 130 }} value={filters.region ?? ''} onChange={(e) => update({ region: e.target.value })}>
          <option value="">كل المناطق</option>
          {Object.keys(facets.regions).map((rg) => <option key={rg} value={rg}>{rg}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 110 }} value={filters.gender ?? ''} onChange={(e) => update({ gender: e.target.value })}>
          <option value="">الجنس</option>
          <option value="female">أنثى</option>
          <option value="male">ذكر</option>
        </select>
        <select className="field" style={{ maxWidth: 130 }} value={filters.has_price ?? ''} onChange={(e) => update({ has_price: e.target.value })}>
          <option value="">السعر</option>
          <option value="1">سعر متاح</option>
        </select>
        {activeCount > 0 && (
          <button onClick={resetAll} className="btn btn-sm btn-outline" title="مسح كل الفلاتر">
            <Icon name="x" size={14} /> مسح الفلاتر ({activeCount})
          </button>
        )}
      </div>

      {categoryEntries.length > 0 && (
        <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', margin: '.2rem 0 1rem', overflowX: 'auto' }}>
          <button
            onClick={() => update({ category: '' })}
            className="ih-chip"
            aria-pressed={!filters.category}
            style={!filters.category ? { background: 'var(--ih-primary)', color: '#fff', borderColor: 'var(--ih-primary)' } : undefined}
          >
            الكل
          </button>
          {categoryEntries.map(([cat, count]) => {
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
                {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noreferrer" className="btn btn-xs btn-outline">الحساب</a>}
                {canContact && c.contact?.hasPhone && (
                  <>
                    <button onClick={() => copyPhone(c.contact!.phone!)} className="btn btn-xs">نسخ الجوال</button>
                    <a href={waLink(c.contact.whatsapp!)} target="_blank" rel="noreferrer" className="btn btn-xs btn-primary">واتساب</a>
                  </>
                )}
                {canUseInCampaign && <a href={u(`/creator-database/${c.id}`)} className="btn btn-xs btn-secondary">ترشيح لحملة</a>}
              </div>
            </div>
          ))}
        </div>
      )}

      <div style={{ marginTop: '1rem' }}><Pagination links={creators.links} /></div>
    </AppShell>
  );
}
