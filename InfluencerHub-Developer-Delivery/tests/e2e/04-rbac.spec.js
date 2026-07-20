import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('الصلاحيات (RBAC)', () => {
    test('21- المشاهد يرى قائمة العملاء', async ({ page }) => {
        await login(page, 'viewer@a.test');
        await page.goto('/app/clients');
        await expect(page.locator('body')).toContainText('نايك السعودية');
    });
    test('22- المشاهد لا يستطيع الإنشاء (403)', async ({ page }) => {
        await login(page, 'viewer@a.test');
        const res = await page.request.post('/app/clients', {
            form: { display_name: 'ممنوع', status: 'active', _token: await csrf(page) },
        });
        expect(res.status()).toBe(403);
    });
    test('23- المشاهد لا يستطيع الأرشفة (403)', async ({ page }) => {
        await login(page, 'viewer@a.test');
        const res = await page.request.fetch('/app/clients/1', {
            method: 'DELETE', form: { _token: await csrf(page) },
        });
        expect(res.status()).toBe(403);
    });
    test('24- المشاهد لا يرى زر الأرشفة في ملف العميل', async ({ page }) => {
        await login(page, 'viewer@a.test');
        await page.goto('/app/clients/1');
        await expect(page.locator('button:has-text("أرشفة")')).toHaveCount(0);
    });
});

// يقرأ رمز CSRF من الصفحة الحالية
async function csrf(page) {
    await page.goto('/app/clients');
    return await page.getAttribute('meta[name="csrf-token"]', 'content');
}
