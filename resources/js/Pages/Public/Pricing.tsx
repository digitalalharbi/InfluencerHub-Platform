import { Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'

interface Plan {
  key: string
  name: string
  for: string
  highlight?: boolean
  includes: string[]
  limitsNote: string
}

/**
 * الأسعار — بلا أرقام وبلا شعارات وسائل دفع.
 *
 * لا مزوّد دفع مربوط ولا سجلّات أسعار في قاعدة البيانات، فأي رقم بالريال هنا
 * يكون اختلاقًا، وأي شعار «مدى» أو «آبل باي» يعد بشراء لا يحدث. البديل الصادق:
 * ما تحصل عليه في كل باقة + تواصل يُنهي التسعير.
 */
export default function Pricing({ plans }: { plans: Plan[] }) {
  return (
    <PublicLayout
      title="الأسعار"
      description="باقات إنفلونسر هَب حسب القدرات: البداية، النمو، والمؤسسات. التسعير يُحدَّد بالتواصل — لا شراء ذاتي حاليًّا."
    >
      <section className="pub-hero">
        <div className="pub-wrap">
          <h1 className="pub-hero-title">باقات بالقدرة، وتسعير بالمحادثة</h1>
          <p className="pub-hero-lede">
            ما تحصل عليه في كل باقة مكتوب كاملًا أدناه. الرقم يعتمد على حجم فريقك وعدد
            حملاتك، ونصل إليه معك في مكالمة قصيرة.
          </p>
        </div>
      </section>

      <section className="pub-wrap pub-section">
        {/* الإفصاح قبل الباقات لا بعدها: من يقرأ الأسعار يستحق أن يعرف حدّ ما نستطيع تقديمه الآن */}
        <div className="pub-notice">
          لا يوجد شراء أو دفع مباشر من الموقع حاليًّا — لم يُربط مزوّد دفع بعد. لن تجد هنا
          شاشة دفع تُوهمك باشتراك لا يُفعَّل. الاشتراك يُرتَّب معك مباشرةً، ونبدأ بفترة تجربة
          على بياناتك.
        </div>

        <div className="pub-plan-grid">
          {plans.map((p) => (
            <div key={p.key} className={p.highlight ? 'pub-plan is-highlight' : 'pub-plan'}>
              {p.highlight && <span className="pub-plan-tag">الأكثر ملاءمة</span>}
              <h2 className="pub-plan-name">{p.name}</h2>
              <p className="pub-muted pub-plan-for">{p.for}</p>
              <ul className="pub-plan-list">
                {p.includes.map((f) => (
                  <li key={f}>{f}</li>
                ))}
              </ul>
              <p className="pub-plan-limits">{p.limitsNote}</p>
              <Link href="/demo" className="btn btn-primary">
                تواصل معنا
              </Link>
            </div>
          ))}
        </div>
      </section>

      <section className="pub-band">
        <div className="pub-wrap pub-section">
          <h2 className="pub-h2">أسئلة تتكرّر عن الاشتراك</h2>
          <div className="pub-faq">
            <details>
              <summary>هل أستطيع الدفع ببطاقة أو مدى؟</summary>
              <p>
                ليس بعد. لا يوجد مزوّد دفع مربوط بالنظام، والتحصيل يتمّ خارجه حاليًّا حسب
                الاتفاق. سنعلن ذلك هنا فور توفّره.
              </p>
            </details>
            <details>
              <summary>هل توجد فترة تجربة؟</summary>
              <p>
                نعم. نبدأ بمساحة عمل تجريبية على بياناتك أنت لا على بيانات عرض، فترى
                المنتَج في سياقك الفعلي قبل الالتزام.
              </p>
            </details>
            <details>
              <summary>ماذا يحدث عند بلوغ حدود الباقة؟</summary>
              <p>
                لكل باقة حدود على المستخدمين والحملات النشطة والمساحة التخزينية والتصدير
                الشهري. عند الاقتراب من الحدّ يظهر لك ذلك داخل النظام، وتستطيع توسيعه
                بإضافة أو بالانتقال إلى باقة أعلى.
              </p>
            </details>
            <details>
              <summary>هل يشمل الاشتراك حسابات صنّاع المحتوى؟</summary>
              <p>
                حساب صانع المحتوى مجاني له. الاشتراك يخصّ مساحة عمل الوكالة أو العميل.
              </p>
            </details>
            <details>
              <summary>هل يمكن تشغيل النظام داخل بنيتنا؟</summary>
              <p>
                نعم ضمن باقة المؤسسات — النظام يدعم النشر المخصّص، وتُحدَّد التفاصيل
                والحدود في الاتفاق.
              </p>
            </details>
          </div>

          <p className="pub-center" style={{ marginBlockStart: '2rem' }}>
            <Link href="/help">بقيّة الأسئلة في صفحة المساعدة</Link>
          </p>
        </div>
      </section>

      <section className="pub-wrap pub-section pub-center">
        <h2 className="pub-h2">لنبدأ بمحادثة</h2>
        <p className="pub-lede">
          أخبرنا بحجم فريقك وعدد حملاتك الشهرية، ونعود إليك بباقة وسعر يناسبانك.
        </p>
        <div className="pub-hero-cta">
          <Link href="/demo" className="btn btn-primary btn-lg">
            اطلب عرضًا توضيحيًا
          </Link>
          <Link href="/register" className="btn btn-outline btn-lg">
            إنشاء حساب
          </Link>
        </div>
      </section>
    </PublicLayout>
  )
}
