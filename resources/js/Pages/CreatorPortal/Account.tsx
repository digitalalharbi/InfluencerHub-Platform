import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Sec, WorkTabs, WorkspaceHeader, type WorkTab } from '@/Components/ui';
import { creatorNav } from '@/lib/nav';
import AccountSecurity, { type SecurityProps } from '@/Components/AccountSecurity';
import { u } from '@/lib/href';

interface Profile {
  displayName: string; professionalName: string | null; phone: string | null; whatsapp: string | null;
  city: string | null; bio: string | null; primaryPlatform: string | null;
  number: string; type: string; hasAvatar: boolean; capabilities: string[];
}
interface Platform { id: number; platform: string; platformLabel: string; handle: string | null; url: string | null; followers: number }
interface Service { id: number; type: string; priceMinor: number | null; currency: string | null; deliveryDays: number | null; description: string | null; available: boolean }
interface PortfolioItem { id: number; type: string; url: string | null; category: string | null; previousBrand: string | null; description: string | null }
interface Verify { status: string | null; statusLabel: string }
interface Props {
  profile: Profile; platforms: Platform[]; services: Service[]; portfolio: PortfolioItem[];
  mowthooq: Verify & { licenseNumber: string | null; expiresAt: string | null };
  financial: Verify & { beneficiaryName: string | null; bankName: string | null; ibanLast4: string | null };
  platformOptions: Record<string, string>;
  capabilityOptions: Record<string, string>;
}
type AccountProps = Props & Omit<SecurityProps, 'base'>;

const LBL: React.CSSProperties = { fontSize: '.78rem', fontWeight: 600, display: 'block', marginBottom: '.25rem' };
const TAB_KEYS = ['profile', 'platforms', 'services', 'portfolio', 'verification', 'financial', 'security'] as const;

const VERIFY_TONE: Record<string, { bg: string; fg: string }> = {
  verified: { bg: 'var(--ih-success-soft)', fg: 'var(--ih-success-ink)' },
  rejected: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  pending: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
};
function VerifyBadge({ v }: { v: Verify }) {
  const t = VERIFY_TONE[v.status ?? ''] ?? { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-muted)' };
  return <span className="badge" style={{ background: t.bg, color: t.fg, fontSize: '.68rem' }}>{v.statusLabel}</span>;
}

const SERVICE_TYPES: [string, string][] = [
  ['post', 'منشور'], ['story', 'ستوري'], ['reel', 'ريل'], ['video', 'فيديو'],
  ['ugc', 'محتوى UGC'], ['event', 'حضور فعالية'], ['other', 'أخرى'],
];
const serviceLabel = (t: string) => SERVICE_TYPES.find(([k]) => k === t)?.[1] ?? t;
const PORTFOLIO_TYPES: [string, string][] = [['image', 'صورة'], ['video', 'فيديو'], ['link', 'رابط']];

const fnum = (n: number) => n >= 1000 ? Math.round(n / 1000).toLocaleString('en-US') + 'K' : n.toLocaleString('en-US');
const sar = (minor: number | null) => minor === null ? '—' : Math.round(minor / 100).toLocaleString('en-US') + ' ر.س';

export default function CreatorAccount({ profile, platforms, services, portfolio, mowthooq, financial, platformOptions, capabilityOptions, prefs, categories, sessions, twoFactorEnabled }: AccountProps) {
  const [tab, setTab] = useState<string>('profile');
  useEffect(() => {
    const apply = () => {
      const h = window.location.hash.replace('#', '');
      if ((TAB_KEYS as readonly string[]).includes(h)) setTab(h);
    };
    apply();
    window.addEventListener('hashchange', apply);
    return () => window.removeEventListener('hashchange', apply);
  }, []);
  const go = (k: string) => { setTab(k); window.history.replaceState(null, '', `#${k}`); };

  const [busy, setBusy] = useState(false);
  const [errs, setErrs] = useState<Record<string, string>>({});
  const post = (path: string, data: Record<string, unknown>, done?: () => void) => {
    setBusy(true);
    router.post(u(`/account${path}`), data as never, {
      preserveScroll: true,
      forceFormData: data.file instanceof File,
      onFinish: () => setBusy(false),
      onError: (e) => setErrs(e as Record<string, string>),
      onSuccess: () => { setErrs({}); done?.(); },
    });
  };
  const Err = ({ k }: { k: string }) => errs[k] ? <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.74rem', marginTop: '.25rem' }}>{errs[k]}</div> : null;

  const [pf, setPf] = useState({
    display_name: profile.displayName, professional_name: profile.professionalName ?? '',
    phone: profile.phone ?? '', whatsapp: profile.whatsapp ?? '', city: profile.city ?? '',
    bio: profile.bio ?? '', primary_platform: profile.primaryPlatform ?? '',
  });
  // اختيار متعدّد حقيقي: يبدأ بما هو محفوظ، ويسمح بالإفراغ ثم إعادة البناء.
  // منع الحفظ عند الصفر لا منع إلغاء الاختيار — وإلا تعذّر استبدال آخر قدرة.
  const [caps, setCaps] = useState<string[]>(profile.capabilities);
  const toggleCap = (k: string) => setCaps((c) => c.includes(k) ? c.filter((x) => x !== k) : [...c, k]);

  const [plat, setPlat] = useState({ platform: '', handle: '', url: '', followers_count: '' });
  const [svc, setSvc] = useState({ service_type: 'post', price: '', delivery_days: '', description: '' });
  const [pfo, setPfo] = useState({ type: 'link', url: '', category: '', previous_brand: '', description: '' });
  const [mw, setMw] = useState({ mowthooq_license_number: mowthooq.licenseNumber ?? '', mowthooq_expires_at: mowthooq.expiresAt ?? '' });
  const [fin, setFin] = useState({ beneficiary_name: financial.beneficiaryName ?? '', bank_name: financial.bankName ?? '', iban: '' });

  const tabs: WorkTab[] = [
    { key: 'profile', label: 'الملف', icon: 'users' },
    { key: 'platforms', label: 'المنصّات', icon: 'radar', count: platforms.length },
    { key: 'services', label: 'الخدمات', icon: 'clipboard-check', count: services.length },
    { key: 'portfolio', label: 'الأعمال', icon: 'image', count: portfolio.length },
    { key: 'verification', label: 'موثوق', icon: 'shield-check' },
    { key: 'financial', label: 'المالية', icon: 'wallet' },
    { key: 'security', label: 'الأمان', icon: 'shield-check' },
  ];

  return (
    <AppShell heading="حسابي" nav={creatorNav} portal="creator" wsName={profile.displayName} wsPlan="بوابة المبدع">
      <Head title="حسابي" />

      <WorkspaceHeader
        eyebrow={`حسابي · ${profile.number}`}
        title={profile.displayName}
        meta={[
          ['المنصّة الأساسية', profile.primaryPlatform ?? '—'],
          ['المدينة', profile.city ?? '—'],
          ['موثوق', mowthooq.statusLabel],
          ['المالية', financial.statusLabel],
        ]}
      />

      <WorkTabs active={tab} onChange={go} tabs={tabs} />

      {tab === 'profile' && (
        <Sec title="الملف الشخصي" icon="users">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
              <Field label="الاسم المعروض" labelStyle={LBL}><input value={pf.display_name} onChange={(e) => setPf({ ...pf, display_name: e.target.value })} className="field" style={{ width: '100%' }} /><Err k="display_name" /></Field>
              <Field label="الاسم المهني" labelStyle={LBL}><input value={pf.professional_name} onChange={(e) => setPf({ ...pf, professional_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
              <Field label="الجوال" labelStyle={LBL}><input value={pf.phone} onChange={(e) => setPf({ ...pf, phone: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              <Field label="واتساب" labelStyle={LBL}><input value={pf.whatsapp} onChange={(e) => setPf({ ...pf, whatsapp: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              <Field label="المدينة" labelStyle={LBL}><input value={pf.city} onChange={(e) => setPf({ ...pf, city: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
            </div>
            <Field label="المنصّة الأساسية" labelStyle={LBL}>
              <select value={pf.primary_platform} onChange={(e) => setPf({ ...pf, primary_platform: e.target.value })} className="field" style={{ width: '100%', maxWidth: 260 }}>
                <option value="">—</option>
                {Object.entries(platformOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
              <Err k="primary_platform" />
            </Field>
            <Field label="قدراتي" labelStyle={LBL}>{(g) => (
              <>
                <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginBottom: '.45rem' }}>
                  اختر كل ما تقدّمه — قدرة واحدة على الأقل. الوكالة ترشّحك بناءً عليها.
                </div>
                <div {...g} role="group" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))', gap: '.45rem' }}>
                  {Object.entries(capabilityOptions).map(([k, label]) => (
                    <label key={k} style={{
                      display: 'flex', alignItems: 'center', gap: '.5rem', cursor: 'pointer',
                      padding: '.5rem .6rem', borderRadius: '.5rem', fontSize: '.82rem',
                      border: `1px solid ${caps.includes(k) ? 'var(--ih-primary)' : 'var(--ih-border)'}`,
                      background: caps.includes(k) ? 'var(--ih-primary-soft)' : 'transparent',
                    }}>
                      <input type="checkbox" checked={caps.includes(k)} onChange={() => toggleCap(k)}
                        style={{ width: '1rem', height: '1rem', flex: 'none' }} />
                      <span>{label}</span>
                    </label>
                  ))}
                </div>
                <Err k="capabilities" />
                <div style={{ marginTop: '.55rem' }}>
                  <button disabled={busy || caps.length === 0}
                    onClick={() => post('/capabilities', { capabilities: caps })}
                    className="btn btn-sm btn-outline">حفظ القدرات</button>
                </div>
              </>
            )}</Field>
            <Field label="نبذة" labelStyle={LBL}><textarea value={pf.bio} onChange={(e) => setPf({ ...pf, bio: e.target.value })} className="field" rows={4} style={{ width: '100%' }} /></Field>
            <div style={{ display: 'flex', gap: '.6rem', alignItems: 'center', flexWrap: 'wrap' }}>
              <button disabled={busy || !pf.display_name.trim()} onClick={() => post('/profile', pf)} className="btn btn-sm btn-primary">حفظ الملف</button>
              <label className="btn btn-sm btn-outline" style={{ cursor: 'pointer' }}>
                {profile.hasAvatar ? 'تغيير الصورة' : 'رفع صورة'}
                <input type="file" accept="image/*" style={{ display: 'none' }}
                  onChange={(e) => { const f = e.target.files?.[0]; if (f) post('/avatar', { file: f }); }} />
              </label>
              <Err k="file" />
            </div>
          </div>
        </Sec>
      )}

      {tab === 'platforms' && (
        <Sec title="المنصّات" icon="radar">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            {platforms.length === 0 ? (
              <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا منصّات مضافة. أضِف منصّاتك ليراها فريق الوكالة عند الترشيح.</div>
            ) : (
              <div style={{ display: 'grid', gap: '.5rem' }}>
                {platforms.map((p) => (
                  <div key={p.id} className="card" style={{ padding: '.65rem .85rem', display: 'flex', alignItems: 'center', gap: '.7rem' }}>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ fontWeight: 600, fontSize: '.86rem' }}>{p.platformLabel}</div>
                      <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>
                        {p.handle ? '@' + p.handle.replace(/^@+/, '') : '—'}
                      </div>
                    </div>
                    <span style={{ fontSize: '.8rem' }}><bdi>{fnum(p.followers)}</bdi> متابع</span>
                    {p.url && <a href={p.url} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline">فتح</a>}
                    <button disabled={busy} onClick={() => post(`/platforms/${p.id}/delete`, {})} className="btn btn-xs btn-danger">حذف</button>
                  </div>
                ))}
              </div>
            )}
            <div className="card" style={{ padding: '.9rem', display: 'grid', gap: '.7rem' }}>
              <div style={{ fontWeight: 700, fontSize: '.85rem' }}>إضافة منصّة</div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.7rem' }}>
                <Field label="المنصّة" labelStyle={LBL}>
                  <select value={plat.platform} onChange={(e) => setPlat({ ...plat, platform: e.target.value })} className="field" style={{ width: '100%' }}>
                    <option value="">— اختر —</option>
                    {Object.entries(platformOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                  </select>
                  <Err k="platform" />
                </Field>
                <Field label="المعرّف" labelStyle={LBL}><input value={plat.handle} onChange={(e) => setPlat({ ...plat, handle: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="handle" /></Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '.7rem' }}>
                <Field label="الرابط" labelStyle={LBL}><input value={plat.url} onChange={(e) => setPlat({ ...plat, url: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="https://…" /><Err k="url" /></Field>
                <Field label="المتابعون" labelStyle={LBL}><input type="number" min={0} value={plat.followers_count} onChange={(e) => setPlat({ ...plat, followers_count: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              </div>
              <div>
                <button disabled={busy || !plat.platform || !plat.handle.trim()}
                  onClick={() => post('/platforms', plat, () => setPlat({ platform: '', handle: '', url: '', followers_count: '' }))}
                  className="btn btn-sm btn-primary">إضافة</button>
              </div>
            </div>
          </div>
        </Sec>
      )}

      {tab === 'services' && (
        <Sec title="الخدمات والأسعار" icon="clipboard-check">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            {services.length === 0 ? (
              <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا خدمات مسعّرة بعد.</div>
            ) : (
              <div style={{ display: 'grid', gap: '.5rem' }}>
                {services.map((s) => (
                  <div key={s.id} className="card" style={{ padding: '.65rem .85rem', display: 'flex', alignItems: 'center', gap: '.7rem' }}>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ fontWeight: 600, fontSize: '.86rem' }}>{serviceLabel(s.type)}</div>
                      {s.description && <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{s.description}</div>}
                    </div>
                    <span style={{ fontSize: '.84rem', fontWeight: 600, direction: 'ltr' }}>{sar(s.priceMinor)}</span>
                    {s.deliveryDays !== null && <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{s.deliveryDays} يوم</span>}
                    <button disabled={busy} onClick={() => post(`/services/${s.id}/delete`, {})} className="btn btn-xs btn-danger">حذف</button>
                  </div>
                ))}
              </div>
            )}
            <div className="card" style={{ padding: '.9rem', display: 'grid', gap: '.7rem' }}>
              <div style={{ fontWeight: 700, fontSize: '.85rem' }}>إضافة خدمة</div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.7rem' }}>
                <Field label="النوع" labelStyle={LBL}>
                  <select value={svc.service_type} onChange={(e) => setSvc({ ...svc, service_type: e.target.value })} className="field" style={{ width: '100%' }}>
                    {SERVICE_TYPES.map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                  </select>
                </Field>
                <Field label="السعر (ر.س)" labelStyle={LBL}><input type="number" min={0} step="0.01" value={svc.price} onChange={(e) => setSvc({ ...svc, price: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="price" /></Field>
                <Field label="مدة التسليم (يوم)" labelStyle={LBL}><input type="number" min={0} value={svc.delivery_days} onChange={(e) => setSvc({ ...svc, delivery_days: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              </div>
              <Field label="الوصف" labelStyle={LBL}><input value={svc.description} onChange={(e) => setSvc({ ...svc, description: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              <div>
                <button disabled={busy} onClick={() => post('/services', svc, () => setSvc({ service_type: 'post', price: '', delivery_days: '', description: '' }))}
                  className="btn btn-sm btn-primary">إضافة</button>
              </div>
            </div>
          </div>
        </Sec>
      )}

      {tab === 'portfolio' && (
        <Sec title="نماذج الأعمال" icon="image">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            {portfolio.length === 0 ? (
              <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا نماذج أعمال. أضِف أعمالًا سابقة لتقوية ترشيحك.</div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '.7rem' }}>
                {portfolio.map((p) => (
                  <div key={p.id} className="card" style={{ padding: '.75rem .9rem', display: 'grid', gap: '.35rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem' }}>
                      <span className="ih-tag" style={{ fontSize: '.62rem' }}>{PORTFOLIO_TYPES.find(([k]) => k === p.type)?.[1] ?? p.type}</span>
                      {p.category && <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{p.category}</span>}
                    </div>
                    {p.previousBrand && <div style={{ fontWeight: 600, fontSize: '.85rem' }}>{p.previousBrand}</div>}
                    {p.description && <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>{p.description}</div>}
                    <div style={{ display: 'flex', gap: '.35rem', marginTop: '.2rem' }}>
                      {p.url && <a href={p.url} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline" style={{ flex: 1, textAlign: 'center' }}>فتح</a>}
                      <button disabled={busy} onClick={() => post(`/portfolio/${p.id}/delete`, {})} className="btn btn-xs btn-ghost">أرشفة</button>
                    </div>
                  </div>
                ))}
              </div>
            )}
            <div className="card" style={{ padding: '.9rem', display: 'grid', gap: '.7rem' }}>
              <div style={{ fontWeight: 700, fontSize: '.85rem' }}>إضافة نموذج عمل</div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '.7rem' }}>
                <Field label="النوع" labelStyle={LBL}>
                  <select value={pfo.type} onChange={(e) => setPfo({ ...pfo, type: e.target.value })} className="field" style={{ width: '100%' }}>
                    {PORTFOLIO_TYPES.map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                  </select>
                </Field>
                <Field label="الرابط" labelStyle={LBL}><input value={pfo.url} onChange={(e) => setPfo({ ...pfo, url: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="https://…" /><Err k="url" /></Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.7rem' }}>
                <Field label="التصنيف" labelStyle={LBL}><input value={pfo.category} onChange={(e) => setPfo({ ...pfo, category: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
                <Field label="العلامة السابقة" labelStyle={LBL}><input value={pfo.previous_brand} onChange={(e) => setPfo({ ...pfo, previous_brand: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              </div>
              <Field label="الوصف" labelStyle={LBL}><input value={pfo.description} onChange={(e) => setPfo({ ...pfo, description: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              <div>
                <button disabled={busy} onClick={() => post('/portfolio', pfo, () => setPfo({ type: 'link', url: '', category: '', previous_brand: '', description: '' }))}
                  className="btn btn-sm btn-primary">إضافة</button>
              </div>
            </div>
          </div>
        </Sec>
      )}

      {tab === 'verification' && (
        <Sec title="توثيق موثوق" icon="shield-check">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
              <span style={{ fontSize: '.85rem', fontWeight: 600 }}>الحالة</span>
              <VerifyBadge v={mowthooq} />
            </div>
            <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
              تُدخل بيانات ترخيصك هنا وتراجعها الوكالة — لا يُعتمد التوثيق ذاتيًا.
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.7rem', maxWidth: 520 }}>
              <Field label="رقم الترخيص" labelStyle={LBL}><input value={mw.mowthooq_license_number} onChange={(e) => setMw({ ...mw, mowthooq_license_number: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              <Field label="تاريخ الانتهاء" labelStyle={LBL}><input type="date" value={mw.mowthooq_expires_at} onChange={(e) => setMw({ ...mw, mowthooq_expires_at: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="mowthooq_expires_at" /></Field>
            </div>
            <div><button disabled={busy} onClick={() => post('/mowthooq', mw)} className="btn btn-sm btn-primary">حفظ</button></div>
          </div>
        </Sec>
      )}

      {tab === 'financial' && (
        <Sec title="البيانات المالية" icon="wallet">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
              <span style={{ fontSize: '.85rem', fontWeight: 600 }}>الحالة</span>
              <VerifyBadge v={financial} />
            </div>
            <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
              الآيبان يُخزَّن مُشفَّرًا ولا يُعرض كاملًا بعد الحفظ — تُعرض آخر أربعة أرقام فقط.
              أي تعديل يعيد الحالة إلى «قيد التحقّق» لمراجعة الوكالة.
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.7rem', maxWidth: 560 }}>
              <Field label="اسم المستفيد" labelStyle={LBL}><input value={fin.beneficiary_name} onChange={(e) => setFin({ ...fin, beneficiary_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              <Field label="البنك" labelStyle={LBL}><input value={fin.bank_name} onChange={(e) => setFin({ ...fin, bank_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
            </div>
            <Field style={{ maxWidth: 560 }} labelStyle={LBL}
              label={<>الآيبان{financial.ibanLast4 ? ` (المحفوظ ينتهي بـ${financial.ibanLast4})` : ''}</>}>
              <input value={fin.iban} onChange={(e) => setFin({ ...fin, iban: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }}
                placeholder={financial.ibanLast4 ? 'اتركه فارغًا للإبقاء على الحالي' : 'SA…'} />
              <Err k="iban" />
            </Field>
            <div><button disabled={busy} onClick={() => post('/financial', fin, () => setFin({ ...fin, iban: '' }))} className="btn btn-sm btn-primary">حفظ</button></div>
          </div>
        </Sec>
      )}
      {tab === 'security' && (
        <AccountSecurity base="/account/settings" prefs={prefs} categories={categories}
          sessions={sessions} twoFactorEnabled={twoFactorEnabled} />
      )}
    </AppShell>
  );
}
