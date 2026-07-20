import { test, expect } from '@playwright/test';

/** Phase 4 — بوابة الانضمام العامة (بلا دخول): landing، نموذج، مسودة، متابعة، OTP. */
test.describe('بوابة الانضمام العامة', () => {
    test('44- صفحة الانضمام العامة تفتح بلا تسجيل دخول', async ({ page }) => {
        await page.goto('/join');
        await expect(page.locator('body')).toContainText('انضم إلى شبكة المبدعين');
        await expect(page.locator('a:has-text("ابدأ طلب الانضمام")')).toBeVisible();
    });

    test('45- تقديم الخطوة الأولى ينشئ مسودة بمرجع غير متسلسل', async ({ page }) => {
        await page.goto('/join/creator?a=org-a');
        await page.check('input[name="capabilities[]"][value="influencer"]');
        await page.fill('input[name="full_name"]', 'سلمى التجريبية');
        await page.fill('input[name="email"]', `salma${Date.now()}@ex.com`);
        await page.fill('input[name="phone"]', '+966500000001');
        await page.check('input[name="terms"]');
        await page.check('input[name="privacy"]');
        await page.click('button:has-text("إنشاء الطلب")');
        await page.waitForURL('**/join/creator/**/status');
        await expect(page.locator('body')).toContainText('رقم المرجع');
        await expect(page.locator('body')).toContainText('CA-'); // مرجع عشوائي
    });

    test('46- تأكيد البريد عبر OTP (وضع تطوير)', async ({ page }) => {
        await page.goto('/join/creator?a=org-a');
        await page.check('input[name="capabilities[]"][value="ugc"]');
        await page.fill('input[name="full_name"]', 'OTP User');
        await page.fill('input[name="email"]', `otp${Date.now()}@ex.com`);
        await page.fill('input[name="phone"]', '+966500000002');
        await page.check('input[name="terms"]');
        await page.check('input[name="privacy"]');
        await page.click('button:has-text("إنشاء الطلب")');
        await page.waitForURL('**/status');
        // أرسل الرمز (يظهر في وضع التطوير)
        await page.click('button:has-text("إرسال رمز التحقق")');
        const otp = (await page.locator('body').innerText()).match(/\b\d{6}\b/)[0];
        await page.fill('input[name="code"]', otp);
        await page.click('button:has-text("تأكيد")');
        await expect(page.locator('body')).toContainText('تم التحقق من البريد');
    });
});
