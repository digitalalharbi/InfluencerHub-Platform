import { Link } from '@inertiajs/react'

type LoginPortal = {
  label: string
  href: string
}

type Props = {
  open: boolean
  action?: string
  portal?: string
  onClose: () => void
}

const portals: LoginPortal[] = [
  { label: 'العميل', href: '/client/login' },
  { label: 'المؤثر · صانع المحتوى', href: '/creator/login' },
  { label: 'الوكالة الشريكة', href: '/partner/login' },
  { label: 'طلب انضمام', href: '/join/creator' },
]

function Icon({ name, size = 18 }: { name: 'close' | 'spark' | 'grid' | 'bolt' | 'chart' | 'file'; size?: number }) {
  const paths = {
    close: 'M18 6 6 18M6 6l12 12',
    spark: 'M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.8 2.8M14.9 14.9l2.8 2.8M17.7 6.3l-2.8 2.8M9.1 14.9l-2.8 2.8',
    grid: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
    bolt: 'M13 2 4 14h7l-1 8 10-13h-7z',
    chart: 'M3 3v18h18M8 17v-5M13 17V8M18 17v-9',
    file: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6',
  }

  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={paths[name]} />
    </svg>
  )
}

export default function PublicLoginModal({ open, action = '/login', portal = 'بوابة الوكالة', onClose }: Props) {
  if (!open) return null

  const csrf = typeof document === 'undefined' ? '' : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

  return (
    <div className="ih-login-backdrop" onMouseDown={onClose}>
      <section className="ih-login-modal" role="dialog" aria-modal="true" aria-labelledby="ih-login-title" onMouseDown={(e) => e.stopPropagation()}>
        <button type="button" className="ih-login-close" onClick={onClose} aria-label="إغلاق">
          <Icon name="close" size={16} />
        </button>

        <aside className="ih-login-formpane">
          <Link href="/" className="ih-login-logo">
            <span>إنفلونسر هَب</span>
            <i aria-hidden="true"><Icon name="spark" size={15} /></i>
          </Link>

          <span className="ih-login-tag">{portal}</span>
          <h2 id="ih-login-title">تسجيل الدخول</h2>
          <p>ادخل إلى مساحة عملك.</p>

          <form method="POST" action={action} className="ih-login-form">
            <input type="hidden" name="_token" value={csrf} />
            <label>
              <span>البريد الإلكتروني</span>
              <input type="email" name="email" required autoComplete="username" inputMode="email" autoFocus />
            </label>
            <label>
              <span>كلمة المرور</span>
              <input type="password" name="password" required autoComplete="current-password" />
            </label>
            <label className="ih-login-remember">
              <input type="checkbox" name="remember" />
              <span>تذكرني</span>
            </label>
            <button type="submit">دخول إلى الوكالة</button>
          </form>

          <div className="ih-login-switch">
            <span>بوابة أخرى؟</span>
            <div>
              {portals.map((item) => (
                <a key={item.href} href={item.href}>
                  {item.label}
                </a>
              ))}
            </div>
          </div>
        </aside>

        <aside className="ih-login-brandpane">
          <h3>أدر عمليات المؤثرين والعملاء من منصة واحدة</h3>
          <p>منظومة تشغيل متكاملة لإدارة الطلبات والحملات والمحتوى والعقود والمدفوعات والتقارير والتكاملات في بيئة موحدة.</p>

          <div className="ih-login-features">
            <article>
              <Icon name="grid" />
              <b>إدارة موحدة</b>
              <span>العملاء والعلامات والمؤثرون والطلبات في بيئة واحدة.</span>
            </article>
            <article>
              <Icon name="bolt" />
              <b>أتمتة وتشغيل</b>
              <span>تنبيهات وسير عمل يقللان المتابعة اليدوية.</span>
            </article>
            <article>
              <Icon name="chart" />
              <b>تحليلات وتقارير</b>
              <span>لوحات أداء لحظية تقيس نتائج الحملات والتعاونات.</span>
            </article>
            <article>
              <Icon name="file" />
              <b>عقود ومدفوعات</b>
              <span>إدارة احترافية للعقود والفواتير والمستحقات.</span>
            </article>
          </div>
        </aside>
      </section>
    </div>
  )
}
