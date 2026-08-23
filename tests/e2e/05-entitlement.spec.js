import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('حدود الخطة (Entitlements)', () => {
    test('25- مستأجر باء عند حد 1 يُرفض إنشاء عميل ثانٍ', async ({ page }) => {
        await login(page, 'admin@b.test');
        await page.goto('/app/clients');
        await page.getByRole('button', { name: 'عميل جديد' }).first().click();
        await expect(page.locator('.modal')).toBeVisible();
        await page.locator('.modal input').first().fill('عميل زائد');
        // customers.max يحتسب الحالات «مؤهّل/نشط» فقط (المهتم مجّاني) — نختار «نشط»
        // كي يُفعَّل الحدّ فعلًا؛ وإلا فإنشاء «مهتم» ثانٍ مسموح تصميمًا.
        await page.locator('.modal select').nth(1).selectOption('active');
        await page.locator('.modal').getByRole('button', { name: 'إنشاء العميل' }).click();
        // الرفض يُعرض داخل النافذة (لا يُبتلع بصمت) — رسالة بلوغ حدّ الخطة.
        await expect(page.locator('.modal')).toContainText('حد العملاء');
    });
    test('26- عميل باء الأصلي ما زال موجودًا بعد الرفض', async ({ page }) => {
        await login(page, 'admin@b.test');
        await page.goto('/app/clients');
        await expect(page.locator('body')).toContainText('عميل باء الوحيد');
    });
});
