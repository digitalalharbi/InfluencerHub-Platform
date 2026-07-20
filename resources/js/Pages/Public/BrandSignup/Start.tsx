import { useForm, Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Steps from './Steps'

/**
 * بداية رحلة العلامة: بريد واحد.
 *
 * لا يُفحص وجود البريد هنا ولا يُقال «هذا البريد مسجَّل» — ذلك يكشف حساباتنا
 * لمن يجرّب العناوين. الردّ واحد مهما كانت الحال.
 */
export default function Start() {
  const { data, setData, post, processing, errors } = useForm({ email: '' })

  return (
    <PublicLayout title="سجّل علامتك التجارية">
      <section className="pub-wrap pub-section" style={{ maxWidth: 520 }}>
        <Steps current="email" />

        <h1 className="pub-h1">سجّل علامتك التجارية</h1>
        <p className="pub-lede">
          مساحة تملكها علامتك: تُطلق حملاتك، وتختار مبدعيك، وتُدير عقودك ومدفوعاتك —
          سواء شغّلتها بنفسك أو فوّضت وكالة بنطاق تحدّده أنت.
        </p>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post('/register/brand/start')
          }}
          className="pub-form"
        >
          <label className="pub-field">
            <span>
              البريد الإلكتروني للعمل<b aria-hidden="true"> *</b>
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
            <small className="pub-muted">
              بريدٌ على نطاق شركتك يختصر خطوات إثبات الملكية لاحقًا.
            </small>
          </label>

          <button type="submit" className="btn btn-primary btn-lg" disabled={processing}>
            {processing ? 'جارٍ الإرسال…' : 'متابعة'}
          </button>
        </form>

        <p className="pub-muted pub-center" style={{ marginBlockStart: '2rem' }}>
          أنت وكالة لا علامة؟ <Link href="/register/agency">أنشئ مساحة وكالة</Link>
        </p>
        <p className="pub-muted pub-center">
          لديك حساب؟ <Link href="/login">تسجيل الدخول</Link>
        </p>
      </section>
    </PublicLayout>
  )
}
