import { Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'

interface FeatureItem {
  title: string
  body: string
  /** ملاحظة صدق: ما لا يعمل بعد أو ما ينتظر اعتمادًا خارجيًّا — تُعرض بجانب الميزة لا في هامش منسيّ. */
  status?: string
}

interface FeatureGroup {
  title: string
  summary: string
  items: FeatureItem[]
}

/**
 * المزايا — مجموعة لكل طبقة تشغيل، بالترتيب الذي يعيشه المستخدم:
 * عمله اليومي، ثم علاقاته، ثم تنفيذه، ثم ماليّته، ثم ما يسند ذلك كلّه.
 * ترتيب أبجدي أو حسب «الأهم» كان سيخفي أن هذه سلسلة متّصلة.
 */
export default function Features({ groups }: { groups: FeatureGroup[] }) {
  return (
    <PublicLayout
      title="المزايا"
      description="ما تديره فعلًا في إنفلونسر هَب: الطلبات، الحملات، الترشيحات، المحتوى واعتماده، العقود، المستحقات، التقارير، والصلاحيات."
    >
      <section className="pub-hero">
        <div className="pub-wrap">
          <h1 className="pub-hero-title">كل ما تحتاجه الحملة، في مكان واحد</h1>
          <p className="pub-hero-lede">
            هذه ليست قائمة أمنيات. كل بند هنا مبنيّ ويعمل، وما ينتظر اعتمادًا خارجيًّا مكتوب
            بجانبه صراحةً.
          </p>
        </div>
      </section>

      {groups.map((g, i) => (
        // شريط ملوّن بالتناوب يفصل المجموعات بصريًّا دون خطوط فاصلة إضافية
        <section key={g.title} className={i % 2 === 1 ? 'pub-band' : undefined}>
          <div className="pub-wrap pub-section">
            <h2 className="pub-h2">{g.title}</h2>
            <p className="pub-lede pub-center" style={{ marginBlockStart: '-1.5rem', marginBlockEnd: '2.25rem' }}>
              {g.summary}
            </p>
            <div className="pub-cap-grid">
              {g.items.map((it) => (
                <div key={it.title} className="pub-cap">
                  <h3>{it.title}</h3>
                  <p className="pub-muted">{it.body}</p>
                  {it.status && <p className="pub-cap-status">{it.status}</p>}
                </div>
              ))}
            </div>
          </div>
        </section>
      ))}

      <section className="pub-wrap pub-section pub-center">
        <h2 className="pub-h2">تريد رؤيتها على بياناتك؟</h2>
        <div className="pub-hero-cta">
          <Link href="/demo" className="btn btn-primary btn-lg">
            اطلب عرضًا توضيحيًا
          </Link>
          <Link href="/pricing" className="btn btn-outline btn-lg">
            اطّلع على الباقات
          </Link>
        </div>
      </section>
    </PublicLayout>
  )
}
