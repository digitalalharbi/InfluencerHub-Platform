import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { u } from '@/lib/href';

interface Contact { phone: string | null; phoneDisplay: string | null; whatsapp: string | null; hasPhone: boolean }
interface Overlay { favorite: boolean; tags: string[]; notes: string | null; negotiatedRate: number | null; relationshipStatus: string | null; tenantRating: string | null; lastContactedAt: string | null }
interface Creator {
  id: number; name: string; platform: string; platformLabel: string; accountUrl: string | null;
  followers: number | null; likes: number | null; tier: string | null; gender: string | null;
  categories: string[]; showsFace: boolean | null; region: string | null; city: string | null;
  rating: string | null; creatorType: string; creatorTypeLabel: string;
  referenceRate: number | null; referenceRateNote: string; dataFreshness: string; lastImportedAt: string | null;
  contact?: Contact; overlay?: Overlay | null;
}
interface Props { base: string; creator: Creator; canContact: boolean; canUseInCampaign: boolean; campaigns: { id: number; name: string }[] }

function kfmt(n: number | null): string {
  if (n === null) return '—';
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1000) return Math.round(n / 1000) + 'K';
  return String(n);
}

export default function CreatorDatabaseShow({ creator: c, canContact, canUseInCampaign, campaigns }: Props) {
  const waLink = (p: string) => `https://wa.me/${p}`;
  const [busy, setBusy] = useState(false);
  const [notes, setNotes] = useState(c.overlay?.notes ?? '');
  const [campaignId, setCampaignId] = useState(campaigns[0]?.id ? String(campaigns[0].id) : '');

  const toggleFav = () => { setBusy(true); router.post(u(`/creator-database/${c.id}/overlay`), { favorite: !(c.overlay?.favorite) }, { preserveScroll: true, onFinish: () => setBusy(false) }); };
  const saveNotes = () => { setBusy(true); router.post(u(`/creator-database/${c.id}/overlay`), { notes }, { preserveScroll: true, onFinish: () => setBusy(false) }); };
  const nominate = () => { if (!campaignId) return; setBusy(true); router.post(u(`/creator-database/${c.id}/nominate`), { campaign_id: campaignId }, { preserveScroll: true, onFinish: () => setBusy(false) }); };

  return (
    <AppShell heading="قاعدة المؤثرين">
      <Head title={c.name} />

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '.8rem' }}>
        <a href={u('/creator-database')} className="btn btn-xs btn-ghost">← كل المبدعين</a>
        <button onClick={toggleFav} disabled={busy} className={`btn btn-xs ${c.overlay?.favorite ? 'btn-primary' : 'btn-outline'}`}>
          {c.overlay?.favorite ? '★ مفضّل' : '☆ إضافة للمفضّلة'}
        </button>
      </div>

      <div className="card" style={{ padding: '1.3rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '.9rem' }}>
          <span className="ih-idc__av" style={{ width: 54, height: 54, fontSize: '1.2rem' }}>{c.name.slice(0, 1)}</span>
          <div>
            <h1 style={{ fontWeight: 800, fontSize: '1.3rem', margin: 0 }}>{c.name}</h1>
            <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem', marginTop: '.2rem' }}>
              <span className="ih-tag">{c.platformLabel}</span>{' '}
              <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{c.creatorTypeLabel}</span>
              {c.tier && <> · فئة {c.tier}</>}
            </div>
          </div>
        </div>

        <div className="ih-mcard__grid" style={{ marginTop: '1.1rem' }}>
          <div className="ih-metric"><span className="ih-metric__v">{kfmt(c.followers)}</span><span className="ih-metric__k">متابع</span></div>
          <div className="ih-metric"><span className="ih-metric__v">{kfmt(c.likes)}</span><span className="ih-metric__k">إعجاب</span></div>
          <div className="ih-metric"><span className="ih-metric__v">{c.showsFace ? 'نعم' : '—'}</span><span className="ih-metric__k">يظهر الوجه</span></div>
          <div className="ih-metric"><span className="ih-metric__v">{c.rating ?? '—'}</span><span className="ih-metric__k">التقييم</span></div>
        </div>
      </div>

      <div className="card" style={{ padding: '1.1rem', marginTop: '1rem' }}>
        <h3 style={{ fontWeight: 700, margin: '0 0 .7rem' }}>معلومات</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px,1fr))', gap: '.7rem', fontSize: '.86rem' }}>
          {([['المنطقة', c.region], ['المدينة', c.city], ['الجنس', c.gender === 'female' ? 'أنثى' : c.gender === 'male' ? 'ذكر' : '—']] as [string, string | null][]).map(([k, v]) => (
            <div key={k}><div style={{ color: 'var(--ih-text-muted)', fontSize: '.76rem' }}>{k}</div><div>{v ?? '—'}</div></div>
          ))}
          {c.referenceRate !== null && (
            <div><div style={{ color: 'var(--ih-text-muted)', fontSize: '.76rem' }}>سعر مرجعي</div><div>{c.referenceRate.toLocaleString('en-US')} ر.س <span style={{ color: 'var(--ih-text-muted)', fontSize: '.7rem' }}>· غير مضمون</span></div></div>
          )}
        </div>
        {c.categories.length > 0 && (
          <div style={{ marginTop: '.9rem', display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
            {c.categories.map((cat, i) => <span key={i} className="ih-tag">{cat}</span>)}
          </div>
        )}
        <div style={{ marginTop: '.9rem', fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>
          {c.dataFreshness}{c.lastImportedAt && <> · آخر تحديث: {c.lastImportedAt}</>}
        </div>
      </div>

      {canContact && c.contact?.hasPhone && (
        <div className="card" style={{ padding: '1.1rem', marginTop: '1rem' }}>
          <h3 style={{ fontWeight: 700, margin: '0 0 .7rem' }}>التواصل</h3>
          <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center', flexWrap: 'wrap' }}>
            <span style={{ direction: 'ltr', fontWeight: 600 }}>{c.contact.phoneDisplay}</span>
            <button onClick={() => navigator.clipboard?.writeText(c.contact!.phone!)} className="btn btn-xs">نسخ</button>
            <a href={`tel:${c.contact.phone}`} className="btn btn-xs btn-outline">اتصال</a>
            <a href={waLink(c.contact.whatsapp!)} target="_blank" rel="noreferrer" className="btn btn-xs btn-primary">واتساب</a>
            {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noreferrer" className="btn btn-xs btn-outline">الحساب الاجتماعي</a>}
          </div>
        </div>
      )}

      {/* ملاحظات المؤسسة الخاصّة (لا تراها مؤسسة أخرى) */}
      <div className="card" style={{ padding: '1.1rem', marginTop: '1rem' }}>
        <h3 style={{ fontWeight: 700, margin: '0 0 .5rem' }}>ملاحظاتك الخاصّة</h3>
        <textarea value={notes} onChange={(e) => setNotes(e.target.value)} className="field" rows={3} style={{ width: '100%' }} placeholder="ملاحظات خاصّة بمؤسستك عن هذا المبدع…" />
        <div style={{ marginTop: '.5rem' }}><button onClick={saveNotes} disabled={busy} className="btn btn-sm btn-outline">حفظ الملاحظات</button></div>
      </div>

      {canUseInCampaign && (
        <div className="card" style={{ padding: '1.1rem', marginTop: '1rem' }}>
          <h3 style={{ fontWeight: 700, margin: '0 0 .5rem' }}>ترشيح لحملة</h3>
          <p style={{ color: 'var(--ih-text-muted)', fontSize: '.82rem', margin: '0 0 .6rem' }}>يُضاف المبدع إلى قاعدة علاقاتك ويُرشَّح مباشرةً — يتقدّم بذلك المرحلة الثانية للحملة.</p>
          {campaigns.length === 0 ? (
            <div style={{ color: 'var(--ih-text-muted)', fontSize: '.82rem' }}>لا حملات متاحة للترشيح.</div>
          ) : (
            <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center', flexWrap: 'wrap' }}>
              <select value={campaignId} onChange={(e) => setCampaignId(e.target.value)} className="field" style={{ maxWidth: 240 }}>
                {campaigns.map((c2) => <option key={c2.id} value={c2.id}>{c2.name}</option>)}
              </select>
              <button onClick={nominate} disabled={busy || !campaignId} className="btn btn-sm btn-primary">إضافة وترشيح</button>
            </div>
          )}
        </div>
      )}
    </AppShell>
  );
}
