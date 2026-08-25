import { test, expect } from '@playwright/test';

/**
 * معاينة مالك المنصّة عبر عدّة نوافذ/تبويبات (§P3) — هذه الميزة موجودة تحديدًا ليعمل المالك
 * عبر تبويبات مختلفة دون تصادم. تبويب ١: لوحة المنصّة. تبويب ٢: معاينة العميل «نايك».
 * تبويب ٣: معاينة العميل «stc». نتحقّق أن كلًّا مستقلّ (مستأجر/بوّابة/هدف/كيان/شريط)، وأن
 * التنقّل في تبويب لا يغيّر الآخر — بلا تصادم active_client_id (الحالة في الـURL لا الجلسة).
 */
const PW = process.env.E2E_PASSWORD;

async function ownerLogin(page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'owner@platform.test');
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

async function startClientPreview(tab, entityLabel) {
  await tab.goto('/platform/tenants');
  await tab.click('a:has-text("وكالة ألف")');
  await tab.waitForURL('**/platform/tenants/**');
  const row = tab.locator('.ih-risk', { hasText: entityLabel });
  await row.getByTestId('preview-client').first().click();
  await tab.waitForURL('**/client**');
}

async function sideNav(tab, group, label) {
  const link = tab.getByRole('link', { name: label, exact: true });
  if (!(await link.first().isVisible().catch(() => false))) {
    await tab.getByRole('button', { name: group }).first().click();
  }
  await tab.getByRole('link', { name: label, exact: true }).first().click();
}

test.describe('معاينة متعدّدة التبويبات — عزل حقيقي (P3)', () => {
  test('94- تبويبان بسياقَي عميل مختلفين يبقيان مستقلّين عبر التنقّل', async ({ page, context }) => {
    // تبويب ١: لوحة المنصّة.
    await ownerLogin(page);
    await page.goto('/platform');
    await expect(page.locator('body')).toContainText('مركز التحكّم');

    // تبويب ٢: معاينة «نايك». تبويب ٣: معاينة «stc» — نفس المستأجر، عميلان مختلفان.
    const tab2 = await context.newPage();
    await startClientPreview(tab2, 'نايك السعودية');
    const tab3 = await context.newPage();
    await startClientPreview(tab3, 'stc');

    // تحقّق مستقلّ: كلّ تبويب هدفُه وكيانه وشريطه الصحيح.
    await expect(tab2.locator('[role="status"]')).toContainText('عميل نايك');
    await expect(tab2.locator('body')).toContainText('نايك السعودية');
    await expect(tab3.locator('[role="status"]')).toContainText('عميل اس تي سي');
    await expect(tab3.locator('body')).toContainText('stc');

    // تبويب ١ ما زال على المنصّة (لا معاينة) — لا تلوّث.
    await expect(page.locator('body')).toContainText('مركز التحكّم');

    // التنقّل في تبويب ٢ لا يغيّر تبويب ٣.
    await sideNav(tab2, 'العمل', 'الحملات');
    await tab2.waitForURL('**/client/campaigns**');
    expect(tab2.url()).toContain('_pv=');
    await tab3.reload();
    await expect(tab3.locator('[role="status"]')).toContainText('عميل اس تي سي');
    await expect(tab3.locator('body')).toContainText('stc');   // بقي «stc» — لا تصادم

    // والعكس: التنقّل في تبويب ٣ لا يغيّر تبويب ٢.
    await sideNav(tab3, 'العمل', 'الحملات');
    await tab3.waitForURL('**/client/campaigns**');
    await tab2.reload();
    await expect(tab2.locator('[role="status"]')).toContainText('عميل نايك');
    await expect(tab2.locator('body')).toContainText('نايك السعودية');   // بقي «نايك» — لا تصادم

    await tab2.close();
    await tab3.close();
  });
});
