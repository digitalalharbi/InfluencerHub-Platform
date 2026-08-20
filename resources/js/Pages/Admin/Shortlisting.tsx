import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { adminNav } from '@/lib/nav'
import { ListHead } from '@/Components/ui'
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
interface Props {
  query: string
  criteria: Record<string, unknown>
  understood: string[]
  results: Result[]
  hasSearch: boolean
  assistant: { driver: string; openaiReady: boolean }
  poolSize: number
}

const EXAMPLES = [
  'مؤثرة عناية في الرياض بمتابعين فوق 500 ألف وميزانية 20000',
  'مشاهير سناب رياضة أقل من 10000 ريال',
  'تيك توك تغطيات جدة',
]

const fmt = (n: number | null) =>
  n == null ? '—' : n >= 1000 ? (n / 1000).toFixed(n >= 10000 ? 0 : 1).replace('.0', '') + 'K' : String(n)

/**
 * ترشيح المؤثرين — محرّك بحث/مساعد فوق القاعدة. لمدير النظام وحده.
 * المساعد يفهم الطلب النصّي (قواعد اليوم، OpenAI لاحقًا) ويعرض ما فهمه بشفافية.
 */
export default function Shortlisting({ query, understood, results, hasSearch, assistant, poolSize }: Props) {
  const [text, setText] = useState(query)

  const search = (q: string) => {
    setText(q)
    router.get(u('/shortlisting'), q ? { query: q } : {}, { preserveState: true })
  }

  return (
    <AppShell heading="ترشيح المؤثرين" nav={adminNav} portal="admin"
      wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="ترشيح المؤثرين" />
      <ListHead eyebrow="مدير النظام · محرّك ذكي" title="ترشيح المؤثرين"
        sub={`ابحث في ${poolSize.toLocaleString('en-US')} مؤثرًا بالوصف الطبيعي — المساعد يفهم طلبك ويرتّب الأنسب.`} />

      {/* صندوق البحث/المساعد */}
      <div style={{ background: 'linear-gradient(135deg, var(--ih-primary-soft), var(--ih-surface))',
        border: '1px solid var(--ih-primary-200, var(--ih-border))', borderRadius: 14, padding: '1.2rem 1.3rem', marginBottom: '1.2rem' }}>
        <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center', flexWrap: 'wrap' }}>
          <input className="field" style={{ flex: 1, minWidth: 240, fontSize: '1rem' }}
            placeholder="صِف من تبحث عنه… مثال: مؤثرة عناية في الرياض بميزانية 20000"
            value={text} onChange={(e) => setText(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && search(text)} autoFocus />
          <button className="btn" onClick={() => search(text)}>ترشيح</button>
          {text && <button className="btn btn-ghost btn-sm" onClick={() => search('')}>مسح</button>}
        </div>
        <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', marginTop: '.7rem' }}>
          <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>جرّب:</span>
          {EXAMPLES.map((ex) => (
            <button key={ex} className="btn btn-xs btn-outline" onClick={() => search(ex)}>{ex}</button>
          ))}
        </div>
        {/* شفافية سائق المساعد */}
        <div style={{ marginTop: '.7rem', fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>
          المساعد: {assistant.openaiReady ? 'OpenAI (مربوط)' : 'قواعد لغوية · مساعد OpenAI جاهز للربط لاحقًا (يحتاج مفتاحًا)'}
        </div>
      </div>

      {/* ما فهمه المساعد — شفافية قابلة للتصحيح */}
      {understood.length > 0 && (
        <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', alignItems: 'center', marginBottom: '1rem' }}>
          <span style={{ fontSize: '.8rem', color: 'var(--ih-text-secondary)' }}>فهمتُ:</span>
          {understood.map((u2, i) => (
            <span key={i} className="badge" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}>{u2}</span>
          ))}
        </div>
      )}

      {!hasSearch ? (
        <div className="ih-empty"><div className="ih-empty__inner">
          <div className="ih-empty__title">ابدأ بوصف حملتك أو المؤثر</div>
          <div className="ih-empty__text">اكتب طلبك بالعربية الطبيعية، أو اختر مثالًا أعلاه.</div>
        </div></div>
      ) : results.length === 0 ? (
        <div className="ih-empty"><div className="ih-empty__inner">
          <div className="ih-empty__title">لا مطابقات</div>
          <div className="ih-empty__text">وسّع المعايير أو جرّب وصفًا آخر.</div>
        </div></div>
      ) : (
        <>
          <div style={{ fontSize: '.82rem', color: 'var(--ih-text-secondary)', marginBottom: '.7rem' }}>
            أفضل {results.length} ترشيحًا — مرتّبة بالملاءمة
          </div>
          <div style={{ display: 'grid', gap: '.7rem' }}>
            {results.map((c, rank) => (
              <div key={c.id} className="card" style={{ padding: '.9rem 1.1rem', display: 'grid', gridTemplateColumns: 'auto 1fr auto', gap: '1rem', alignItems: 'center' }}>
                {/* الترتيب + الدرجة */}
                <div style={{ textAlign: 'center', minWidth: 56 }}>
                  <div style={{ fontSize: '1.3rem', fontWeight: 800,
                    color: (c.matchScore ?? 0) >= 60 ? 'var(--ih-success-700, #067647)' : 'var(--ih-warning-ink, #B54708)' }}>
                    {c.matchScore ?? '—'}٪
                  </div>
                  <div style={{ fontSize: '.66rem', color: 'var(--ih-text-muted)' }}>#{rank + 1} ملاءمة</div>
                </div>
                {/* التفاصيل */}
                <div style={{ minWidth: 0 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', flexWrap: 'wrap' }}>
                    <b>{c.name}</b>
                    {c.tier && <span className="badge">{c.tier}</span>}
                    <span style={{ fontSize: '.78rem', color: 'var(--ih-text-secondary)' }}>{c.platformLabel} · {fmt(c.followers)} متابع</span>
                    {c.sourceType === 'ugc' && <span className="badge" style={{ fontSize: '.6rem' }}>UGC</span>}
                  </div>
                  <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginTop: '.3rem' }}>
                    {c.matchReasons.slice(0, 3).map((r, i) => (
                      <span key={i} style={{ fontSize: '.68rem', background: 'var(--ih-success-soft, #ECFDF3)', color: 'var(--ih-success-700, #067647)', borderRadius: 6, padding: '.05rem .4rem' }}>{r}</span>
                    ))}
                    {c.matchFlags.slice(0, 2).map((r, i) => (
                      <span key={i} style={{ fontSize: '.68rem', background: 'var(--ih-warning-soft, #FFFAEB)', color: 'var(--ih-warning-ink, #B54708)', borderRadius: 6, padding: '.05rem .4rem' }}>{r}</span>
                    ))}
                  </div>
                  <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginTop: '.35rem', display: 'flex', gap: '.8rem', flexWrap: 'wrap' }}>
                    <span>{[c.city, c.region].filter(Boolean).join(' · ') || '—'}</span>
                    {c.phone && <span>📞 <span style={{ direction: 'ltr' }}>{c.phone}</span></span>}
                    {(c.sellPost || c.costPost) && <span>منشور: تكلفة {c.costPost ?? '—'} / بيع {c.sellPost ?? '—'} ر.س</span>}
                    {(c.sellCoverage || c.costCoverage) && <span>تغطية: تكلفة {c.costCoverage ?? '—'} / بيع {c.sellCoverage ?? '—'} ر.س</span>}
                  </div>
                </div>
                {/* إجراء */}
                <div style={{ display: 'grid', gap: '.35rem' }}>
                  {c.accountUrl && <a href={c.accountUrl} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline">الحساب ↗</a>}
                  <a href={u(`/creator-pool?q=${encodeURIComponent(c.name)}`)} className="btn btn-xs">في القاعدة</a>
                </div>
              </div>
            ))}
          </div>
        </>
      )}
    </AppShell>
  )
}
