import { useForm } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Steps from './Steps'

type Social = { platform: string; handle: string }

const PLATFORMS = ['instagram', 'tiktok', 'x', 'snapchat', 'youtube', 'linkedin']

/**
 * بيانات المؤسسة والعلامة.
 *
 * السجلّ التجاري والموقع اختياريان لكنّ ذكرهما يختصر إثبات الملكية لاحقًا —
 * وهذا مكتوب للمستخدم صراحةً بدل أن يُترك ليكتشفه متعثّرًا في الخطوة التالية.
 */
export default function Details({ reference }: { reference: string }) {
  const { data, setData, post, processing, errors } = useForm({
    legal_name: '',
    commercial_registration: '',
    brand_name: '',
    sector: '',
    website: '',
    description: '',
    social_accounts: [] as Social[],
  })

  const addSocial = () =>
    setData('social_accounts', [...data.social_accounts, { platform: 'instagram', handle: '' }])

  const updateSocial = (i: number, patch: Partial<Social>) =>
    setData(
      'social_accounts',
      data.social_accounts.map((s, j) => (j === i ? { ...s, ...patch } : s)),
    )

  const removeSocial = (i: number) =>
    setData(
      'social_accounts',
      data.social_accounts.filter((_, j) => j !== i),
    )

  return (
    <PublicLayout title="بيانات العلامة">
      <section className="pub-wrap pub-section" style={{ maxWidth: 640 }}>
        <Steps current="details" />

        <h1 className="pub-h1">بيانات المؤسسة والعلامة</h1>
        <p className="pub-lede">
          هذه ما يراه المبدعون قبل قبول التعاون، وما نبني عليه عقودك وفواتيرك.
        </p>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            post(`/register/brand/details/${reference}`)
          }}
          className="pub-form"
        >
          <h2 className="pub-h2">المؤسسة</h2>

          <label className="pub-field">
            <span>
              الاسم النظامي<b aria-hidden="true"> *</b>
            </span>
            <input
              type="text"
              value={data.legal_name}
              onChange={(e) => setData('legal_name', e.target.value)}
              className="field"
              autoFocus
            />
            {errors.legal_name && <em className="pub-field-error">{errors.legal_name}</em>}
          </label>

          <label className="pub-field">
            <span>السجلّ التجاري</span>
            <input
              type="text"
              value={data.commercial_registration}
              onChange={(e) => setData('commercial_registration', e.target.value)}
              className="field"
              style={{ direction: 'ltr' }}
            />
            {errors.commercial_registration && (
              <em className="pub-field-error">{errors.commercial_registration}</em>
            )}
            <small className="pub-muted">اختياري — لكنّه يختصر إثبات الملكية إن طُلب منك.</small>
          </label>

          <h2 className="pub-h2" style={{ marginBlockStart: '2rem' }}>
            العلامة
          </h2>

          <label className="pub-field">
            <span>
              اسم العلامة<b aria-hidden="true"> *</b>
            </span>
            <input
              type="text"
              value={data.brand_name}
              onChange={(e) => setData('brand_name', e.target.value)}
              className="field"
            />
            {errors.brand_name && <em className="pub-field-error">{errors.brand_name}</em>}
          </label>

          <label className="pub-field">
            <span>القطاع</span>
            <input
              type="text"
              value={data.sector}
              onChange={(e) => setData('sector', e.target.value)}
              className="field"
              placeholder="أزياء، أغذية، تقنية…"
            />
            {errors.sector && <em className="pub-field-error">{errors.sector}</em>}
          </label>

          <label className="pub-field">
            <span>الموقع الإلكتروني</span>
            <input
              type="text"
              value={data.website}
              onChange={(e) => setData('website', e.target.value)}
              className="field"
              style={{ direction: 'ltr' }}
              placeholder="example.com"
            />
            {errors.website && <em className="pub-field-error">{errors.website}</em>}
          </label>

          <label className="pub-field">
            <span>وصف مختصر</span>
            <textarea
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              className="field"
              rows={3}
            />
            {errors.description && <em className="pub-field-error">{errors.description}</em>}
          </label>

          <fieldset className="pub-field" style={{ border: 0, padding: 0, margin: 0 }}>
            <legend style={{ padding: 0 }}>حسابات التواصل</legend>

            {data.social_accounts.map((s, i) => (
              <div key={i} style={{ display: 'flex', gap: '.5rem', marginBlockEnd: '.5rem' }}>
                <select
                  value={s.platform}
                  onChange={(e) => updateSocial(i, { platform: e.target.value })}
                  className="field"
                  style={{ maxWidth: 150 }}
                  aria-label="المنصّة"
                >
                  {PLATFORMS.map((p) => (
                    <option key={p} value={p}>
                      {p}
                    </option>
                  ))}
                </select>
                <input
                  type="text"
                  value={s.handle}
                  onChange={(e) => updateSocial(i, { handle: e.target.value })}
                  className="field"
                  style={{ direction: 'ltr', flex: 1 }}
                  placeholder="@handle"
                  aria-label="المعرّف"
                />
                <button type="button" className="btn btn-ghost" onClick={() => removeSocial(i)}>
                  حذف
                </button>
              </div>
            ))}

            {data.social_accounts.length < 10 && (
              <button type="button" className="btn btn-secondary" onClick={addSocial}>
                + أضف حسابًا
              </button>
            )}
          </fieldset>

          <button type="submit" className="btn btn-primary btn-lg" disabled={processing}>
            {processing ? 'جارٍ الحفظ…' : 'متابعة'}
          </button>
        </form>
      </section>
    </PublicLayout>
  )
}
