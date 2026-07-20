import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { Icon } from '@/Components/Icon';
import { agencyNav, mobilePrimary, type NavGroup, type NavItem } from '@/lib/nav';
import { base, u } from '@/lib/href';
import type { SharedProps } from '@/types';

function isActive(url: string, route: string, match?: string, home = base()): boolean {
  const [path, query = ''] = url.split('?');
  const [target, targetQuery = ''] = (match ?? route).split('?');
  if (target === home) return path === home;
  if (path !== target && !path.startsWith(target + '/')) return false;
  // التمييز حسب معامل النوع (المؤثرون/صنّاع المحتوى/كل المبدعين يتشاركون نفس المسار)
  const urlType = new URLSearchParams(query).get('type');
  const targetType = new URLSearchParams(targetQuery).get('type');
  if (targetType) return urlType === targetType;
  // عنصر بلا نوع (مثل "كل المبدعين") يَنشط فقط عندما لا يوجد نوع في الرابط
  return !urlType || target !== u('/creators');
}

export default function AppShell({
  heading, children, nav: navGroups = agencyNav, home = base(), wsName, wsPlan = 'وكالة · الخطة النشطة', brand = 'إنفلونسر هَب',
  portal = 'agency',
}: {
  heading?: string; children: ReactNode; nav?: NavGroup[]; home?: string;
  wsName?: string; wsPlan?: string; brand?: string;
  portal?: 'agency' | 'client' | 'creator' | 'partner' | 'admin' | 'brand';
}) {
  const page = usePage<SharedProps>();
  const { auth, workspace, showcase, nav, flash } = page.props;
  const url = page.url;
  const [open, setOpen] = useState(false);
  // طيّ الشريط — تفضيل واجهة غير حسّاس، يُحفظ محليًا فقط
  const [rail, setRail] = useState(false);
  useEffect(() => { setRail(localStorage.getItem('ih.rail') === '1'); }, []);
  const toggleRail = () => setRail((v) => { localStorage.setItem('ih.rail', v ? '0' : '1'); return !v; });
  const badges = nav?.badges ?? {};
  const can = nav?.can ?? {};
  const wsLabel = wsName ?? workspace ?? 'مساحة العمل';

  // تصفية بالصلاحية (عنصر ذو `can` يظهر فقط إن كانت القدرة صحيحة) وإخفاء المجموعات الفارغة.
  const visibleGroups = navGroups
    .map((g) => ({ ...g, items: g.items.filter((it) => !it.can || can[it.can]) }))
    .filter((g) => g.items.length > 0);

  // تنقّل الجوال: أهم الوجهات المتاحة فعلًا لهذا المستخدم + «المزيد»
  const allItems: NavItem[] = visibleGroups.flatMap((g) => g.items);
  const bottomItems = (mobilePrimary[portal] ?? [])
    .map((k) => allItems.find((i) => i.key === k))
    .filter((i): i is NavItem => Boolean(i));

  return (
    <div className={`ih-shell has-bottom-nav${open ? ' nav-open' : ''}${rail ? ' rail-collapsed' : ''}`}>
      <div className="ih-scrim" onClick={() => setOpen(false)} />

      <aside className="sidebar ih-side" onClick={() => setOpen(false)}>
        <Link href={home} className="ih-side__brand" title={rail ? brand : undefined}>
          <span className="ih-side__mark">◆</span> <span className="ih-side__brand-text">{brand}</span>
        </Link>
        <div className="ih-side__workspace">
          <span className="ih-side__ws-avatar">{(wsLabel ?? 'و').slice(0, 1)}</span>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div className="ih-side__ws-name">{wsLabel}</div>
            <div className="ih-side__ws-plan">{wsPlan}</div>
          </div>
        </div>

        <nav className="ih-side__scroll ih-nav" style={{ display: 'flex', flexDirection: 'column', gap: '.1rem' }}>
          {visibleGroups.map((group) => (
            <div key={group.key}>
              {group.label && <div className="ih-nav__group">{group.label}</div>}
              {group.items.map((item) => {
                const href = item.abs ? item.route : u(item.route);
                const active = isActive(url, href, item.match === undefined ? undefined : u(item.match), home);
                const count = item.badge ? badges[item.badge] ?? 0 : 0;
                return (
                  <Link
                    key={item.key}
                    href={href}
                    className={`nav-link ih-nav__link${active ? ' active' : ''}`}
                    style={{ display: 'flex', alignItems: 'center', gap: '.6rem', justifyContent: 'space-between' }}
                    aria-current={active ? 'page' : undefined}
                    data-label={item.label}
                    title={rail ? item.label : undefined}
                  >
                    <span style={{ display: 'flex', alignItems: 'center', gap: '.6rem', minWidth: 0 }}>
                      <Icon name={item.icon} size={18} className="ih-icon" />
                      <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.label}</span>
                    </span>
                    {count > 0 && <span className="ih-nav__badge">{count > 99 ? '99+' : count}</span>}
                  </Link>
                );
              })}
            </div>
          ))}
        </nav>

        <div className="ih-side__foot">
          <Link href="/logout" method="post" as="button" className="nav-link ih-nav__link" data-label="تسجيل الخروج"
            style={{ width: '100%', border: 0, background: 'none', cursor: 'pointer', textAlign: 'start', fontSize: '.84rem' }}>
            <Icon name="log-out" size={18} /> <span>تسجيل الخروج</span>
          </Link>
          <button type="button" onClick={(e) => { e.stopPropagation(); toggleRail(); }} className="ih-side__collapse"
            aria-label={rail ? 'توسيع القائمة' : 'طيّ القائمة'} title={rail ? 'توسيع' : 'طيّ'}>
            <Icon name="chevron-left" size={16} style={{ transform: rail ? 'scaleX(-1)' : undefined }} />
            <span>طيّ القائمة</span>
          </button>
        </div>
      </aside>

      <main style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <div className="ih-topbar-mobile">
          <button className="ih-icon-btn" onClick={() => setOpen(true)} aria-label="فتح القائمة"><Icon name="menu" size={22} /></button>
          <span style={{ fontWeight: 800, color: 'var(--ih-primary)' }}>◆ {brand}</span>
          <span style={{ marginInlineStart: 'auto', fontWeight: 700, fontSize: '.9rem' }}>{heading}</span>
        </div>

        <header className="ih-topbar ih-only-desktop">
          <div className="ih-topbar__title">{heading ?? 'لوحة التحكم'}</div>
          <div className="ih-topbar__spacer" />
          {showcase && <span className="ih-showcase-badge" title="بيئة عرض تجريبية">● بيانات تجريبية</span>}
          <span className="ih-tag" style={{ fontSize: '.68rem' }}>React · معاينة</span>
          <div className="ih-topbar__user">
            <span className="ih-topbar__user-name ih-only-desktop">{auth.user?.name}</span>
            <span className="ih-topbar__avatar">{(auth.user?.name ?? '؟').slice(0, 1)}</span>
          </div>
        </header>

        <div className="ih-content">
          {flash?.ok && (
            <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{flash.ok}</div>
          )}
          {children}
        </div>
      </main>

      {/* تنقّل الجوال — وجهات أساسية + «المزيد» يفتح القائمة الكاملة */}
      {bottomItems.length > 0 && (
        <nav className="ih-bottom-nav" aria-label="التنقّل السريع">
          {bottomItems.map((item) => {
            const href = item.abs ? item.route : u(item.route);
                const active = isActive(url, href, item.match === undefined ? undefined : u(item.match), home);
            const count = item.badge ? badges[item.badge] ?? 0 : 0;
            return (
              <Link key={item.key} href={href} className={`ih-bottom-nav__link${active ? ' active' : ''}`} aria-current={active ? 'page' : undefined}>
                <span className="ih-bottom-nav__icon">
                  <Icon name={item.icon} size={21} />
                  {count > 0 && <span className="ih-bottom-nav__dot" />}
                </span>
                <span className="ih-bottom-nav__label">{item.label}</span>
              </Link>
            );
          })}
          <button type="button" onClick={() => setOpen(true)} className="ih-bottom-nav__link" aria-label="عرض كل الأقسام"
            style={{ border: 0, background: 'none', cursor: 'pointer', font: 'inherit' }}>
            <span className="ih-bottom-nav__icon"><Icon name="menu" size={21} /></span>
            <span className="ih-bottom-nav__label">المزيد</span>
          </button>
        </nav>
      )}
    </div>
  );
}
