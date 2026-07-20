import { useForm } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Steps from './Steps'

/**
 * آخر خطوة: حساب المالك، ثم تُفتح المساحة ويُسجَّل الدخول فورًا.
 *
 * لا نطلب البريد ثانيةً — تحقّقنا منه في أوّل الرحلة، وإعادة طلبه تدعو إلى
 * إدخال بريدٍ مختلف عمّا تحقّقنا منه.
 */
export default function Owner({
  reference,
  email,
  brandName,
}: {
  reference: string
  email: string
  brandName: string
}) {
  const { data, setData, post, processing, errors } = useForm({
    owner_name: '',
    password: '',
    password_confirmation: '',
  })

  return (
    <PublicLayout title="إنشاء حسابك">
      <section className="pub-wrap pub-section" style={{ maxWidth: 520 }}>
        <Steps current="owner" />

        <h1 className="pub-h1">أنشئ حسابك</h1>
        <p className="pub-lede">
          ستكون مالك مساحة <b>{brandName}</b>، ولك وحدك دعوة الفريق وتفويض الوكالات.
        </p>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post(`/register/brand/complete/${reference}`)
          }}
          className="pub-form"
        >
          <label className="pub-field">
            <span>
              اسمك<b aria-hidden="true"> *</b>
            </span>
            <input
              type="text"
              value={data.owner_name}
              onChange={(e) => setData('owner_name', e.target.value)}
              className="field"
              autoComplete="name"
              autoFocus
            />
            {errors.owner_name && <em className="pub-field-error">{errors.owner_name}</em>}
          </label>

          <label className="pub-field">
            <span>البريد الإلكتروني</span>
            <input
              type="email"
              value={email}
              className="field"
              style={{ direction: 'ltr' }}
              disabled
              readOnly
            />
            <small className="pub-muted">مؤكَّد — هو بريد دخولك.</small>
          </label>

          <label className="pub-field">
            <span>
              كلمة المرور<b aria-hidden="true"> *</b>
            </span>
            <input
              type="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              className="field"
              style={{ direction: 'ltr' }}
              autoComplete="new-password"
            />
            {errors.password && <em className="pub-field-error">{errors.password}</em>}
            <small className="pub-muted">ثمانية محارف على الأقلّ، فيها حرف ورقم.</small>
          </label>

          <label className="pub-field">
            <span>
              تأكيد كلمة المرور<b aria-hidden="true"> *</b>
            </span>
            <input
              type="password"
              value={data.password_confirmation}
              onChange={(e) => setData('password_confirmation', e.target.value)}
              className="field"
              style={{ direction: 'ltr' }}
              autoComplete="new-password"
            />
          </label>

          <button type="submit" className="btn btn-primary btn-lg" disabled={processing}>
            {processing ? 'جارٍ إنشاء المساحة…' : 'أنشئ المساحة'}
          </button>
        </form>
      </section>
    </PublicLayout>
  )
}
