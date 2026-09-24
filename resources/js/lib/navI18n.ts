/**
 * ترجمة تسميات التنقّل (React) — خريطة عربي→إنجليزي مفتاحها النصّ العربي نفسه.
 *
 * لماذا خريطة بالنصّ لا بالمفتاح: مفاتيح عناصر القوائم تتصادم عبر البوّابات بمعانٍ
 * مختلفة (مثلاً key 'dashboard' = «لوحة التحكم» في الوكالة و«نظرة عامة» في العلامة).
 * المفتاح النصّي فريد بالمعنى، فلا التباس. العربية هي الهُويّة (لا تتغيّر)، والإنجليزية
 * تأتي من هذه الخريطة؛ أي نصّ غير مُترجَم يعود كما هو (لا مفاتيح خام).
 */
const NAV_EN: Record<string, string> = {
  // مساحات ومجموعات
  'لوحة التحكم': 'Dashboard',
  'نظرة عامة': 'Overview',
  'مركز التحكّم': 'Control center',
  'عملي': 'My work',
  'العمل': 'Work',
  'المؤثرون': 'Creators',
  'التنفيذ': 'Execution',
  'العملاء والعلامات': 'Clients & brands',
  'العلاقات': 'Relationships',
  'التشغيل': 'Operations',
  'المالية': 'Finance',
  'التقارير': 'Reports',
  'الإدارة': 'Administration',
  'المزيد': 'More',
  'الحساب': 'Account',
  'المساحة': 'Workspace',
  'التجاري': 'Commercial',
  'الحسابات': 'Accounts',
  'الإشراف': 'Oversight',
  // عناصر
  'الطلبات': 'Requests',
  'الحملات': 'Campaigns',
  'صناع المحتوى': 'Content creators',
  'صنّاع المحتوى': 'Content creators',
  'قاعدة المؤثرين': 'Creator database',
  'قاعدة المبدعين': 'Creator pool',
  'الترشيحات': 'Nominations',
  'ترشيح المؤثرين': 'Influencer nominations',
  'ترشيحات المؤثرين': 'Influencer nominations',
  'الناشرون': 'Publishers',
  'طلبات الانضمام': 'Join requests',
  'التعاونات': 'Collaborations',
  'المحتوى': 'Content',
  'العقود': 'Contracts',
  'العملاء': 'Clients',
  'العلامات': 'Brands',
  'الوكالات الشريكة': 'Partner agencies',
  'الوكالات': 'Agencies',
  'مراجعة العلامات': 'Brand reviews',
  'مراجعات العملاء': 'Client reviews',
  'الفواتير': 'Invoices',
  'المستحقات': 'Payouts',
  'مركز التصدير': 'Export center',
  'الأتمتة': 'Automation',
  'التكاملات': 'Integrations',
  'الفريق': 'Team',
  'الإعدادات': 'Settings',
  'صحّة النظام': 'System health',
  'حسابي': 'My account',
  'مركز المعاينة': 'Preview center',
  'المستندات': 'Documents',
  'الإشعارات': 'Notifications',
  'المستأجرون': 'Tenants',
  'الاشتراكات': 'Subscriptions',
  'سجل التدقيق': 'Audit log',
  'الخطط': 'Plans',
  'طلبات فتح الحساب': 'Signup requests',
  // بوّابة المبدع
  'ملفي': 'My profile',
  'تعاوناتي': 'My collaborations',
  'محتواي': 'My content',
  'مستحقاتي': 'My payouts',
  // بوّابة العميل
  'ملف العميل': 'Client profile',
  'البريفات': 'Briefs',
  // عناصر قِشرة التطبيق (قائمة الحساب/الطيّ/الجوال)
  'تسجيل الخروج': 'Sign out',
  'طيّ القائمة': 'Collapse menu',
  'الملف الشخصي': 'Profile',
  'تغيير كلمة المرور': 'Change password',
  'الجلسات والإشعارات': 'Sessions & notifications',
  'اللغة': 'Language',
};

/** يُعيد تسمية التنقّل باللغة المطلوبة؛ العربية هُويّة، والإنجليزية من الخريطة (أو النصّ كما هو). */
export function navLabel(arabic: string, locale: string): string {
  if (locale !== 'en') return arabic;
  return NAV_EN[arabic] ?? arabic;
}
