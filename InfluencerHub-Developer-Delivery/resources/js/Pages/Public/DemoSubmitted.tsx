import { Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'

/**
 * تأكيد استلام طلب العرض — بمرجع حقيقي محفوظ في قاعدة البيانات.
 * المرجع ليس زينة: به يتابع صاحب الطلب دون أن يعيد شرح من هو.
 */
export default function DemoSubmitted({
  reference,
  email,
  audienceLabel,
}: {
  reference: string
  email: string
  audienceLabel: string
}) {
  return (
    <PublicLayout title="استلمنا طلبك">
      <section className="pub-wrap pub-section pub-center" style={{ maxWidth: 560 }}>
        <h1 className="pub-h1">استلمنا طلبك</h1>
        <p className="pub-lede">
          طلب عرض توضيحي بصفتك «{audienceLabel}» وصلنا، وسنتواصل معك على{' '}
          <b style={{ direction: 'ltr' }}>{email}</b> لتحديد الموعد خلال يوم عمل.
        </p>

        <div className="pub-reference">
          <span>رقم الطلب</span>
          <strong style={{ direction: 'ltr' }}>{reference}</strong>
        </div>

        <p className="pub-muted">احتفظ بهذا الرقم للرجوع إليه عند المتابعة.</p>

        <div className="pub-hero-cta">
          <Link href="/features" className="btn btn-outline">
            اطّلع على المزايا
          </Link>
          <Link href="/" className="btn btn-outline">
            العودة للرئيسية
          </Link>
        </div>
      </section>
    </PublicLayout>
  )
}
