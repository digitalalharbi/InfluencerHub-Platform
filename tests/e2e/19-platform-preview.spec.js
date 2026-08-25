import { test, expect } from '@playwright/test';

/**
 * معاينة مالك المنصّة (§P3) — رحلة كاملة بالواجهة الحقيقية (لا محاكاة روابط):
 * دخول المالك → /platform → اختيار مستأجر → اختيار سياق عميل دقيق → «معاينة» من الواجهة →
 * البوّابة الحقيقية تُفتح → الشريط ظاهر + هوية الهدف → تنقّل عبر صفحات حقيقية والمعاينة
 * تبقى نشطة → محاولة تحوّر بزرّ حقيقيّ → الخادم يحظره (403) → جلسة المالك سليمة → خروج → /platform.
 */
const PW = process.env.E2E_PASSWORD;

async function ownerLogin(page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'owner@platform.test');
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

async function openTenant(page) {
  await page.goto('/platform/tenants');
  await expect(page.locator('body')).toContainText('المستأجرون');
  await page.click('a:has-text("وكالة ألف")');
  await page.waitForURL('**/platform/tenants/**');
  await expect(page.locator('body')).toContainText('معاينة البوّابات');
}

/** يتنقّل عبر رابط الشريط الجانبي (Inertia — يحمل ‎_pv‎)، موسّعًا المجموعة إن كانت مطويّة. */
async function sideNav(page, group, label) {
  const link = page.getByRole('link', { name: label, exact: true });
  if (!(await link.first().isVisible().catch(() => false))) {
    await page.getByRole('button', { name: group }).first().click();
  }
  await page.getByRole('link', { name: label, exact: true }).first().click();
}

test.describe('معاينة مالك المنصّة (P3) — رحلة كاملة', () => {
  test('93- المالك يعاين سياق عميل دقيقًا، يتنقّل، تُحظر محاولة التحوّر، ثم يخرج', async ({ page }) => {
    await ownerLogin(page);
    await openTenant(page);

    // اختيار سياق العميل الدقيق (نايك) والضغط على «معاينة» من الواجهة الحقيقية.
    const nikeRow = page.locator('.ih-risk', { hasText: 'نايك السعودية' });
    await nikeRow.getByTestId('preview-client').first().click();
    await page.waitForURL('**/client**');

    // البوّابة الحقيقية + شريط المعاينة + هوية الهدف الظاهرة.
    await expect(page.locator('[role="status"]')).toContainText('وضع المعاينة');
    await expect(page.locator('[role="status"]')).toContainText('عميل نايك');
    await expect(page.locator('body')).toContainText('نايك السعودية');
    expect(page.url()).toContain('_pv=');

    // تنقّل عبر صفحات حقيقية — المعاينة تبقى نشطة والرمز محفوظ (المعترِض يُبقيه).
    await sideNav(page, 'العمل', 'الحملات');
    await page.waitForURL('**/client/campaigns**');
    expect(page.url()).toContain('_pv=');
    await expect(page.locator('[role="status"]')).toContainText('وضع المعاينة');

    await sideNav(page, 'العمل', 'العقود');
    await page.waitForURL('**/client/contracts**');
    expect(page.url()).toContain('_pv=');
    await expect(page.locator('[role="status"]')).toContainText('وضع المعاينة');

    // محاولة تحوّر من زرّ حقيقيّ ومستقلّ عن الترتيب: إنشاء علامة جديدة (POST /brands)
    // ⇒ الخادم يردّ 403 قبل أي إنشاء. (لا نعتمد على حالة سجلّ قد يُطفَّر في اختبار سابق.)
    await sideNav(page, 'الحساب', 'العلامات');
    await page.waitForURL('**/client/brands**');
    await page.getByRole('button', { name: '+ علامة جديدة' }).click();
    await page.locator('.modal input').first().fill('علامة معاينة اختبار');
    const [resp] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && /\/brands(\?|$)/.test(r.url())),
      page.locator('.modal').getByRole('button', { name: 'حفظ كمسودة' }).click(),
    ]);
    expect(resp.status()).toBe(403);   // التحوّر مُنِع خادميًّا قبل أي تغيير

    // Inertia يعرض ردّ 403 في نافذة خطأ (APP_DEBUG) تعترض النقر — نُغلقها ونُغلق نافذة العلامة.
    await page.keyboard.press('Escape');
    await page.locator('#inertia-error-dialog').waitFor({ state: 'detached' }).catch(() => {});
    await page.locator('.modal').getByRole('button', { name: 'إلغاء' }).click().catch(() => {});

    // جلسة المالك سليمة: الخروج من المعاينة يعيده إلى /platform (لم تُفسد جلسته).
    await page.getByRole('link', { name: 'الخروج من المعاينة' }).click();
    await page.waitForURL('**/platform**');
    await expect(page.locator('body')).toContainText('مركز التحكّم');
  });
});
