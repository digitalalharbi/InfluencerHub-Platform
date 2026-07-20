import { Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'

/** تأكيد الاستلام — بمرجع يستطيع المستخدم ذكره عند المتابعة. */
export default function SignupSubmitted({
  reference,
  typeLabel,
  email,
}: {
  reference: string
  typeLabel: string
  email: string
}) {
  return (
    <PublicLayout title="استلمنا طلبك">
      <section className="pub-wrap pub-section pub-center" style={{ maxWidth: 560 }}>
        <h1 className="pub-h1">استلمنا طلبك</h1>
        <p className="pub-lede">
          طلب تسجيل {typeLabel} وصلنا، وسنتواصل معك على <b style={{ direction: 'ltr' }}>{email}</b>{' '}
          خلال يوم عمل.
        </p>

        <div className="pub-reference">
          <span>رقم الطلب</span>
          <strong style={{ direction: 'ltr' }}>{reference}</strong>
        </div>

        <p className="pub-muted">احتفظ بهذا الرقم للرجوع إليه عند المتابعة.</p>

        <div className="pub-hero-cta">
          <Link href="/" className="btn btn-outline">
            العودة للرئيسية
          </Link>
        </div>
      </section>
    </PublicLayout>
  )
}
