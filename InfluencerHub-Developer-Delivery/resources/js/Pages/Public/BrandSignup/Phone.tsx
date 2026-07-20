import { useForm } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Steps from './Steps'

/**
 * الجوال ورمزه في صفحة واحدة.
 *
 * الرقم يُدخَل ثم يظهر حقل الرمز تحته مباشرةً — بلا انتقال صفحة، فالانتقال
 * هنا يُفقد السياق ويجعل تصحيح رقمٍ خاطئ رحلةً كاملة.
 */
export default function Phone({
  reference,
  phone,
}: {
  reference: string
  phone: string | null
}) {
  const start = useForm({ phone: phone ?? '' })
  const verify = useForm({ code: '' })
  const resend = useForm({ channel: 'phone' })

  const sent = Boolean(phone)

  return (
    <PublicLayout title="تأكيد الجوال">
      <section className="pub-wrap pub-section" style={{ maxWidth: 520 }}>
        <Steps current="phone" />

        <h1 className="pub-h1">رقم الجوال</h1>
        <p className="pub-lede">
          نستعمله لتأكيد هويّتك وللتنبيهات العاجلة على حملاتك — لا للتسويق.
        </p>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            start.post(`/register/brand/phone/${reference}`)
          }}
          className="pub-form"
        >
          <label className="pub-field">
            <span>
              رقم الجوال<b aria-hidden="true"> *</b>
            </span>
            <input
              type="tel"
              value={start.data.phone}
              onChange={(e) => start.setData('phone', e.target.value)}
              className="field"
              style={{ direction: 'ltr' }}
              placeholder="+9665XXXXXXXX"
              autoComplete="tel"
              autoFocus={!sent}
            />
            {start.errors.phone && <em className="pub-field-error">{start.errors.phone}</em>}
          </label>

          <button type="submit" className="btn btn-secondary" disabled={start.processing}>
            {start.processing ? 'جارٍ الإرسال…' : sent ? 'أرسل الرمز مرّة أخرى' : 'أرسل رمز التأكيد'}
          </button>
        </form>

        {sent && (
          <form
            onSubmit={(e) => {
              e.preventDefault()
              verify.post(`/register/brand/phone/${reference}/verify`)
            }}
            className="pub-form"
            style={{ marginBlockStart: '2rem' }}
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
                value={verify.data.code}
                onChange={(e) => verify.setData('code', e.target.value.replace(/\D/g, ''))}
                className="field"
                style={{ direction: 'ltr', letterSpacing: '.5em', textAlign: 'center' }}
                autoFocus
              />
              {verify.errors.code && <em className="pub-field-error">{verify.errors.code}</em>}
            </label>

            <button
              type="submit"
              className="btn btn-primary btn-lg"
              disabled={verify.processing || verify.data.code.length < 6}
            >
              {verify.processing ? 'جارٍ التحقّق…' : 'تأكيد ومتابعة'}
            </button>

            <p className="pub-muted pub-center">
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
          </form>
        )}
      </section>
    </PublicLayout>
  )
}
