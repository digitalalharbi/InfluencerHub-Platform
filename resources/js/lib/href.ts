/**
 * بناء الروابط من بادئة التركيب الفعلية للصفحة بدل قيمة ثابتة.
 *
 * أثناء التحويل التدريجي من Blade إلى React تُقدَّم الصفحة نفسها تحت `/beta`
 * أو `/app` (وللبوابات `/beta/client` أو `/client`…). الخادم يشارك البادئة
 * الحقيقية في `base`، فتظل روابط الصفحة داخل المجموعة التي فُتحت منها.
 *
 * دالة عادية لا Hook — تُستعمل داخل المكوّنات وخارجها (router.get مثلًا).
 */
let BASE = '/app';
let PV: string | null = null;

/** يُستدعى من inertia.tsx عند الإقلاع وعند كل تنقّل. */
export function setBase(base?: unknown): void {
  if (typeof base === 'string' && base.startsWith('/')) BASE = base;
}

/**
 * رمز معاينة مالك المنصّة (§P3). أثناء معاينة نشطة يُمرَّر في كل رابط داخلي كي
 * يظل التنقّل داخل المعاينة — والحالة في الـURL لا الجلسة (متعدّد النوافذ آمن).
 * يُستدعى من inertia.tsx من props.preview.token (null خارج المعاينة).
 */
export function setPreviewToken(token?: unknown): void {
  PV = typeof token === 'string' && token !== '' ? token : null;
}

export function base(): string {
  return BASE;
}

/** رمز المعاينة الحاليّ (أو null) — يستعمله معترِض inertia لتغطية كل الطلبات لا u() فقط. */
export function previewToken(): string | null {
  return PV;
}

/** `u('/content')` → `/app/content` أو `/beta/content` حسب مكان التقديم. */
export function u(path: string): string {
  const full = BASE + path;
  if (PV === null) return full;
  return full + (full.includes('?') ? '&' : '?') + '_pv=' + encodeURIComponent(PV);
}
