import { useForm, Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Stepper from './Stepper'

/**
 * بداية المسار الذاتي: بريد واحد فقط.
 * نطلب أقلّ ما يمكن هنا — بقية البيانات بعد التحقّق، فلا يُهجَر النموذج قبل أن يبدأ.
 */
export default function Start({ steps }: { steps: Record<string, string> }) {
  const { data, setData, post, processing, errors } = useForm({ email: '' })

  return (
    <PublicLayout title="إنشاء مساحة وكالة">
      <section className="pub-wrap pub-section" style={{ maxWidth: 520 }}>
        <Stepper steps={steps} status="email_verification_pending" completedSteps={[]} />

        <h1 className="pub-h1">أنشئ مساحة وكالتك</h1>
        <p className="pub-lede">
          ابدأ بتجربة مجانية 14 يومًا. بلا بطاقة ولا دفع — أدخل بريدك فقط.
        </p>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post('/register/agency/start')
          }}
          className="pub-form"
        >
          <label className="pub-field">
            <span>
              البريد الإلكتروني<b aria-hidden="true"> *</b>
            </span>
            <input
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              className="field"
              style={{ direction: 'ltr' }}
              autoComplete="email"
              autoFocus
            />
            {errors.email && <em className="pub-field-error">{errors.email}</em>}
          </label>

          <button type="submit" className="btn btn-primary btn-lg" disabled={processing}>
            {processing ? 'جارٍ الإرسال…' : 'متابعة'}
          </button>
        </form>

        <p className="pub-muted pub-center" style={{ marginBlockStart: '2rem' }}>
          تحتاج خطة مخصّصة أو حسابًا مؤسسيًّا؟{' '}
          <Link href="/register/agency/enterprise">تواصل معنا</Link>
        </p>
        <p className="pub-muted pub-center">
          لديك حساب؟ <Link href="/login">تسجيل الدخول</Link>
        </p>
      </section>
    </PublicLayout>
  )
}
