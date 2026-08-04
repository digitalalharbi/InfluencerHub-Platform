import { Link, Head } from '@inertiajs/react'
import type { ReactNode } from 'react'

function BrandMark({ size = 26 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect x="1.25" y="1.25" width="29.5" height="29.5" rx="8.5" stroke="var(--ih-primary)" strokeWidth="1.6" opacity=".28" />
      <path d="M9 22V10" stroke="var(--ih-primary)" strokeWidth="2.4" strokeLinecap="round" />
      <path d="M23 10v12" stroke="var(--ih-primary)" strokeWidth="2.4" strokeLinecap="round" />
      <path d="M9 16h14" stroke="var(--ih-primary)" strokeWidth="2.4" strokeLinecap="round" opacity=".55" />
      <circle cx="16" cy="16" r="3.4" fill="var(--ih-primary)" />
      <circle cx="9" cy="10" r="2" fill="var(--ih-primary)" />
      <circle cx="23" cy="22" r="2" fill="var(--ih-primary)" />
    </svg>
  )
}
/**
 * غلاف الموقع العام — لزائر بلا حساب.
 * منفصل عن أغلفة البوابات: لا شريط جانبي ولا سياق مستأجر، والهدف هنا الفهم لا التشغيل.
 */
export default function PublicLayout({
  title,
  description,
  children,
}: {
  title: string
  description?: string
  children: ReactNode
}) {

  return (
    <div className="pub">
      <Head>
        <title>{title}</title>
        {description && <meta name="description" content={description} />}
        <meta property="og:title" content={title} />
        {description && <meta property="og:description" content={description} />}
        <meta property="og:type" content="website" />
      </Head>

      <header className="pub-header">
        <div className="pub-wrap pub-header-inner">
          <Link href="/" className="pub-logo">
            <BrandMark />
            <span>إنفلونسر هَب</span>
          </Link>
          <nav className="pub-nav">
            <Link href="/features">المزايا</Link>
            <Link href="/solutions/clients">للعملاء</Link>
            <Link href="/solutions/agencies">للوكالات</Link>
            <Link href="/solutions/creators">لصنّاع المحتوى</Link>
            <Link href="/pricing">الأسعار</Link>
          </nav>
          <div className="pub-header-cta">
            <Link href="/login" className="btn btn-sm btn-outline">تسجيل الدخول</Link>
            <Link href="/register" className="btn btn-sm btn-primary">
              إنشاء حساب
            </Link>
          </div>
        </div>
      </header>

      <main>{children}</main>


      <footer className="pub-footer">
        <div className="pub-wrap">
          <div className="pub-footer-cols">
            <div>
              <div className="pub-logo"><BrandMark /><span>إنفلونسر هَب</span></div>
              <p className="pub-muted">منصّة إدارة التسويق عبر صنّاع المحتوى.</p>
            </div>
            <div>
              <h4>المنتَج</h4>
              <Link href="/features">المزايا</Link>
              <Link href="/solutions/clients">للعملاء</Link>
              <Link href="/solutions/agencies">للوكالات</Link>
              <Link href="/solutions/creators">لصنّاع المحتوى</Link>
              <Link href="/pricing">الأسعار</Link>
            </div>
            <div>
              <h4>ابدأ</h4>
              <Link href="/register">إنشاء حساب</Link>
              <Link href="/login">تسجيل الدخول</Link>
              <Link href="/join/creator">الانضمام كصانع محتوى</Link>
              <Link href="/demo">اطلب عرضًا توضيحيًا</Link>
            </div>
            <div>
              <h4>الدعم</h4>
              <Link href="/help">المساعدة</Link>
              <Link href="/terms">الشروط</Link>
              <Link href="/privacy">الخصوصية</Link>
            </div>
          </div>
          {/* الروابط النظامية في السطر الأخير أيضًا: هذا أوّل ما يُبحث عنه في التذييل */}
          <div className="pub-footer-legal">
            © {new Date().getFullYear()} إنفلونسر هَب · <Link href="/terms">الشروط</Link> ·{' '}
            <Link href="/privacy">الخصوصية</Link>
          </div>
        </div>
      </footer>
    </div>
  )
}
