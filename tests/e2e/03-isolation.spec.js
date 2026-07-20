import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('عزل المستأجرين', () => {
    test('17- مستأجر باء لا يرى عملاء ألف في القائمة', async ({ page }) => {
        await login(page, 'admin@b.test');
        await page.goto('/app/clients');
        await expect(page.locator('body')).toContainText('عميل باء الوحيد');
        await expect(page.locator('body')).not.toContainText('نايك السعودية');
    });
    test('18- مستأجر باء يحصل 404 على ملف عميل ألف', async ({ page }) => {
        await login(page, 'admin@b.test');
        const res = await page.goto('/app/clients/1'); // عميل يخص ألف
        expect(res.status()).toBe(404);
    });
    test('19- مستأجر ألف لا يرى عميل باء', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/clients');
        await expect(page.locator('body')).not.toContainText('عميل باء الوحيد');
    });
    test('20- منع IDOR على تنزيل مستند عبر API لمستأجر آخر', async ({ page }) => {
        await login(page, 'admin@b.test');
        // طلب API مباشر بترويسة JSON (لا يتبع أي تحويل) → يجب ألا ينجح الوصول لمستند ألف.
        // العزل fail-closed: غير مصادَق (401) أو ممنوع (403) أو غير موجود (404) — أبدًا 200.
        const res = await page.request.get('/api/v1/clients/1/documents/1/download', {
            headers: { Accept: 'application/json' },
            maxRedirects: 0,
        });
        expect(res.status()).not.toBe(200);
        expect([401, 403, 404]).toContain(res.status());
    });
});
