import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('التصميم والواجهة', () => {
    test.beforeEach(async ({ page }) => login(page));

    test('27- الهوية البصرية باللون التيل من التصميم القديم', async ({ page }) => {
        await page.goto('/app/clients');
        const btn = page.locator('.btn-primary').first();
        const bg = await btn.evaluate((el) => getComputedStyle(el).backgroundColor);
        expect(bg).toBe('rgb(13, 138, 111)'); // #0d8a6f
    });
    test('28- الاتجاه RTL على مستوى الصفحة', async ({ page }) => {
        await page.goto('/app');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    });
    test('29- شارات الحالة تظهر بألوانها', async ({ page }) => {
        await page.goto('/app/clients');
        await expect(page.locator('.badge-active').first()).toBeVisible();
        await expect(page.locator('.badge-lead').first()).toBeVisible();
    });
    test('30- تجاوب الجوال: الشريط الجانبي والمحتوى يظهران', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/app/clients');
        await expect(page.locator('body')).toContainText('العملاء');
        await expect(page.locator('table')).toBeVisible();
    });
});
