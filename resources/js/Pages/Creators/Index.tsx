import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Kpi, ListHead, numFmt } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface CreatorRow {
  id: number; name: string; handle: string | null; number: string;
  platform: string | null; followers: number; rateMinor: number | null;
  status: string; statusLabel: string; statusTone: string;
  tier: string; engagement: number | null; verified: boolean; incomplete: boolean; activeCollabs: number;
  capabilities: { key: string; label: string }[];
}
interface Summary {
  total: number; tier_a: number; tier_b: number; tier_c: number;
  verified: number; unverified: number; active: number; incomplete: number;
  needs_review: number; has_active_collab: number;
}
interface Filters { q?: string; status?: string; platform?: string; city?: string; seg?: string; type?: string }
interface Props {
  creators: Paginated<CreatorRow>; summary: Summary; type: string | null;
  filters: Filters; platformOptions: Record<string, string>; capabilityOptions: Record<string, string>; cities: string[];
}

const TIER_COLOR: Record<string, string> = { A: 'var(--ih-primary)', B: 'var(--ih-accent-600)', C: 'var(--ih-gray-500)' };
const STATUS_LABELS: Record<string, string> = { prospect: 'مبدئي', active: 'نشط', paused: 'موقوف', blocked: 'محظور' };

function fnum(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1000) return Math.round(n / 1000) + 'K';
  return n.toLocaleString('en-US');
}
function sar(minor: number | null): string {
  return minor == null ? '—' : Math.round(minor / 100).toLocaleString('en-US') + ' ر.س';
}

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

export default function CreatorsIndex({ creators, summary, type, filters, platformOptions, capabilityOptions, cities }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const first = useRef(true);
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  // القدرة الافتراضية تتبع الشريحة المفتوحة حاليًا، وتبقى قابلة للتعديل كاملًا
  const [form, setForm] = useState({
    display_name: '', status: 'prospect',
    handle: '', primary_platform: '', followers_count: '0', city: '',
  });
  const [caps, setCaps] = useState<string[]>([type === 'ugc_creator' || type === 'ugc' ? 'ugc' : 'influencer']);
  const toggleCap = (k: string) => setCaps((c) => c.includes(k) ? c.filter((x) => x !== k) : [...c, k]);

  const submitCreate = () => {
    if (!form.display_name.trim() || caps.length === 0) return;
    setBusy(true);
    router.post(u('/creators'), { ...form, capabilities: caps }, {
      onFinish: () => setBusy(false),
      onError: (e) => setErrors(e as Record<string, string>),
      onSuccess: () => { setCreateOpen(false); setErrors({}); setForm({ ...form, display_name: '', handle: '', city: '' }); },
    });
  };

  // بحث مع تأخير (debounced) عبر Inertia — يحافظ على الحالة ولا يُعيد تحميل الصفحة كاملة
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => {
      router.get(u('/creators'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true });
    }, 350);
    return () => clearTimeout(t);
  }, [q]);

  const update = (patch: Filters) =>
    router.get(u('/creators'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  // اسم واحد للوحدة: «صناع المحتوى». التصفية بالقدرة تُبيَّن كلاحقة لا كاسم
  // مستقلّ، وإلا بدت الوحدة الواحدة وحدتين وتفرّقت التسمية بين القائمة والعنوان.
  const capability = type === 'influencer' ? 'مؤثرون'
    : (type === 'ugc_creator' || type === 'ugc') ? 'UGC'
    : (type && capabilityOptions[type]) ? capabilityOptions[type] : '';
  const title = capability ? `صناع المحتوى · ${capability}` : 'صناع المحتوى';
  const hasFilters = !!(filters.q || filters.status || filters.platform || filters.city || seg);

  // الفلتر صار على القدرات لا على ثلاثة أنواع ثابتة؛ `ugc_creator` يبقى في الرابط
  // للتوافق مع روابط محفوظة ويترجمه الخادم إلى قدرة `ugc`.
  const types: [string, string][] = [
    ['', 'الكل'], ['influencer', 'مؤثرون'], ['ugc_creator', 'UGC'],
    ...Object.entries(capabilityOptions).filter(([k]) => k !== 'influencer' && k !== 'ugc'),
  ];
  const segments: [string, string, number][] = [
    ['', 'الكل', summary.total], ['tier_a', 'فئة A', summary.tier_a], ['tier_b', 'فئة B', summary.tier_b],
    ['tier_c', 'فئة C', summary.tier_c], ['verified', 'موثّق', summary.verified], ['unverified', 'غير موثّق', summary.unverified],
    ['active', 'نشط', summary.active], ['incomplete', 'غير مكتمل', summary.incomplete],
    ['needs_review', 'يحتاج مراجعة', summary.needs_review], ['has_active_collab', 'لديه تعاون نشط', summary.has_active_collab],
  ];

  return (
    <AppShell heading={title}>
      <Head title={title} />

      <ListHead eyebrow="شبكة المبدعين" title={title}
        sub="قاعدة المؤثرين وصنّاع المحتوى مع التصنيف الآلي والتوثيق والتفاعل والأسعار"
        actions={<button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> مبدع جديد</button>} />

      <div className="ih-kpis">
        <Kpi label="إجمالي المبدعين" icon="users" value={numFmt(summary.total)} sub={`${summary.tier_a} فئة A · ${summary.active} نشط`} />
        <Kpi label="موثّقون" icon="shield-check" tone="success" value={numFmt(summary.verified)} sub={`${summary.unverified} غير موثّق`} />
        <Kpi label="لديهم تعاون نشط" icon="handshake" tone="accent" value={numFmt(summary.has_active_collab)} sub="مشاركون في حملات جارية" />
        <Kpi label="يحتاجون مراجعة" icon="clipboard-check" tone="warning" value={numFmt(summary.needs_review)} sub={`${summary.incomplete} ملف غير مكتمل`} />
      </div>

      {/* مبدّل النوع */}
      <div className="ih-chips" style={{ marginBottom: '.7rem' }}>
        {types.map(([tk, tl]) => (
          <button key={tk} onClick={() => update({ type: tk, seg: '' })} className={`ih-chip${(type ?? '') === tk ? ' active' : ''}`}>{tl}</button>
        ))}
      </div>

      {/* شرائح التصنيف */}
      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      {/* البحث والفلاتر */}
      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالاسم أو المعرّف أو المدينة…" />
        </label>
        <select className="field" style={{ maxWidth: 130 }} value={filters.status ?? ''} onChange={(e) => update({ status: e.target.value })}>
          <option value="">كل الحالات</option>
          {Object.entries(STATUS_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 140 }} value={filters.platform ?? ''} onChange={(e) => update({ platform: e.target.value })}>
          <option value="">كل المنصّات</option>
          {Object.entries(platformOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <select className="field" style={{ maxWidth: 120 }} value={filters.city ?? ''} onChange={(e) => update({ city: e.target.value })}>
          <option value="">كل المدن</option>
          {cities.map((c) => <option key={c} value={c}>{c}</option>)}
        </select>
      </div>

      {creators.data.length === 0 ? (
        <EmptyState hasFilters={hasFilters} />
      ) : (
        <>
          {/* جدول سطح المكتب */}
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr>
                  <th>المبدع</th><th>الحجم</th><th>المنصّة</th><th>المتابعون</th><th>التفاعل</th><th>السعر/منشور</th><th>الحالة</th><th></th>
                </tr></thead>
                <tbody>
                  {creators.data.map((c) => (
                    <tr key={c.id}>
                      <td>
                        <a href={u(`/creators/${c.id}`)} className="ih-idc" style={{ textDecoration: 'none' }}>
                          <span className="ih-idc__av ih-idc__av--round">{c.name.slice(0, 1)}</span>
                          <span className="ih-idc__main">
                            <span className="ih-idc__name">{c.name} {c.verified && <Icon name="shield-check" size={13} style={{ color: 'var(--ih-success)' }} />}</span>
                            <span className="ih-idc__sub" style={{ direction: 'ltr', textAlign: 'right' }}>{c.handle ? '@' + c.handle : c.number}</span>
                          </span>
                        </a>
                      </td>
                      <td><span className="badge" style={{ background: (TIER_COLOR[c.tier] ?? TIER_COLOR.C) + '1f', color: TIER_COLOR[c.tier] ?? TIER_COLOR.C, fontWeight: 800 }}>{c.tier}</span></td>
                      <td>{c.platform ? <span className="ih-tag">{c.platform}</span> : '—'}</td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{fnum(c.followers)}</td>
                      <td className="ih-dt__num">{c.engagement ?? '—'}%</td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{sar(c.rateMinor)}</td>
                      <td>
                        <span className={`badge ih-status-${c.statusTone}`}>{c.statusLabel}</span>
                        {c.incomplete && <span className="badge" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.56rem' }}>ناقص</span>}
                      </td>
                      <td style={{ textAlign: 'end' }}>
                        <span className="ih-dt__row-actions">
                          {c.activeCollabs > 0 && <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{c.activeCollabs} تعاون</span>}
                          <a href={u(`/creators/${c.id}`)} className="btn btn-xs btn-outline">فتح</a>
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot">
                <span>{creators.total} مبدع{hasFilters ? ' · مُرشَّح' : ''}</span>
                <Pagination links={creators.links} />
              </div>
            </div>
          </div>

          {/* بطاقات الجوال */}
          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {creators.data.map((c) => (
                <a key={c.id} href={u(`/creators/${c.id}`)} className="ih-mcard">
                  <div className="ih-mcard__top">
                    <span className="ih-idc__av ih-idc__av--round" style={{ width: 42, height: 42 }}>{c.name.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{c.name}
                        <span className="badge" style={{ background: (TIER_COLOR[c.tier] ?? TIER_COLOR.C) + '1f', color: TIER_COLOR[c.tier] ?? TIER_COLOR.C, fontSize: '.6rem', fontWeight: 800 }}>{c.tier}</span>
                        {c.verified && <Icon name="shield-check" size={13} style={{ color: 'var(--ih-success)' }} />}
                      </div>
                      <div className="ih-idc__sub" style={{ direction: 'ltr', textAlign: 'right' }}>{c.handle ? '@' + c.handle : c.number}</div>
                    </div>
                    <span className={`badge ih-status-${c.statusTone}`}>{c.statusLabel}</span>
                  </div>
                  {c.capabilities.length > 0 && (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '.25rem', margin: '.4rem 0 .1rem' }}>
                      {c.capabilities.map((cap) => (
                        <span key={cap.key} className="ih-tag" style={{ fontSize: '.6rem' }}>{cap.label}</span>
                      ))}
                    </div>
                  )}
                  <div className="ih-mcard__grid">
                    <div className="ih-metric"><span className="ih-metric__v" style={{ direction: 'ltr' }}>{fnum(c.followers)}</span><span className="ih-metric__k">{c.platform ?? 'متابع'}</span></div>
                    <div className="ih-metric"><span className="ih-metric__v">{c.engagement ?? '—'}%</span><span className="ih-metric__k">تفاعل</span></div>
                    <div className="ih-metric"><span className="ih-metric__v" style={{ direction: 'ltr' }}>{sar(c.rateMinor)}</span><span className="ih-metric__k">السعر</span></div>
                  </div>
                  {c.activeCollabs > 0 && <div style={{ marginTop: '.6rem', fontSize: '.76rem', color: 'var(--ih-primary)', fontWeight: 600 }}>{c.activeCollabs} تعاون نشط</div>}
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={creators.links} /></div>
          </div>
        </>
      )}
      {createOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setCreateOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 560 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>مبدع جديد</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="الاسم" labelStyle={LBL}>
                <input value={form.display_name} onChange={(e) => setForm({ ...form, display_name: e.target.value })}
                  className="field" style={{ width: '100%' }} autoFocus />
                {errors.display_name && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.display_name}</div>}
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="القدرات" labelStyle={LBL} style={{ gridColumn: '1 / -1' }}>
                  {(g) => (
                    <>
                      <div {...g} role="group" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: '.4rem' }}>
                        {Object.entries(capabilityOptions).map(([k, label]) => (
                          <label key={k} style={{
                            display: 'flex', alignItems: 'center', gap: '.45rem', cursor: 'pointer',
                            padding: '.4rem .55rem', borderRadius: '.5rem', fontSize: '.78rem',
                            border: `1px solid ${caps.includes(k) ? 'var(--ih-primary)' : 'var(--ih-border)'}`,
                            background: caps.includes(k) ? 'var(--ih-primary-soft)' : 'transparent',
                          }}>
                            <input type="checkbox" checked={caps.includes(k)} onChange={() => toggleCap(k)}
                              style={{ width: '.95rem', height: '.95rem', flex: 'none' }} />
                            <span>{label}</span>
                          </label>
                        ))}
                      </div>
                      {errors.capabilities && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.capabilities}</div>}
                    </>
                  )}
                </Field>
                <Field label="الحالة" labelStyle={LBL}>
                  <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className="field" style={{ width: '100%' }}>
                    {Object.entries(STATUS_LABELS).filter(([v]) => v !== 'blocked').map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                  </select>
                </Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="المعرّف" labelStyle={LBL}>
                  <input value={form.handle} onChange={(e) => setForm({ ...form, handle: e.target.value })}
                    className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="username" />
                </Field>
                <Field label="المنصة الأساسية" labelStyle={LBL}>
                  <select value={form.primary_platform} onChange={(e) => setForm({ ...form, primary_platform: e.target.value })} className="field" style={{ width: '100%' }}>
                    <option value="">—</option>
                    {Object.entries(platformOptions).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                  </select>
                </Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="عدد المتابعين" labelStyle={LBL}>
                  <input type="number" min={0} value={form.followers_count} onChange={(e) => setForm({ ...form, followers_count: e.target.value })}
                    className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
                <Field label="المدينة" labelStyle={LBL}>
                  <input value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} className="field" style={{ width: '100%' }} />
                </Field>
              </div>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.display_name.trim() || caps.length === 0} onClick={submitCreate} className="btn btn-primary">حفظ المبدع</button>
              <button disabled={busy} onClick={() => setCreateOpen(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

function EmptyState({ hasFilters }: { hasFilters: boolean }) {
  return (
    <div className="ih-dt-wrap"><div className="ih-empty">
      <span className="ih-empty__icon"><Icon name="users" size={26} /></span>
      {hasFilters ? (
        <>
          <div className="ih-empty__title">لا مبدعين مطابقين</div>
          <div className="ih-empty__text">لا نتائج للبحث أو الفلاتر الحالية. جرّب تغيير المنصّة أو الفئة.</div>
          <a href={u("/creators")} className="btn btn-sm btn-outline">مسح الفلاتر</a>
        </>
      ) : (
        <>
          <div className="ih-empty__title">ابدأ ببناء شبكة المبدعين</div>
          <div className="ih-empty__text">أضِف مؤثرين وصنّاع محتوى ليصنّفهم النظام آليًا حسب الحجم والتفاعل والموثوقية.</div>
          <a href="/app/creators" className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> مبدع جديد</a>
        </>
      )}
    </div></div>
  );
}

/** يزيل القيم الفارغة من معاملات الفلترة. */
function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) {
    if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  }
  return out;
}
