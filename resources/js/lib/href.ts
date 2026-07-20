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

/** يُستدعى من inertia.tsx عند الإقلاع وعند كل تنقّل. */
export function setBase(base?: unknown): void {
  if (typeof base === 'string' && base.startsWith('/')) BASE = base;
}

export function base(): string {
  return BASE;
}

/** `u('/content')` → `/app/content` أو `/beta/content` حسب مكان التقديم. */
export function u(path: string): string {
  return BASE + path;
}
