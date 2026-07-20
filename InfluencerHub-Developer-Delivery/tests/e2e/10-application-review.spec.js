import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/** Phase 4 — مراجعة الوكالة + القبول وإنشاء الحساب. */
test.describe('مراجعة طلبات الانضمام', () => {
    test('47- قائمة الطلبات تعرض الطلب المُرسَل', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creator-applications');
        await expect(page.locator('body')).toContainText('ريناد الزهراني');
        await expect(page.locator('body')).toContainText('renad@applicant.test');
    });

    test('48- تفاصيل الطلب تعرض التبويبات والإجراءات', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creator-applications');
        await page.click('a:has-text("ريناد الزهراني")');
        await expect(page.locator('body')).toContainText('مراجعة طلب انضمام');
        await expect(page.locator('button:has-text("قبول وإنشاء الحساب")')).toBeVisible();
        await page.click('button:has-text("المنصات")');
        await expect(page.locator('body')).toContainText('renad.style');
    });

    test('49- القبول ينشئ مبدعًا وينقل المنصة', async ({ page }) => {
        await login(page, 'admin@a.test');
        // اقبل عبر طلب POST مباشر (نتجاوز حوار confirm)
        await page.goto('/app/creator-applications');
        const href = await page.locator('a:has-text("ريناد الزهراني")').getAttribute('href');
        const id = href.split('/').pop();
        const token = await page.getAttribute('meta[name="csrf-token"]', 'content');
        const res = await page.request.post(`/app/creator-applications/${id}/approve`, {
            form: { _token: token }, maxRedirects: 0,
        });
        expect([302, 303]).toContain(res.status()); // تحويل لصفحة المبدع الجديد
        // المبدع الجديد ظاهر
        await page.goto('/app/creators?type=influencer');
        await expect(page.locator('body')).toContainText('Renad');
    });

    test('50- المشاهد لا يرى إجراء القبول', async ({ page }) => {
        await login(page, 'viewer@a.test');
        await page.goto('/app/creator-applications');
        // viewer يرى القائمة لكن الطلب المتبقّي (إن وُجد) بلا زر قبول — أو 403 على POST
        const token = await page.getAttribute('meta[name="csrf-token"]', 'content');
        const res = await page.request.post('/app/creator-applications/1/approve', {
            form: { _token: token }, headers: { Accept: 'application/json' }, maxRedirects: 0,
        });
        expect([403, 404]).toContain(res.status());
    });
});
