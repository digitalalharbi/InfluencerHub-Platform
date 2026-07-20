import { test, expect } from '@playwright/test';
async function login(page) {
    await page.goto('/creator/login');
    await page.fill('input[name="email"]', 'creator@a.test');
    await page.fill('input[name="password"]', process.env.E2E_PASSWORD);
    await page.click('button:has-text("دخول")');
    await page.waitForURL('**/creator/dashboard');
}
test.describe('CRUD بوابة المبدع', () => {
    test('61- إضافة منصة ثم حذفها', async ({ page }) => {
        await login(page);
        await page.goto('/creator/platforms');
        await page.fill('input[name="handle"]', 'portal_handle');
        await page.click('form[action="/creator/platforms"] button:has-text("إضافة")');
        await expect(page.locator('body')).toContainText('portal_handle');
        page.on('dialog', d => d.accept());
        await page.click('button:has-text("حذف")');
        await expect(page.locator('body')).not.toContainText('portal_handle');
    });
    test('62- إضافة خدمة بسعر يُخزَّن بوحدات صغرى', async ({ page }) => {
        await login(page);
        await page.goto('/creator/services');
        await page.fill('input[name="price"]', '2500');
        await page.click('form[action="/creator/services"] button:has-text("إضافة")');
        await expect(page.locator('body')).toContainText('ر.س');
    });
    test('63- الوحدات المستقبلية فقط تعرض Not available', async ({ page }) => {
        await login(page);
        await page.goto('/creator/platforms');
        await expect(page.locator('body')).not.toContainText('Not available yet'); // الست صفحات تعمل
        await page.goto('/creator/contracts');
        await expect(page.locator('body')).toContainText('Not available yet'); // المستقبلية فقط
    });
});
