import { useForm, Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Stepper from './Stepper'

/** تأكيد البريد — الخطوة التي تمنع إنشاء مساحات ببُرُد لا يملكها أحد. */
export default function Verify({
  reference,
  email,
  status,
  steps,
  completedSteps,
}: {
  reference: string
  email: string
  status: string
  steps: Record<string, string>
  completedSteps: string[]
}) {
  const { data, setData, post, processing, errors } = useForm({ code: '' })

  return (
    <PublicLayout title="تأكيد البريد">
      <section className="pub-wrap pub-section" style={{ maxWidth: 520 }}>
        <Stepper steps={steps} status={status} completedSteps={completedSteps} />

        <h1 className="pub-h1">تأكيد بريدك</h1>
        <p className="pub-lede">
          أرسلنا رمزًا من 6 أرقام إلى <b style={{ direction: 'ltr' }}>{email}</b>. صلاحيته 15 دقيقة.
        </p>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post(`/register/agency/verify/${reference}`)
          }}
          className="pub-form"
        >
          <label className="pub-field">
            <span>رمز التحقّق</span>
            <input
              value={data.code}
              onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
              className="field pub-code-input"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={6}
              autoFocus
            />
            {errors.code && <em className="pub-field-error">{errors.code}</em>}
          </label>

          <button
            type="submit"
            className="btn btn-primary btn-lg"
            disabled={processing || data.code.length !== 6}
          >
            {processing ? 'جارٍ التأكيد…' : 'تأكيد'}
          </button>

          {data.code.length !== 6 && (
            <span className="pub-muted" style={{ fontSize: '.8rem' }}>
              أدخل الأرقام الستّة ليُصبح التأكيد متاحًا
            </span>
          )}
        </form>

        <p className="pub-muted pub-center" style={{ marginBlockStart: '1.5rem' }}>
          لم يصلك الرمز؟{' '}
          <Link href={`/register/agency/resend/${reference}`} method="post" as="button">
            إرسال رمز جديد
          </Link>
        </p>
      </section>
    </PublicLayout>
  )
}
