import type { IconName } from '@/Components/Icon';

export interface NavItem {
  key: string;
  label: string;
  route: string;
  icon: IconName;
  badge?: string; // key into shared nav.badges
  match?: string; // path prefix for active state (defaults to route)
  can?: string;   // key into shared nav.can — item hidden unless true (undefined = always shown)
  abs?: boolean;  // مسار مطلق (ما زال على Blade) — لا تُضاف إليه بادئة التركيب
}

export interface NavGroup {
  key: string;
  label?: string;
  items: NavItem[];
}

/**
 * قائمة الوكالة (React) — التسميات والمجموعات مصدرها الوحيد `docs/PRODUCT-TERMINOLOGY.md`.
 * قاعدة: اسم واحد لكل كيان، كلمة–كلمتان، ولا يساوي اسمُ المجموعة اسمَ عنصر بداخلها.
 * تُصفّى بالدور/الصلاحية عبر `nav.can` المشتركة؛ المجموعة الفارغة تُخفى في AppShell.
 * لا يُعرض عنصر لوحدة غير مبنية. ما لم يُهاجَر بعد يشير إلى Blade `/app` (لا حذف لأي وحدة).
 */
export const agencyNav: NavGroup[] = [
  {
    key: 'work',
    label: 'العمل',
    items: [
      { key: 'dashboard', label: 'لوحة التحكم', route: '', icon: 'layout-dashboard', match: '' },
      { key: 'my_tasks', label: 'مهامي', route: '/my-tasks', icon: 'list-checks' },
      { key: 'requests', label: 'الطلبات', route: '/service-requests', icon: 'inbox', badge: 'service_requests' },
    ],
  },
  {
    key: 'relationships',
    label: 'العلاقات',
    items: [
      { key: 'clients', label: 'العملاء', route: '/clients', icon: 'building-2' },
      { key: 'brands', label: 'العلامات', route: '/brands', icon: 'bookmark' },
      // وجهة واحدة لصناع المحتوى. كانت وجهتين («المؤثرون» و«صناع المحتوى»)
      // تفتحان الصفحة نفسها بفلتر مختلف، فبدتا وحدتين منفصلتين والتصفية
      // بالقدرة موجودة داخل الصفحة أصلًا. الاسم الموحّد: صناع المحتوى.
      { key: 'creators', label: 'صناع المحتوى', route: '/creators', icon: 'users' },
      { key: 'publishers', label: 'الناشرون', route: '/publishers', icon: 'radar' },
      { key: 'applications', label: 'طلبات الانضمام', route: '/creator-applications', icon: 'user-plus', badge: 'creator_applications', can: 'reviews' },
    ],
  },
  {
    key: 'execution',
    label: 'التنفيذ',
    items: [
      { key: 'campaigns', label: 'الحملات', route: '/campaigns', icon: 'megaphone' },
      { key: 'shortlisting', label: 'الترشيحات', route: '/shortlisting', icon: 'list-checks' },
      { key: 'collaborations', label: 'التعاونات', route: '/collaborations', icon: 'git-merge' },
      { key: 'content', label: 'المحتوى', route: '/content', icon: 'image', badge: 'content' },
      { key: 'contracts', label: 'العقود', route: '/contracts', icon: 'file-text' },
    ],
  },
  {
    key: 'finance',
    label: 'المالية',
    items: [
      { key: 'invoices', label: 'الفواتير', route: '/invoices', icon: 'file-text' },
      { key: 'payouts', label: 'المستحقات', route: '/payouts', icon: 'wallet' },
    ],
  },
  {
    key: 'intelligence',
    label: 'الذكاء',
    items: [
      { key: 'reports', label: 'التقارير', route: '/reports', icon: 'bar-chart-3' },
      { key: 'integrations', label: 'التكاملات', route: '/integrations', icon: 'plug' },
    ],
  },
  {
    key: 'admin',
    label: 'الإدارة',
    items: [
      { key: 'brand_reviews', label: 'مراجعة العلامات', route: '/brands?seg=needs_review', match: '/brands', icon: 'shield-check', badge: 'brand_reviews', can: 'reviews' },
      { key: 'client_reviews', label: 'مراجعات العملاء', route: '/client-reviews', icon: 'clipboard-check', badge: 'client_reviews', can: 'reviews' },
      { key: 'partners', label: 'الوكالات الشريكة', route: '/partner-agencies', icon: 'handshake', can: 'admin' },
      { key: 'team', label: 'الفريق', route: '/team', icon: 'users', can: 'admin' },
      { key: 'settings', label: 'الإعدادات', route: '/settings', icon: 'settings', can: 'admin' },
      { key: 'account', label: 'حسابي', route: '/account', icon: 'users' },
    ],
  },
];

/**
 * تنقّل الجوال — أهم 4 وجهات لكل بوابة + «المزيد» يفتح القائمة الكاملة.
 * ليست القائمة الجانبية مضغوطة؛ تجربة جوال مستقلة.
 * المفاتيح تشير إلى عناصر موجودة في القائمة نفسها (تُصفّى بالصلاحية عند العرض).
 */
export const mobilePrimary: Record<string, string[]> = {
  agency: ['dashboard', 'my_tasks', 'clients', 'campaigns'],
  client: ['dashboard', 'campaigns', 'approvals', 'contracts'],
  creator: ['dashboard', 'collaborations', 'content', 'payouts'],
  partner: ['dashboard', 'clients', 'requests'],
  admin: ['dashboard', 'tenants', 'subscriptions', 'audit'],
  brand: ['dashboard', 'campaigns', 'content', 'reports'],
};

/**
 * قائمة بوابة العميل (React) — دور العميل يرى حملاته/موافقاته/عقوده فقط.
 * المسارات تحت `/beta/client` (بالتوازي مع Blade `/client`).
 */
export const clientNav: NavGroup[] = [
  {
    key: 'overview',
    items: [{ key: 'dashboard', label: 'لوحة التحكم', route: '', icon: 'layout-dashboard', match: '' }],
  },
  {
    key: 'work',
    label: 'العمل',
    items: [
      { key: 'requests', label: 'الطلبات', route: '/requests', icon: 'inbox' },
      { key: 'campaigns', label: 'الحملات', route: '/campaigns', icon: 'megaphone' },
      { key: 'approvals', label: 'المحتوى', route: '/content', icon: 'image', badge: 'client_approvals' },
      { key: 'contracts', label: 'العقود', route: '/contracts', icon: 'file-text' },
    ],
  },
  {
    key: 'account',
    label: 'الحساب',
    items: [
      { key: 'brands', label: 'العلامات', route: '/brands', icon: 'bookmark' },
      { key: 'team', label: 'الفريق', route: '/team', icon: 'users' },
      { key: 'documents', label: 'المستندات', route: '/documents', icon: 'file-text' },
    ],
  },
];

/**
 * قائمة بوابة المبدع (React) — يرى تعاوناته/محتواه/عقوده/مستحقاته فقط.
 * المسارات تحت `/beta/creator` (بالتوازي مع Blade `/creator`).
 */
export const creatorNav: NavGroup[] = [
  {
    key: 'overview',
    items: [{ key: 'dashboard', label: 'لوحة التحكم', route: '', icon: 'layout-dashboard', match: '' }],
  },
  {
    key: 'work',
    label: 'العمل',
    items: [
      { key: 'collaborations', label: 'التعاونات', route: '/collaborations', icon: 'git-merge' },
      { key: 'content', label: 'المحتوى', route: '/content', icon: 'image' },
      { key: 'contracts', label: 'العقود', route: '/contracts', icon: 'file-text' },
    ],
  },
  {
    key: 'finance',
    label: 'المالية',
    items: [
      { key: 'invoices', label: 'الفواتير', route: '/invoices', icon: 'file-text' },
      { key: 'payouts', label: 'المستحقات', route: '/payouts', icon: 'wallet' },
      { key: 'account', label: 'حسابي', route: '/account', icon: 'users' },
    ],
  },
];

/**
 * قائمة بوابة الشريك (React) — الوكالة الشريكة ترى ما رُبطت به بنطاقاتها فقط.
 * المسارات تحت `/beta/partner` (بالتوازي مع Blade `/partner`).
 */
export const partnerNav: NavGroup[] = [
  {
    key: 'overview',
    items: [{ key: 'dashboard', label: 'لوحة التحكم', route: '', icon: 'layout-dashboard', match: '' }],
  },
  {
    key: 'work',
    label: 'العمل',
    items: [
      { key: 'clients', label: 'العملاء', route: '', icon: 'building-2', match: '' },
      { key: 'requests', label: 'الطلبات', route: '/requests', icon: 'inbox' },
    ],
  },
];

/**
 * قائمة مدير النظام (SaaS) — إشراف عبر المنصّة، للقراءة فقط.
 * المسارات تحت `/beta/admin` (محميّة بـ is_system_admin).
 */

/**
 * قائمة مساحة العلامة (React).
 *
 * كل عنصر هنا له **مسار يعمل وصفحة فعلية** — لا عنصر يشير إلى فراغ. وترتيبها
 * يتبع سلسلة الاشتقاق التشغيلية: طلب ← حملة ← ترشيح ← محتوى ← عقد ← فاتورة.
 */
export const brandNav: NavGroup[] = [
  {
    key: 'overview',
    items: [{ key: 'dashboard', label: 'نظرة عامة', route: '', icon: 'layout-dashboard', match: '' }],
  },
  {
    key: 'work',
    label: 'العمل',
    items: [
      { key: 'requests', label: 'الطلبات', route: '/requests', icon: 'inbox' },
      { key: 'campaigns', label: 'الحملات', route: '/campaigns', icon: 'megaphone' },
      { key: 'shortlists', label: 'الترشيحات', route: '/shortlists', icon: 'users' },
      { key: 'content', label: 'المحتوى', route: '/content', icon: 'image' },
    ],
  },
  {
    key: 'commercial',
    label: 'التجاري',
    items: [
      { key: 'contracts', label: 'العقود', route: '/contracts', icon: 'file-text' },
      { key: 'invoices', label: 'الفواتير', route: '/invoices', icon: 'receipt' },
      { key: 'payouts', label: 'المدفوعات', route: '/payouts', icon: 'wallet' },
      { key: 'reports', label: 'التقارير', route: '/reports', icon: 'bar-chart-3' },
    ],
  },
  {
    key: 'account',
    label: 'المساحة',
    items: [
      { key: 'agencies', label: 'الوكالات', route: '/agencies', icon: 'handshake' },
      { key: 'team', label: 'الفريق', route: '/team', icon: 'users' },
      { key: 'notifications', label: 'الإشعارات', route: '/notifications', icon: 'activity' },
      { key: 'settings', label: 'الإعدادات', route: '/settings', icon: 'settings' },
    ],
  },
];

export const adminNav: NavGroup[] = [
  {
    key: 'overview',
    items: [{ key: 'dashboard', label: 'لوحة التحكم', route: '', icon: 'layout-dashboard', match: '' }],
  },
  {
    key: 'platform',
    label: 'الحسابات',
    items: [
      { key: 'tenants', label: 'المستأجرون', route: '/tenants', icon: 'building-2' },
      { key: 'signup-requests', label: 'طلبات فتح الحساب', route: '/signup-requests', icon: 'inbox' },
      { key: 'plans', label: 'الخطط', route: '/plans', icon: 'shield-check' },
      { key: 'subscriptions', label: 'الاشتراكات', route: '/subscriptions', icon: 'wallet' },
    ],
  },
  {
    key: 'oversight',
    label: 'الإشراف',
    items: [{ key: 'audit', label: 'سجل التدقيق', route: '/audit', icon: 'file-text' }],
  },
];
