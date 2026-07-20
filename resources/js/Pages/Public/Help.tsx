import { Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'

interface FaqGroup {
  title: string
  items: { q: string; a: string }[]
}

/**
 * المساعدة — أسئلة وأجوبة مجموعة حسب المرحلة التي يقف عندها السائل.
 *
 * `details/summary` بدل أكورديون بحالة React: يعمل بلا جافاسكربت، ويبحث فيه
 * متصفّح المستخدم بـCtrl+F حتى وهو مطويّ، ويحمل دلالات وصول جاهزة.
 */
export default function Help({ groups }: { groups: FaqGroup[] }) {
  return (
    <PublicLayout
      title="المساعدة"
      description="أجوبة عن البدء وأنواع الحسابات والتشغيل والاشتراك والبيانات في إنفلونسر هَب."
    >
      <section className="pub-hero">
        <div className="pub-wrap">
          <h1 className="pub-hero-title">المساعدة</h1>
          <p className="pub-hero-lede">
            أجوبة مباشرة عن أكثر ما يُسأل — بما فيه ما لا يعمل بعد. إن لم تجد سؤالك، اطلب
            عرضًا توضيحيًا واكتبه في «ما تودّ رؤيته».
          </p>
        </div>
      </section>

      <section className="pub-wrap pub-section">
        <nav className="pub-jump" aria-label="أقسام المساعدة">
          {groups.map((g) => (
            <a key={g.title} href={`#${slug(g.title)}`}>
              {g.title}
            </a>
          ))}
        </nav>

        {groups.map((g) => (
          <div key={g.title} className="pub-faq-group">
            <h2 id={slug(g.title)} className="pub-faq-title">
              {g.title}
            </h2>
            <div className="pub-faq">
              {g.items.map((it) => (
                <details key={it.q}>
                  <summary>{it.q}</summary>
                  <p>{it.a}</p>
                </details>
              ))}
            </div>
          </div>
        ))}
      </section>

      <section className="pub-band">
        <div className="pub-wrap pub-section pub-center">
          <h2 className="pub-h2">لم تجد ما تبحث عنه؟</h2>
          <p className="pub-lede">
            اطلب عرضًا توضيحيًا واكتب سؤالك في الطلب — نجيبك عليه في الجلسة أو قبلها.
          </p>
          <div className="pub-hero-cta">
            <Link href="/demo" className="btn btn-primary btn-lg">
              اطلب عرضًا توضيحيًا
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

/** معرّف لاتيني للمرساة: العناوين عربية، ومعرّف عربي في الـURL يُرمَّز فيصبح غير مقروء. */
function slug(title: string): string {
  return 'faq-' + [...title].map((c) => c.charCodeAt(0).toString(16)).join('')
}
