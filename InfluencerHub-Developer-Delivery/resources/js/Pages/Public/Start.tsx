import { Head, Link, useForm } from '@inertiajs/react'
import { useState } from 'react'

/* ============================================================================
   `/start` — المسار الرسمي الوحيد لاختيار نوع الحساب وبدء التسجيل.
   ============================================================================

   خطوتان في صفحة واحدة: اختيار الصفة، ثم البريد. والبريد يُدخَل هنا مرّة
   ويُسلَّم إلى تدفّق النوع مع رمزه — فلا يُطلب مرّتين.

   و`?type=` يُملأ مسبقًا من الرابط، فيصل الزائر من إعلانٍ موجَّه إلى خطوته
   مباشرةً بلا إعادة اختيار.
   ========================================================================= */

type AccountType = {
  key: string
  label: string
  title: string
  icon: string
  hint: string
  summary: string
  benefits: string[]
  login: string
}

type Props = {
  accountTypes: AccountType[]
  selected: string | null
  prefill: { email: string | null }
  carry: Record<string, string>
}

const P: Record<string, string> = {
  building: 'M3 21h18M5 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16M13 9h5a1 1 0 0 1 1 1v11M8 8h2M8 12h2M8 16h2',
  briefcase: 'M3 8h18v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8ZM9 8V6a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18',
  spark: 'M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.8 2.8M14.9 14.9l2.8 2.8M17.7 6.3l-2.8 2.8M9.1 14.9l-2.8 2.8',
  check: 'M20 6 9 17l-5-5',
}

function Icon({ name, size = 16 }: { name: string; size?: number }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.9"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d={P[name] ?? P.check} />
    </svg>
  )
}

export default function Start({ accountTypes, selected, prefill, carry }: Props) {
  const initial = accountTypes.find((t) => t.key === selected) ?? accountTypes[0]
  const [type, setType] = useState<AccountType>(initial)

  // المعاملات المحمولة تُرسَل مع النموذج فلا تنقطع سلسلة الإحالة عند POST
  const form = useForm({ type: initial.key, email: prefill.email ?? '', ...carry })

  const pick = (t: AccountType) => {
    setType(t)
    form.setData('type', t.key)
  }

  return (
    <div className="gw gw--start" dir="rtl">
      <Head>
        <title>ابدأ الآن</title>
      </Head>

      <header className="gw-header">
        <div className="gw-wrap gw-header-inner">
          <Link href="/" className="gw-brand">
            <span className="gw-mark" aria-hidden="true">
              <Icon name="spark" size={17} />
            </span>
            إنفلونسر هَب
          </Link>

          <div className="gw-header-actions" style={{ marginInlineStart: 'auto' }}>
            <Link href={type.login} className="gw-btn gw-btn--ghost">
              تسجيل الدخول
            </Link>
          </div>
        </div>
      </header>

      <main className="gw-wrap gw-start-main">
        <section className="gw-card gw-start-card">
          <h1>ما نوع حسابك؟</h1>
          <p className="gw-access-lede">نفتح لك المسار المناسب — ولكل نوع أدواته.</p>

          <fieldset className="gw-roles">
            <legend className="gw-label">أنا</legend>

            {accountTypes.map((t) => (
              <label key={t.key} className="gw-role">
                <input
                  type="radio"
                  name="type"
                  value={t.key}
                  checked={type.key === t.key}
                  onChange={() => pick(t)}
                />
                <span className="gw-role-icon" aria-hidden="true">
                  <Icon name={t.icon} />
                </span>
                <span className="gw-role-text">
                  <b>{t.title}</b>
                  <span>{t.hint}</span>
                </span>
              </label>
            ))}
          </fieldset>

          <form
            className="gw-actions"
            onSubmit={(e) => {
              e.preventDefault()
              form.post('/start')
            }}
          >
            <label className="pub-field">
              <span>
                البريد الإلكتروني<b aria-hidden="true"> *</b>
              </span>
              <input
                type="email"
                className="field"
                style={{ direction: 'ltr' }}
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
                autoComplete="email"
                autoFocus
              />
              {form.errors.email && <em className="pub-field-error">{form.errors.email}</em>}
              {form.errors.type && <em className="pub-field-error">{form.errors.type}</em>}
            </label>

            <button
              type="submit"
              className="gw-btn gw-btn--primary gw-btn--lg gw-btn--block"
              disabled={form.processing}
            >
              {form.processing ? 'جارٍ المتابعة…' : `متابعة كـ${type.label}`}
            </button>
          </form>

          <p className="gw-muted-note">سنرسل رمز تأكيد إلى بريدك للمتابعة.</p>
        </section>

        {/* ما يناله هذا النوع تحديدًا — يتغيّر بتغيّر الاختيار */}
        <aside className="gw-card gw-start-aside">
          <h2>{type.title}</h2>
          <p className="gw-access-lede">{type.summary}</p>

          <ul className="gw-start-benefits">
            {type.benefits.map((b) => (
              <li key={b}>
                <Icon name="check" size={14} /> {b}
              </li>
            ))}
          </ul>
        </aside>
      </main>
    </div>
  )
}
