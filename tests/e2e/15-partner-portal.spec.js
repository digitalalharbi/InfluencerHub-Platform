import { test, expect } from '@playwright/test';

const PW = process.env.E2E_PASSWORD;

async function agencyLogin(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@a.test');
    await page.fill('input[name="password"]', PW);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/app**');
}

test.describe('بوابة الوكالة الخارجية والشريك — Phase 5', () => {
    test('69- الوكالة ترى قائمة الوكالات الخارجية المعتمدة', async ({ page }) => {
        await agencyLogin(page);
        await page.goto('/app/partner-agencies');
        await expect(page.locator('body')).toContainText('وكالة نجمة الإبداع');
        await expect(page.locator('body')).toContainText('معتمدة');
    });

    test('70- تفصيل الوكالة يعرض العضو والرابط المُنطّق وسجل الحالة', async ({ page }) => {
        await agencyLogin(page);
        await page.goto('/app/partner-agencies');
        await page.click('a:has-text("فتح")');
        await expect(page.locator('body')).toContainText('سارة الشريك');
        await expect(page.locator('body')).toContainText('نايك السعودية');
        await expect(page.locator('body')).toContainText('سجل الحالة');
    });

    test('71- دخول الشريك يرى فقط روابطه المُنطّقة', async ({ page }) => {
        await page.goto('/partner/login');
        await page.fill('input[name="email"]', 'partner@a.test');
        await page.fill('input[name="password"]', PW);
        await page.click('button:has-text("دخول")');
        await page.waitForURL('**/partner/dashboard');
        await expect(page.locator('body')).toContainText('نايك السعودية');
        await expect(page.locator('body')).toContainText('عرض البريفات');
        // لا يرى عملاء غير مرتبطين
        await expect(page.locator('body')).not.toContainText('مطاعم البيك');
    });

    test('72- بوابة الشريك fail-closed: غير الأعضاء يُمنعون', async ({ page }) => {
        // مستخدم وكالة عادي ليس عضو شريك
        await page.goto('/partner/login');
        await page.fill('input[name="email"]', 'admin@a.test');
        await page.fill('input[name="password"]', PW);
        await page.click('button:has-text("دخول")');
        await expect(page.locator('body')).toContainText('لا توجد عضوية شريك');
    });

    test('73- قبول دعوة شريك عام: إنشاء حساب والدخول للوحة المُنطّقة', async ({ page }) => {
        await page.goto('/partner/invite/e2e-partner-invite');
        await expect(page.locator('body')).toContainText('قبول دعوة الشريك');
        await expect(page.locator('input[disabled]')).toHaveValue('invited@partner.test'); // البريد المدعو قيمة حقل
        await page.fill('input[name="name"]', 'عضو مدعو');
        await page.fill('input[name="password"]', 'InvitePass2026');
        await page.fill('input[name="password_confirmation"]', 'InvitePass2026');
        await page.click('button:has-text("قبول وإنشاء الحساب")');
        await page.waitForURL('**/partner/dashboard');
        await expect(page.locator('body')).toContainText('لوحة الشريك');
        await expect(page.locator('body')).toContainText('نايك السعودية');
    });

    test('74- الدعوة أحادية الاستخدام: إعادة الرمز غير صالحة', async ({ page }) => {
        // بعد استخدامها في 73 تصبح غير صالحة (تعتمد على ترتيب الحالة داخل الملف)
        await page.goto('/partner/invite/e2e-partner-invite');
        await expect(page.locator('body')).toContainText('دعوة غير صالحة');
    });
});
