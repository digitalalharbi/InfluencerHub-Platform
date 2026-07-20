import { useForm } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'

/**
 * نموذج طلب فتح حساب (عميل/وكالة).
 * نقول للمستخدم صراحةً أن هذا طلب يُراجَع — لا نُوهمه بتفعيل فوري لا يحدث.
 */
export default function SignupRequestPage({
  accountType,
  typeLabel,
}: {
  accountType: string
  typeLabel: string
}) {
  const isAgency = accountType === 'agency'
  const { data, setData, post, processing, errors } = useForm({
    contact_name: '',
    email: '',
    phone: '',
    company_name: '',
    website: '',
    country_code: 'SA',
    team_size: '',
    monthly_campaigns: '',
    notes: '',
  })

  return (
    <PublicLayout title={`تسجيل ${typeLabel}`}>
      <section className="pub-wrap pub-section" style={{ maxWidth: 640 }}>
        <h1 className="pub-h1">تسجيل {typeLabel}</h1>
        <p className="pub-lede">
          {isAgency
            ? 'أرسل بيانات وكالتك وسنتواصل معك لتفعيل مساحة العمل واختيار الباقة المناسبة.'
            : 'أرسل بيانات شركتك وسنربطك بالوكالة المناسبة لبدء حملتك الأولى.'}
        </p>

        <div className="pub-notice">
          هذا طلب يُراجَع يدويًّا وليس تفعيلًا فوريًّا. تفعيل المساحة يرتبط باختيار باقة
          وترتيب الاشتراك، ونتواصل معك على بريدك خلال يوم عمل.
        </div>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post(`/register/${accountType}`)
          }}
          className="pub-form"
        >
          <Field label="الاسم" error={errors.contact_name} required>
            <input
              value={data.contact_name}
              onChange={(e) => setData('contact_name', e.target.value)}
              className="field"
              autoComplete="name"
            />
          </Field>

          <Field label="البريد الإلكتروني" error={errors.email} required>
            <input
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              className="field"
              autoComplete="email"
              style={{ direction: 'ltr' }}
            />
          </Field>

          <Field label="رقم الجوال" error={errors.phone}>
            <input
              value={data.phone}
              onChange={(e) => setData('phone', e.target.value)}
              className="field"
              autoComplete="tel"
              style={{ direction: 'ltr' }}
            />
          </Field>

          <Field
            label={isAgency ? 'اسم الوكالة' : 'اسم الشركة أو العلامة'}
            error={errors.company_name}
            required
          >
            <input
              value={data.company_name}
              onChange={(e) => setData('company_name', e.target.value)}
              className="field"
            />
          </Field>

          <Field label="الموقع الإلكتروني" error={errors.website}>
            <input
              value={data.website}
              onChange={(e) => setData('website', e.target.value)}
              className="field"
              style={{ direction: 'ltr' }}
              placeholder="https://"
            />
          </Field>

          {isAgency ? (
            <Field label="حجم الفريق" error={errors.team_size}>
              <select
                value={data.team_size}
                onChange={(e) => setData('team_size', e.target.value)}
                className="field"
              >
                <option value="">اختر…</option>
                <option value="1-5">1–5</option>
                <option value="6-20">6–20</option>
                <option value="21-50">21–50</option>
                <option value="50+">أكثر من 50</option>
              </select>
            </Field>
          ) : null}

          <Field label="عدد الحملات المتوقّع شهريًّا" error={errors.monthly_campaigns}>
            <select
              value={data.monthly_campaigns}
              onChange={(e) => setData('monthly_campaigns', e.target.value)}
              className="field"
            >
              <option value="">اختر…</option>
              <option value="1-2">1–2</option>
              <option value="3-10">3–10</option>
              <option value="10+">أكثر من 10</option>
            </select>
          </Field>

          <Field label="تفاصيل إضافية" error={errors.notes}>
            <textarea
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              className="field"
              rows={4}
            />
          </Field>

          <button type="submit" className="btn btn-primary btn-lg" disabled={processing}>
            {processing ? 'جارٍ الإرسال…' : 'إرسال الطلب'}
          </button>
        </form>
      </section>
    </PublicLayout>
  )
}

function Field({
  label,
  error,
  required,
  children,
}: {
  label: string
  error?: string
  required?: boolean
  children: React.ReactNode
}) {
  return (
    <label className="pub-field">
      <span>
        {label}
        {required && <b aria-hidden="true"> *</b>}
      </span>
      {children}
      {error && <em className="pub-field-error">{error}</em>}
    </label>
  )
}
