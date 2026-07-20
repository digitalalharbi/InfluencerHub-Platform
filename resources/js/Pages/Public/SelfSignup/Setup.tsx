import { useForm, usePage } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Stepper from './Stepper'

/**
 * إعداد المساحة — الخطوة الأخيرة قبل الدخول.
 * نقول حالة الاشتراك بصدق: تجربة بلا دفع، والفوترة تُفعَّل عند ربط المزوّد.
 */
export default function Setup({
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
  // خطأ التفعيل عامّ لا حقليّ (بريد مستعمَل، مساحة أُنشئت) فيأتي مع أخطاء الصفحة
  const { errors: pageErrors } = usePage().props as { errors?: Record<string, string> }
  const setupError = pageErrors?.setup

  const { data, setData, post, processing, errors } = useForm({
    owner_name: '',
    organization_name: '',
    password: '',
    password_confirmation: '',
  })

  return (
    <PublicLayout title="إعداد مساحتك">
      <section className="pub-wrap pub-section" style={{ maxWidth: 560 }}>
        <Stepper steps={steps} status={status} completedSteps={completedSteps} />

        <h1 className="pub-h1">إعداد مساحتك</h1>
        <p className="pub-lede">
          سنُنشئ مساحة وكالتك وحساب المالك بالبريد <b style={{ direction: 'ltr' }}>{email}</b>.
        </p>

        <div className="pub-notice">
          تبدأ بتجربة مجانية 14 يومًا بلا بطاقة ولا دفع. الفوترة والاشتراك المدفوع غير مفعّلين بعد —
          سنُخطرك قبل انتهاء التجربة بوقت كافٍ.
        </div>

        {setupError && <div className="pub-error-banner">{setupError}</div>}

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post(`/register/agency/complete/${reference}`)
          }}
          className="pub-form"
        >
          <label className="pub-field">
            <span>
              اسمك<b aria-hidden="true"> *</b>
            </span>
            <input
              value={data.owner_name}
              onChange={(e) => setData('owner_name', e.target.value)}
              className="field"
              autoComplete="name"
              autoFocus
            />
            {errors.owner_name && <em className="pub-field-error">{errors.owner_name}</em>}
          </label>

          <label className="pub-field">
            <span>
              اسم الوكالة<b aria-hidden="true"> *</b>
            </span>
            <input
              value={data.organization_name}
              onChange={(e) => setData('organization_name', e.target.value)}
              className="field"
              autoComplete="organization"
            />
            {errors.organization_name && (
              <em className="pub-field-error">{errors.organization_name}</em>
            )}
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
              autoComplete="new-password"
            />
            <em className="pub-muted" style={{ fontSize: '.75rem', fontStyle: 'normal' }}>
              8 أحرف على الأقل، تتضمّن حرفًا ورقمًا
            </em>
            {errors.password && <em className="pub-field-error">{errors.password}</em>}
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
              autoComplete="new-password"
            />
          </label>

          <button type="submit" className="btn btn-primary btn-lg" disabled={processing}>
            {processing ? 'جارٍ إنشاء المساحة…' : 'إنشاء المساحة وبدء التجربة'}
          </button>
        </form>
      </section>
    </PublicLayout>
  )
}
