import { test, expect } from '@playwright/test';

/**
 * لوحة الأوامر (⌘K) — فتح عبر الزر والاختصار، بحث فوري، والانتقال إلى وجهة.
 * مصدر الأوامر: إعداد التنقّل المركزي (بيانات تنقّل غير حسّاسة فقط).
 */
const PW = process.env.E2E_PASSWORD;

async function agencyLogin(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@a.test');
    await page.fill('input[name="password"]', PW);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/app**');
}

test.describe('لوحة الأوامر (⌘K)', () => {
    test.beforeEach(async ({ page }) => {
        await agencyLogin(page);
        await page.goto('/app');
        await page.waitForLoadState('networkidle');
    });

    test('تُفتح بزر «بحث سريع» وتعرض وجهات', async ({ page }) => {
        await page.locator('.ih-cmdk__trigger').first().click();
        await expect(page.locator('.ih-cmdk')).toBeVisible();
        // تعرض عدة أوامر من إعداد التنقّل
        expect(await page.locator('.ih-cmdk__item').count()).toBeGreaterThan(5);
        await expect(page.locator('.ih-cmdk__item').first()).toContainText('لوحة التحكم');
    });

    test('تُفتح باختصار لوحة المفاتيح ⌘K', async ({ page }) => {
        await page.keyboard.press('Meta+k');
        await expect(page.locator('.ih-cmdk')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('.ih-cmdk')).toBeHidden();
    });

    test('البحث يرشّح النتائج والانتقال يعمل', async ({ page }) => {
        await page.locator('.ih-cmdk__trigger').first().click();
        await page.locator('.ih-cmdk__search input').fill('حمل');
        // نتيجة واحدة: الحملات
        const items = page.locator('.ih-cmdk__item');
        await expect(items).toHaveCount(1);
        await expect(items.first()).toContainText('الحملات');
        // Enter ينتقل إلى الوجهة
        await page.locator('.ih-cmdk__search input').press('Enter');
        await page.waitForURL('**/app/campaigns');
        expect(page.url()).toContain('/app/campaigns');
    });

    test('لا تعرض عناصر «قريبًا»', async ({ page }) => {
        await page.locator('.ih-cmdk__trigger').first().click();
        await page.locator('.ih-cmdk__search input').fill('الإعدادات');
        // «الإعدادات» عنصر قريبًا في الوكالة → غير مدرج في اللوحة
        await expect(page.locator('.ih-cmdk__empty')).toBeVisible();
    });
});
