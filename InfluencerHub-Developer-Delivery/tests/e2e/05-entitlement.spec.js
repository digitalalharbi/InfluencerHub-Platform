import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('حدود الخطة (Entitlements)', () => {
    test('25- مستأجر باء عند حد 1 يُرفض إنشاء عميل ثانٍ', async ({ page }) => {
        await login(page, 'admin@b.test');
        await page.goto('/app/clients');
        await page.click('button:has-text("+ عميل جديد")');
        await page.fill('.modal input[name="display_name"]', 'عميل زائد');
        await page.click('.modal button:has-text("حفظ")');
        await expect(page.locator('body')).toContainText('حد العملاء');
    });
    test('26- عميل باء الأصلي ما زال موجودًا بعد الرفض', async ({ page }) => {
        await login(page, 'admin@b.test');
        await page.goto('/app/clients');
        await expect(page.locator('body')).toContainText('عميل باء الوحيد');
    });
});
