import { Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'

interface SolutionContent {
  label: string
  title: string
  lede: string
  painsTitle: string
  /** «قبل ← بعد»: أصدق من قائمة مزايا، لأنها تصف حالة يعرفها القارئ من عمله. */
  pains: { from: string; to: string }[]
  capsTitle: string
  caps: { t: string; d: string }[]
  ctaPrimary: { label: string; href: string }
}

/**
 * صفحة واحدة تخدم الأدوار الثلاثة: البنية ثابتة والمحتوى يأتي من الخادم.
 * ثلاث نسخ متطابقة الشكل كانت ستتفرّق مع أول تعديل على التخطيط.
 */
export default function Solution({ content }: { role: string; content: SolutionContent }) {
  return (
    <PublicLayout title={`الحلول — ${content.label}`} description={content.lede}>
      <section className="pub-hero">
        <div className="pub-wrap">
          <p className="pub-eyebrow">{content.label}</p>
          <h1 className="pub-hero-title">{content.title}</h1>
          <p className="pub-hero-lede">{content.lede}</p>
          <div className="pub-hero-cta">
            <Link href={content.ctaPrimary.href} className="btn btn-primary btn-lg">
              {content.ctaPrimary.label}
            </Link>
            <Link href="/demo" className="btn btn-outline btn-lg">
              اطلب عرضًا توضيحيًا
            </Link>
          </div>
        </div>
      </section>

      <section className="pub-band">
        <div className="pub-wrap pub-section">
          <h2 className="pub-h2">{content.painsTitle}</h2>
          <ul className="pub-shift-list">
            {content.pains.map((p) => (
              <li key={p.from}>
                <span className="pub-shift-from">{p.from}</span>
                <span className="pub-shift-arrow" aria-hidden="true">
                  ←
                </span>
                <span className="pub-shift-to">{p.to}</span>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="pub-wrap pub-section">
        <h2 className="pub-h2">{content.capsTitle}</h2>
        <div className="pub-cap-grid">
          {content.caps.map((c) => (
            <div key={c.t} className="pub-cap">
              <h3>{c.t}</h3>
              <p className="pub-muted">{c.d}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="pub-band">
        <div className="pub-wrap pub-section pub-center">
          <h2 className="pub-h2">ابدأ من هنا</h2>
          <div className="pub-hero-cta">
            <Link href={content.ctaPrimary.href} className="btn btn-primary btn-lg">
              {content.ctaPrimary.label}
            </Link>
            <Link href="/features" className="btn btn-outline btn-lg">
              اطّلع على المزايا
            </Link>
          </div>
        </div>
      </section>
    </PublicLayout>
  )
}
