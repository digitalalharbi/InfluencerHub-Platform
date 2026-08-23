import { test, expect } from '@playwright/test';
async function login(page) {
    await page.goto('/creator/login');
    await page.fill('input[name="email"]', 'creator@a.test');
    await page.fill('input[name="password"]', process.env.E2E_PASSWORD);
    await page.click('button:has-text("دخول")');
    await page.waitForURL('**/creator/dashboard');
}
// المنصّات والخدمات تُدار الآن ضمن صفحة الحساب (React/Inertia) بتبويبات،
// لا كصفحات مستقلّة — /creator/platforms و /creator/services يعيدان التوجيه إلى /creator/account.
test.describe('CRUD بوابة المبدع', () => {
    test('61- إضافة منصة ثم حذفها', async ({ page }) => {
        await login(page);
        await page.goto('/creator/account');
        await page.getByRole('tab', { name: 'المنصّات' }).click();
        // إضافة منصّة: المنصّة (قائمة) + المعرّف مطلوبان لتفعيل زر الإضافة.
        // المتابعون مطلوب فعليًا: عمود followers_count في قاعدة البيانات NOT NULL،
        // فتُملأ كي ينجح الإنشاء (مسار المستخدم الواقعي).
        await page.getByLabel('المنصّة').selectOption({ index: 1 });
        await page.getByLabel('المعرّف').fill('portal_handle');
        await page.getByLabel('المتابعون').fill('1000');
        await page.getByRole('button', { name: 'إضافة', exact: true }).click();
        await expect(page.locator('body')).toContainText('portal_handle');
        // حذف بطاقة المنصّة المضافة تحديدًا (قد تكون هناك منصّات أخرى)
        const card = page.locator('.card', { hasText: 'portal_handle' });
        await card.getByRole('button', { name: 'حذف', exact: true }).click();
        await expect(page.locator('body')).not.toContainText('portal_handle');
    });
    test('62- إضافة خدمة بسعر يُخزَّن بوحدات صغرى', async ({ page }) => {
        await login(page);
        await page.goto('/creator/account');
        await page.getByRole('tab', { name: 'الخدمات' }).click();
        // السعر يُدخل بالريال ويُخزَّن بالهللات (×100): 2500 ر.س ⇒ 250000 هللة ⇒ يُعرض 2,500 ر.س
        await page.getByLabel('السعر').fill('2500');
        await page.getByRole('button', { name: 'إضافة', exact: true }).click();
        await expect(page.locator('body')).toContainText('2,500 ر.س');
    });
    test('63- الوحدات المستقبلية فقط تعرض Not available', async ({ page }) => {
        await login(page);
        // صفحة مبنيّة فعلًا (الحساب) لا تعرض بوّابة "غير متاح"
        await page.goto('/creator/account');
        await expect(page.locator('body')).not.toContainText('Not available yet');
        // العقود صارت صفحة React حقيقية؛ الوحدة المستقبلية المتبقّية هي الفرص
        await page.goto('/creator/opportunities');
        await expect(page.locator('body')).toContainText('Not available yet'); // المستقبلية فقط
    });
});
