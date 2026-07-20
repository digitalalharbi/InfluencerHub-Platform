import { useForm } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Steps from './Steps'

export default function VerifyEmail({ reference, email }: { reference: string; email: string }) {
  const { data, setData, post, processing, errors } = useForm({ code: '' })
  const resend = useForm({ channel: 'email' })

  return (
    <PublicLayout title="تأكيد البريد">
      <section className="pub-wrap pub-section" style={{ maxWidth: 520 }}>
        <Steps current="email" />

        <h1 className="pub-h1">أدخل رمز التأكيد</h1>
        <p className="pub-lede">
          أرسلنا رمزًا من ستّ خانات إلى <b style={{ direction: 'ltr', display: 'inline-block' }}>{email}</b>.
          ينتهي خلال 15 دقيقة.
        </p>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post(`/register/brand/verify/${reference}`)
          }}
          className="pub-form"
        >
          <label className="pub-field">
            <span>
              رمز التأكيد<b aria-hidden="true"> *</b>
            </span>
            <input
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={6}
              value={data.code}
              onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
              className="field"
              style={{ direction: 'ltr', letterSpacing: '.5em', textAlign: 'center' }}
              autoFocus
            />
            {errors.code && <em className="pub-field-error">{errors.code}</em>}
          </label>

          <button type="submit" className="btn btn-primary btn-lg" disabled={processing || data.code.length < 6}>
            {processing ? 'جارٍ التحقّق…' : 'تأكيد'}
          </button>
        </form>

        <p className="pub-muted pub-center" style={{ marginBlockStart: '1.5rem' }}>
          لم يصلك الرمز؟{' '}
          <button
            type="button"
            className="btn btn-ghost btn-sm"
            disabled={resend.processing}
            onClick={() => resend.post(`/register/brand/resend/${reference}`)}
          >
            أعد الإرسال
          </button>
        </p>
      </section>
    </PublicLayout>
  )
}
