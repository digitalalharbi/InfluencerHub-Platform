import { test, expect } from '@playwright/test';

/** Phase 4 — بوابة المبدع: دخول، لوحة، ملف، IBAN مشفّر، وحدات لاحقة Not available. */
async function creatorLogin(page) {
    await page.goto('/creator/login');
    await page.fill('input[name="email"]', 'creator@a.test');
    await page.fill('input[name="password"]', process.env.E2E_PASSWORD);
    await page.click('button:has-text("دخول")');
    await page.waitForURL('**/creator/dashboard');
}

test.describe('بوابة المبدع', () => {
    test('51- دخول المبدع يصل للوحته', async ({ page }) => {
        await creatorLogin(page);
        await expect(page.locator('body')).toContainText('لوحة المبدع');
        await expect(page.locator('body')).toContainText('CR-1-');
    });

    test('52- تحديث الملف يحفظ في PostgreSQL', async ({ page }) => {
        await creatorLogin(page);
        await page.goto('/creator/profile');
        await page.fill('input[name="city"]', 'الرياض المحدّثة');
        await page.click('button:has-text("حفظ التعديلات")');
        await expect(page.locator('body')).toContainText('تم تحديث ملفك');
    });

    test('53- حفظ IBAN يعرضه مقنّعًا فقط', async ({ page }) => {
        await creatorLogin(page);
        await page.goto('/creator/financial');
        await page.fill('input[name="iban"]', 'SA0380000000608010167519');
        await page.fill('input[name="beneficiary_name"]', 'نورة');
        await page.click('button:has-text("حفظ")');
        await expect(page.locator('body')).toContainText('7519');
        await expect(page.locator('body')).not.toContainText('0380000000'); // لا يُعرض كاملًا
        await expect(page.locator('body')).toContainText('قيد التحقق'); // لا يعتمد نفسه
    });

    test('54- الوحدات اللاحقة تعرض Not available yet', async ({ page }) => {
        await creatorLogin(page);
        await page.goto('/creator/opportunities');
        await expect(page.locator('body')).toContainText('Not available yet');
    });

    test('55- غير المبدع لا يدخل البوابة', async ({ page }) => {
        await page.goto('/creator/login');
        await page.fill('input[name="email"]', 'admin@a.test'); // ليس مبدعًا
        await page.fill('input[name="password"]', process.env.E2E_PASSWORD);
        await page.click('button:has-text("دخول")');
        await expect(page.locator('body')).toContainText('ليس حساب مبدع');
    });
});
