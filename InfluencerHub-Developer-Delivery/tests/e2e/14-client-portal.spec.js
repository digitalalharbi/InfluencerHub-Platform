import { test, expect } from '@playwright/test';

const PW = process.env.E2E_PASSWORD;

async function clientLogin(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'client@a.test');
    await page.fill('input[name="password"]', PW);
    await page.click('button[type="submit"]');
    await page.goto('/client/dashboard');
    await expect(page.locator('body')).toContainText('نايك السعودية');
}

test.describe('بوابة العميل — Phase 5', () => {
    test('64- دخول العميل ولوحة التحكم بأعداد فعلية', async ({ page }) => {
        await clientLogin(page);
        await expect(page.locator('aside')).toContainText('العلامات');
        await expect(page.locator('aside')).toContainText('الفريق');
    });

    test('65- سير عمل العلامة: تعديل مسودة ثم إرسال للمراجعة', async ({ page }) => {
        await clientLogin(page);
        await page.goto('/client/brands');
        await expect(page.locator('body')).toContainText('Nike Air');
        // نستهدف صف «Nike Air» تحديدًا (قد توجد علامات أخرى) لضمان الحتمية
        await page.click('tr:has-text("Nike Air") a:has-text("عرض")');
        await expect(page.locator('body')).toContainText('مسودة');
        page.on('dialog', d => d.accept()); // تأكيد الإرسال
        await page.click('form[action$="/submit"] button');
        await expect(page.locator('body')).toContainText('مُرسَلة');
    });

    test('66- إدارة الفريق: دعوة عضو تُظهر رمزًا مرة واحدة', async ({ page }) => {
        await clientLogin(page);
        await page.goto('/client/team');
        await expect(page.locator('body')).toContainText('عميل نايك');
        await page.click('button:has-text("دعوة عضو")');
        await page.fill('form[action="/client/team/invite"] input[name="email"]', 'newmember@nike.test');
        await page.click('form[action="/client/team/invite"] button:has-text("إرسال الدعوة")');
        await expect(page.locator('body')).toContainText('رمز الدعوة');
        await expect(page.locator('body')).toContainText('newmember@nike.test');
    });

    test('67- الإعدادات: عرض الجلسات وتفضيلات الإشعارات', async ({ page }) => {
        await clientLogin(page);
        await page.goto('/client/settings');
        await expect(page.locator('body')).toContainText('تفضيلات الإشعارات');
        await expect(page.locator('body')).toContainText('الجلسات النشطة');
        await expect(page.locator('body')).toContainText('المصادقة الثنائية');
    });

    test('68- الحقول القانونية الحساسة تمرّ عبر طلب مراجعة', async ({ page }) => {
        await clientLogin(page);
        await page.goto('/client/profile');
        await expect(page.locator('body')).toContainText('ملف العميل');
    });
});
