import { Link, Head, usePage } from '@inertiajs/react'
import type { ReactNode } from 'react'
import type { SharedProps } from '@/types'
import { BrandLogo } from '@/Components/BrandLogo'
/**
 * غلاف الموقع العام — لزائر بلا حساب.
 * منفصل عن أغلفة البوابات: لا شريط جانبي ولا سياق مستأجر، والهدف هنا الفهم لا التشغيل.
 */
export default function PublicLayout({
  title,
  children,
}: {
  title: string
  /** يُقبل من الصفحات للتوافق؛ الوصف الافتراضي يوفّره القالب الجذر (بلا تكرار وسم). */
  description?: string
  children: ReactNode
}) {
  const { brand } = usePage<SharedProps>().props

  return (
    <div className="pub">
      {/* عنوان الصفحة فقط يمرّ عبر مُحوِّل inertia.tsx فيُلحق «— InfluencerHub».
          الرابط القانوني (نطاق المنتج + المسار) والوصف الافتراضي يضبطهما القالب
          الجذر مرّةً في HTML الأوّليّ — عنصر واحد لكلٍّ بلا تكرار. */}
      <Head title={title} />

      <header className="pub-header">
        <div className="pub-wrap pub-header-inner">
          <Link href="/" className="pub-logo" aria-label="InfluencerHub — الرئيسية">
            <BrandLogo height={30} surface="auto" />
          </Link>
          <nav className="pub-nav">
            <Link href="/features">المزايا</Link>
            <Link href="/solutions/clients">للعملاء</Link>
            <Link href="/solutions/agencies">للوكالات</Link>
            <Link href="/solutions/creators">لصنّاع المحتوى</Link>
            <Link href="/pricing">الأسعار</Link>
          </nav>
          <div className="pub-header-cta">
            <a href="/login" className="btn btn-sm btn-outline">تسجيل الدخول</a>
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
              <div className="pub-logo"><BrandLogo height={28} surface="auto" /></div>
              <p className="pub-muted">منصّة إدارة حملات المؤثرين وصنّاع المحتوى.</p>
              <a href="https://influencerhub.io/" style={{ direction: 'ltr', display: 'inline-block', marginTop: '.35rem', fontWeight: 700 }}>influencerhub.io</a>
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
              <a href="/login">تسجيل الدخول</a>
              <Link href="/join/creator">الانضمام كصانع محتوى</Link>
              <Link href="/demo">اطلب عرضًا توضيحيًا</Link>
            </div>
            <div>
              <h4>الدعم</h4>
              <Link href="/info">عن InfluencerHub</Link>
              <Link href="/help">المساعدة</Link>
              <Link href="/terms">الشروط</Link>
              <Link href="/privacy">الخصوصية</Link>
            </div>
            <div>
              <h4>تواصل</h4>
              <a href={`mailto:${brand.publicEmail}`} style={{ direction: 'ltr' }}>{brand.publicEmail}</a>
              <a href={`tel:${brand.publicPhone}`} style={{ direction: 'ltr' }}>{brand.publicPhoneDisplay}</a>
            </div>
          </div>
          {/* الروابط النظامية في السطر الأخير أيضًا: هذا أوّل ما يُبحث عنه في التذييل */}
          <div className="pub-footer-legal">
            © {new Date().getFullYear()} <span style={{ direction: 'ltr' }}>InfluencerHub</span> · <a href="https://influencerhub.io/" style={{ direction: 'ltr' }}>influencerhub.io</a> · <Link href="/terms">الشروط</Link> ·{' '}
            <Link href="/privacy">الخصوصية</Link>
          </div>
        </div>
      </footer>
    </div>
  )
}
