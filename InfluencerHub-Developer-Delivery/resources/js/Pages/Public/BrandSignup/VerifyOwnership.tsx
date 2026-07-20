import { useForm } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Steps from './Steps'

type Claim = {
  reference: string
  status: string
  statusLabel: string
  infoRequested: string | null
  documents: number
}

const DOC_TYPES = [
  { value: 'commercial_registration', label: 'السجلّ التجاري' },
  { value: 'authorization_letter', label: 'خطاب تفويض' },
  { value: 'trademark', label: 'شهادة علامة تجارية' },
  { value: 'other', label: 'مستند آخر' },
]

/**
 * إثبات الملكية.
 *
 * ## ما لا تقوله هذه الصفحة
 *
 * لا تذكر أن علامةً مطابِقة **موجودة** عندنا، ولا اسمها، ولا من يديرها. نصّها
 * صحيح سواء وُجد سجلّ أو لم يوجد — فلا يستفيد منه من يجرّب الأسماء ليعرف من
 * هم عملاؤنا.
 *
 * ولهذا لا يظهر هنا شيء مشتقّ من نتيجة المطابقة: لا درجة، ولا مؤشّرات، ولا
 * إشارة إلى قوّة التطابق.
 */
export default function VerifyOwnership({
  reference,
  brandName,
  claim,
}: {
  reference: string
  brandName: string
  claim: Claim | null
}) {
  const form = useForm({ statement: '', role: '' })
  const upload = useForm<{ document: File | null; type: string }>({
    document: null,
    type: 'commercial_registration',
  })

  return (
    <PublicLayout title="إثبات ملكية العلامة">
      <section className="pub-wrap pub-section" style={{ maxWidth: 640 }}>
        <Steps current="owner" />

        <h1 className="pub-h1">نحتاج إثبات ملكيّتك</h1>
        <p className="pub-lede">
          «{brandName}» اسمٌ يحتاج إثباتًا قبل فتح مساحة باسمه. راجعْنا سيتحقّق من
          طلبك، ولا يُمنح أيّ وصول قبل ذلك.
        </p>

        {!claim && (
          <form
            onSubmit={(e) => {
              e.preventDefault()
              form.post(`/register/brand/claim/${reference}`)
            }}
            className="pub-form"
          >
            <label className="pub-field">
              <span>
                صفتك في المؤسسة<b aria-hidden="true"> *</b>
              </span>
              <input
                type="text"
                value={form.data.role}
                onChange={(e) => form.setData('role', e.target.value)}
                className="field"
                placeholder="مدير التسويق، المالك، …"
                autoFocus
              />
              {form.errors.role && <em className="pub-field-error">{form.errors.role}</em>}
            </label>

            <label className="pub-field">
              <span>
                بيان الملكية<b aria-hidden="true"> *</b>
              </span>
              <textarea
                value={form.data.statement}
                onChange={(e) => form.setData('statement', e.target.value)}
                className="field"
                rows={5}
                placeholder="اشرح علاقتك بالعلامة وما يثبتها."
              />
              {form.errors.statement && <em className="pub-field-error">{form.errors.statement}</em>}
            </label>

            <button type="submit" className="btn btn-primary btn-lg" disabled={form.processing}>
              {form.processing ? 'جارٍ الإرسال…' : 'أرسل الطلب'}
            </button>
          </form>
        )}

        {claim && (
          <>
            <div className="pub-notice" style={{ marginBlockEnd: '2rem' }}>
              <p>
                <b>حالة الطلب:</b> {claim.statusLabel}
              </p>
              {claim.infoRequested && (
                <p>
                  <b>المطلوب منك:</b> {claim.infoRequested}
                </p>
              )}
              <p className="pub-muted">
                المستندات المرفوعة: {claim.documents} — سنراسلك على بريدك عند صدور القرار.
              </p>
            </div>

            <h2 className="pub-h2">أرفق مستندًا</h2>
            <form
              onSubmit={(e) => {
                e.preventDefault()
                upload.post(`/register/brand/claim/${reference}/document`, {
                  forceFormData: true,
                  onSuccess: () => upload.reset(),
                })
              }}
              className="pub-form"
            >
              <label className="pub-field">
                <span>نوع المستند</span>
                <select
                  value={upload.data.type}
                  onChange={(e) => upload.setData('type', e.target.value)}
                  className="field"
                >
                  {DOC_TYPES.map((t) => (
                    <option key={t.value} value={t.value}>
                      {t.label}
                    </option>
                  ))}
                </select>
              </label>

              <label className="pub-field">
                <span>الملفّ</span>
                <input
                  type="file"
                  accept=".pdf,.png,.jpg,.jpeg"
                  onChange={(e) => upload.setData('document', e.target.files?.[0] ?? null)}
                  className="field"
                />
                {upload.errors.document && (
                  <em className="pub-field-error">{upload.errors.document}</em>
                )}
                <small className="pub-muted">PDF أو صورة، حتّى 10 ميغابايت.</small>
              </label>

              <button
                type="submit"
                className="btn btn-secondary"
                disabled={upload.processing || !upload.data.document}
              >
                {upload.processing ? 'جارٍ الرفع…' : 'ارفع المستند'}
              </button>
            </form>
          </>
        )}
      </section>
    </PublicLayout>
  )
}
