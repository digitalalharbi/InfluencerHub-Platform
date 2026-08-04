import { useMemo, useState } from 'react'
import { useForm } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'
import Steps from './Steps'

type CountryDial = {
  code: string
  name: string
  dial: string
  nationalPrefix?: string
  placeholder: string
}

const COUNTRIES: CountryDial[] = [
  { code: 'EG', name: 'مصر', dial: '+20', nationalPrefix: '0', placeholder: '1090962585' },
  { code: 'SA', name: 'السعودية', dial: '+966', nationalPrefix: '0', placeholder: '5XXXXXXXX' },
  { code: 'AE', name: 'الإمارات', dial: '+971', nationalPrefix: '0', placeholder: '5XXXXXXXX' },
  { code: 'KW', name: 'الكويت', dial: '+965', placeholder: 'XXXXXXXX' },
  { code: 'QA', name: 'قطر', dial: '+974', placeholder: 'XXXXXXXX' },
  { code: 'BH', name: 'البحرين', dial: '+973', placeholder: 'XXXXXXXX' },
  { code: 'OM', name: 'عُمان', dial: '+968', placeholder: 'XXXXXXXX' },
  { code: 'JO', name: 'الأردن', dial: '+962', nationalPrefix: '0', placeholder: '7XXXXXXXX' },
  { code: 'LB', name: 'لبنان', dial: '+961', nationalPrefix: '0', placeholder: 'XXXXXXXX' },
  { code: 'MA', name: 'المغرب', dial: '+212', nationalPrefix: '0', placeholder: '6XXXXXXXX' },
  { code: 'US', name: 'الولايات المتحدة', dial: '+1', placeholder: 'XXXXXXXXXX' },
]

function splitPhone(phone: string | null): { country: CountryDial; local: string } {
  const raw = (phone ?? '').replace(/[\s()-]/g, '')
  const country = COUNTRIES.find((item) => raw.startsWith(item.dial)) ?? COUNTRIES[0]
  const local = raw.startsWith(country.dial) ? raw.slice(country.dial.length) : raw.replace(/^\+/, '')

  return { country, local }
}

function buildInternationalPhone(country: CountryDial, local: string): string {
  const digits = local.replace(/\D/g, '')
  const normalized = country.nationalPrefix && digits.startsWith(country.nationalPrefix)
    ? digits.slice(country.nationalPrefix.length)
    : digits

  return `${country.dial}${normalized}`
}

export default function Phone({
  reference,
  phone,
}: {
  reference: string
  phone: string | null
}) {
  const initial = useMemo(() => splitPhone(phone), [phone])
  const [countryCode, setCountryCode] = useState(initial.country.code)
  const [localPhone, setLocalPhone] = useState(initial.local)
  const start = useForm({ phone: phone ?? '' })
  const verify = useForm({ code: '' })
  const resend = useForm({ channel: 'phone' })

  const country = COUNTRIES.find((item) => item.code === countryCode) ?? COUNTRIES[0]
  const fullPhone = buildInternationalPhone(country, localPhone)
  const sent = Boolean(phone)

  return (
    <PublicLayout title="تأكيد الجوال">
      <section className="pub-wrap pub-section" style={{ maxWidth: 560 }}>
        <Steps current="phone" />

        <h1 className="pub-h1">رقم الجوال</h1>
        <p className="pub-lede">
          نستعمله لتأكيد هويتك والتنبيهات العاجلة على حملاتك، لا للتسويق.
        </p>

        {!sent && (
          <form
            onSubmit={(e) => {
            e.preventDefault()
            start
              .transform(() => ({ phone: fullPhone }))
              .post(`/register/brand/phone/${reference}`)
            }}
            className="pub-form"
          >
          <label className="pub-field">
            <span>
              رقم الجوال<b aria-hidden="true"> *</b>
            </span>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'minmax(150px, 190px) 1fr',
                gap: '.6rem',
                direction: 'rtl',
              }}
            >
              <select
                className="field"
                value={countryCode}
                onChange={(e) => setCountryCode(e.target.value)}
                aria-label="كود الدولة"
              >
                {COUNTRIES.map((item) => (
                  <option key={item.code} value={item.code}>
                    {item.name} {item.dial}
                  </option>
                ))}
              </select>
              <div style={{ display: 'flex', direction: 'ltr' }}>
                <span
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    paddingInline: '.85rem',
                    border: '1px solid var(--ih-border)',
                    borderInlineEnd: 0,
                    borderRadius: '10px 0 0 10px',
                    background: 'var(--ih-muted)',
                    color: 'var(--ih-text-muted)',
                    fontWeight: 700,
                  }}
                >
                  {country.dial}
                </span>
                <input
                  type="tel"
                  value={localPhone}
                  onChange={(e) => setLocalPhone(e.target.value.replace(/[^0-9\s-]/g, ''))}
                  className="field"
                  style={{ direction: 'ltr', borderRadius: '0 10px 10px 0' }}
                  placeholder={country.placeholder}
                  autoComplete="tel-national"
                  autoFocus={!sent}
                />
              </div>
            </div>
            <small className="pub-muted" style={{ display: 'block', marginTop: '.45rem', direction: 'ltr', textAlign: 'right' }}>
              سيتم الإرسال إلى {fullPhone}
            </small>
            {start.errors.phone && <em className="pub-field-error">{start.errors.phone}</em>}
          </label>

          <button type="submit" className="btn btn-secondary" disabled={start.processing || localPhone.replace(/\D/g, '').length < 6}>
            {start.processing ? 'جارٍ الإرسال…' : 'أرسل رمز التأكيد'}
          </button>
          </form>
        )}

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
