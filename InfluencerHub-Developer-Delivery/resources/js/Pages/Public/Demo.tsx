import { useForm } from '@inertiajs/react'
import type { ReactNode } from 'react'
import PublicLayout from '@/Layouts/PublicLayout'

interface Audience {
  key: string
  label: string
}

/**
 * طلب عرض توضيحي — نموذج يحفظ سجلًّا حقيقيًّا ويعيد مرجعًا.
 *
 * نسأل عن الجهة أولًا لأن ما يُعرض في الجلسة يختلف جذريًّا بينها: العميل يريد
 * رؤية الاعتماد والتقارير، والوكالة تريد التشغيل، وصانع المحتوى يريد ملفّه
 * وفرصه. عرض واحد لثلاثتهم يعني إضاعة وقت ثلثيهم.
 */
export default function Demo({ audiences }: { audiences: Audience[] }) {
  const { data, setData, post, processing, errors } = useForm({
    audience: 'agency',
    contact_name: '',
    email: '',
    phone: '',
    company_name: '',
    role_title: '',
    team_size: '',
    preferred_time: '',
    interests: '',
  })

  // صانع المحتوى فرد غالبًا — سؤاله عن اسم الجهة وحجم الفريق حقلان فارغان يملأهما بلا معنى
  const isCreator = data.audience === 'creator'

  return (
    <PublicLayout
      title="اطلب عرضًا توضيحيًا"
      description="اطلب جلسة عرض لإنفلونسر هَب مبنية على دورك: عميل، وكالة، أو صانع محتوى."
    >
      <section className="pub-wrap pub-section" style={{ maxWidth: 640 }}>
        <h1 className="pub-h1">اطلب عرضًا توضيحيًا</h1>
        <p className="pub-lede">
          جلسة قصيرة نمشي فيها على المسار الذي يخصّك أنت — من الطلب حتى التقرير — ونجيب عن
          أسئلتك مباشرةً.
        </p>

        <div className="pub-notice">
          نعرض النظام كما هو، بما فيه ما ينتظر اعتمادات خارجية ولا يعمل بعد. ولتوضيح ما لا
          نعد به: لا يوجد دفع أو شراء ذاتي من الموقع حاليًّا، والاشتراك يُرتَّب بالمحادثة.
        </div>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post('/demo')
          }}
          className="pub-form"
        >
          <Field label="أنت" error={errors.audience} required>
            <div className="pub-radio-row">
              {audiences.map((a) => (
                <label
                  key={a.key}
                  className={data.audience === a.key ? 'pub-radio is-selected' : 'pub-radio'}
                >
                  <input
                    type="radio"
                    name="audience"
                    value={a.key}
                    checked={data.audience === a.key}
                    onChange={(e) => setData('audience', e.target.value)}
                  />
                  <span>{a.label}</span>
                </label>
              ))}
            </div>
          </Field>

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

          {!isCreator && (
            <Field
              label={data.audience === 'agency' ? 'اسم الوكالة' : 'اسم الشركة أو العلامة'}
              error={errors.company_name}
            >
              <input
                value={data.company_name}
                onChange={(e) => setData('company_name', e.target.value)}
                className="field"
                autoComplete="organization"
              />
            </Field>
          )}

          <Field label={isCreator ? 'تخصّصك' : 'دورك في الجهة'} error={errors.role_title}>
            <input
              value={data.role_title}
              onChange={(e) => setData('role_title', e.target.value)}
              className="field"
              placeholder={isCreator ? 'مثال: إنتاج UGC، تصوير' : 'مثال: مدير تسويق'}
            />
          </Field>

          {!isCreator && (
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
          )}

          <Field label="الوقت المفضّل للتواصل" error={errors.preferred_time}>
            <select
              value={data.preferred_time}
              onChange={(e) => setData('preferred_time', e.target.value)}
              className="field"
            >
              <option value="">لا يهمّ</option>
              <option value="morning">صباحًا</option>
              <option value="afternoon">بعد الظهر</option>
              <option value="evening">مساءً</option>
            </select>
          </Field>

          <Field label="ما تودّ رؤيته تحديدًا" error={errors.interests}>
            <textarea
              value={data.interests}
              onChange={(e) => setData('interests', e.target.value)}
              className="field"
              rows={4}
              placeholder="مثال: كيف يعتمد العميل المحتوى، وكيف تُحسب مستحقّات الصنّاع"
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
  children: ReactNode
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
