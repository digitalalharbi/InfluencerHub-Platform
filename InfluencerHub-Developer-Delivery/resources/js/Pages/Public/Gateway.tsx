import { Head, Link, router } from '@inertiajs/react'
import { useEffect, useState } from 'react'

/* ============================================================================
   بوّابة المنتَج — الجذر `/` داخل شاشة واحدة.
   ============================================================================

   البنية: شريط علوي · بطاقة وصول والخيارات الثلاثة ظاهرة بلا تمرير · معاينة
   المنتَج · أربع فوائد · خمس خطوات.

   والتفاصيل الزائدة في لوحة تُفتح لا أسفل الصفحة — فالأسفل لا يُقرأ أصلًا
   حين لا يوجد تمرير.
   ========================================================================= */

type AccountType = {
  key: string
  label: string
  title: string
  icon: string
  hint: string
  summary: string
  benefits: string[]
  register: string
  login: string
  portal: string
}

type Props = {
  system: { name: string; tagline: string }
  nav: { label: string; href: string }[]
  accountTypes: AccountType[]
  benefits: { icon: string; title: string; text: string }[]
  flow: string[]
  preview: {
    title: string
    stats: { label: string; value: string }[]
    rows: { name: string; state: string; tone: string }[]
  }
  more: { group: string; items: string[] }[]
  contact: { email: string; phone: string }
  legal: { label: string; href: string }[]
  year: number
}

const P: Record<string, string> = {
  building: 'M3 21h18M5 21V5a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v16M13 9h5a1 1 0 0 1 1 1v11M8 8h2M8 12h2M8 16h2',
  briefcase: 'M3 8h18v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8ZM9 8V6a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18',
  spark: 'M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.8 2.8M14.9 14.9l2.8 2.8M17.7 6.3l-2.8 2.8M9.1 14.9l-2.8 2.8',
  megaphone: 'M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1ZM16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12',
  chart: 'M3 3v18h18M8 17v-5M13 17V8M18 17v-9',
  wallet: 'M20 12V8a2 2 0 0 0-2-2H5a2 2 0 0 1 0-4h13M3 6v12a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-4M17 12h4v4h-4a2 2 0 0 1 0-4Z',
  check: 'M20 6 9 17l-5-5',
  shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z',
  close: 'M18 6 6 18M6 6l12 12',
  arrow: 'M19 12H5M12 19l-7-7 7-7',
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

export default function Gateway({
  system,
  nav,
  accountTypes,
  benefits,
  flow,
  preview,
  more,
  contact,
  legal,
  year,
}: Props) {
  const [selected, setSelected] = useState<AccountType>(accountTypes[0])
  const [showMore, setShowMore] = useState(false)

  // اللوحة تُغلق بـEsc: نافذةٌ لا تُغلق إلا بالفأرة تحبس من يتنقّل بالكيبورد
  useEffect(() => {
    if (!showMore) return
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setShowMore(false)
    window.addEventListener('keydown', onKey)

    return () => window.removeEventListener('keydown', onKey)
  }, [showMore])

  // اختيار النوع يقود إلى `/start?type=…` — مسار واحد لا فروع
  const go = (type: AccountType) => router.visit(`/start?type=${type.key}`)

  return (
    <div className="gw" dir="rtl">
      <Head>
        <title>منصّتك لإدارة التسويق مع صنّاع المحتوى</title>
        <meta name="description" content={system.tagline} />
      </Head>

      <header className="gw-header">
        <div className="gw-wrap gw-header-inner">
          <Link href="/" className="gw-brand">
            <span className="gw-mark" aria-hidden="true">
              <Icon name="spark" size={17} />
            </span>
            {system.name}
          </Link>

          <nav className="gw-nav" aria-label="روابط رئيسية">
            {nav.map((item) => (
              <Link key={item.label} href={item.href}>
                {item.label}
              </Link>
            ))}
          </nav>

          <div className="gw-header-actions">
            <Link href="/login" className="gw-btn gw-btn--ghost">
              تسجيل الدخول
            </Link>
            <Link href="/start" className="gw-btn gw-btn--primary">
              ابدأ الآن
            </Link>
          </div>
        </div>
      </header>

      <main className="gw-wrap gw-main">
        <section className="gw-card gw-access" aria-labelledby="gw-title">
          <div>
            <h1 id="gw-title">ابدأ رحلتك في {system.name}</h1>
            <p className="gw-access-lede">اختر صفتك وابدأ فورًا — لكل فئة مساحتها وأدواتها.</p>
          </div>

          <fieldset className="gw-roles">
            <legend className="gw-label">أنا</legend>

            {accountTypes.map((t) => (
              <label key={t.key} className="gw-role">
                <input
                  type="radio"
                  name="account-type"
                  value={t.key}
                  checked={selected.key === t.key}
                  onChange={() => setSelected(t)}
                />
                <span className="gw-role-icon" aria-hidden="true">
                  <Icon name={t.icon} />
                </span>
                <span className="gw-role-text">
                  <b>{t.label}</b>
                  <span>{t.hint}</span>
                </span>
              </label>
            ))}
          </fieldset>

          <div className="gw-actions">
            <button
              type="button"
              className="gw-btn gw-btn--primary gw-btn--lg gw-btn--block"
              onClick={() => go(selected)}
            >
              متابعة كـ{selected.label}
            </button>
            <Link href={selected.login} className="gw-btn gw-btn--ghost gw-btn--block">
              لدي حساب — تسجيل الدخول
            </Link>
          </div>

          <ul className="gw-trust">
            <li>
              <Icon name="check" size={13} /> حسابك محميّ
            </li>
            <li>
              <Icon name="check" size={13} /> تأكيد بالبريد والجوال
            </li>
          </ul>
        </section>

        <section className="gw-showcase">
          <div className="gw-hero">
            <span className="gw-eyebrow">
              <Icon name="shield" size={12} /> كل ما تحتاجه في مكان واحد
            </span>
            <h2>
              أهلًا بك في <em>{system.name}</em>
            </h2>
            <p>
              مكان واحد تُدير فيه تعاوناتك مع صنّاع المحتوى: تتّفق، وتتابع، وتعرف نتيجة كل
              حملة — بخطوات واضحة.
            </p>

            <div className="gw-preview" aria-label="معاينة توضيحية">
              <div className="gw-preview-head">
                <b>{preview.title}</b>
                {/* التعليم صريح: الأرقام توضيحية لا إحصاء حقيقي */}
                <span className="gw-preview-tag">عرض توضيحي</span>
              </div>

              <div className="gw-preview-stats">
                {preview.stats.map((s) => (
                  <div key={s.label}>
                    <b>{s.value}</b>
                    <span>{s.label}</span>
                  </div>
                ))}
              </div>

              <ul className="gw-preview-rows">
                {preview.rows.map((row) => (
                  <li key={row.name}>
                    <span>{row.name}</span>
                    <em data-tone={row.tone}>{row.state}</em>
                  </li>
                ))}
              </ul>
            </div>
          </div>

          <div className="gw-benefits">
            {benefits.map((b) => (
              <article key={b.title} className="gw-card gw-feature">
                <span className="gw-feature-icon" aria-hidden="true">
                  <Icon name={b.icon} />
                </span>
                <b>{b.title}</b>
                <span>{b.text}</span>
              </article>
            ))}

            <button type="button" className="gw-card gw-more-btn" onClick={() => setShowMore(true)}>
              <b>استعراض المميزات</b>
              <span>
                التفاصيل كاملة <Icon name="arrow" size={13} />
              </span>
            </button>
          </div>
        </section>
      </main>

      <section className="gw-flow" aria-label="كيف تبدأ">
        <div className="gw-wrap gw-flow-inner">
          <span className="gw-flow-label">كيف تبدأ</span>
          <ol className="gw-flow-steps">
            {flow.map((step, i) => (
              <li key={step}>
                <span className="gw-step">
                  <i>{String(i + 1).padStart(2, '0')}</i>
                  {step}
                </span>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <footer className="gw-footer">
        <div className="gw-wrap gw-footer-inner">
          <nav className="gw-footer-links" aria-label="روابط">
            {legal.map((l) => (
              <Link key={l.label} href={l.href}>
                {l.label}
              </Link>
            ))}
          </nav>

          <div className="gw-footer-contact">
            <a href={`mailto:${contact.email}`}>{contact.email}</a>
            <a href={`tel:${contact.phone.replace(/\s/g, '')}`} dir="ltr">
              {contact.phone}
            </a>
          </div>

          <span className="gw-copy">
            © {year} {system.name}
          </span>
        </div>
      </footer>

      {showMore && (
        <div className="gw-scrim" onClick={() => setShowMore(false)}>
          <aside
            className="gw-drawer"
            role="dialog"
            aria-modal="true"
            aria-labelledby="gw-more-title"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="gw-drawer-head">
              <h2 id="gw-more-title">استعراض المميزات</h2>
              <button
                type="button"
                className="gw-btn gw-btn--ghost"
                onClick={() => setShowMore(false)}
                aria-label="إغلاق"
              >
                <Icon name="close" />
              </button>
            </div>

            <div className="gw-drawer-body">
              {more.map((g) => (
                <section key={g.group}>
                  <h3>{g.group}</h3>
                  <ul>
                    {g.items.map((item) => (
                      <li key={item}>
                        <Icon name="check" size={14} /> {item}
                      </li>
                    ))}
                  </ul>
                </section>
              ))}
            </div>

            <div className="gw-drawer-foot">
              <Link href="/start" className="gw-btn gw-btn--primary gw-btn--block gw-btn--lg">
                ابدأ الآن
              </Link>
            </div>
          </aside>
        </div>
      )}
    </div>
  )
}
