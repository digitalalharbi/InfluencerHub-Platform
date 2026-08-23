import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/**
 * قاعدة المؤثرين (المنتج المميّز) — الرحلة الحقيقية + خصوصية المصدر + الحوكمة.
 *
 * المؤسسة «ألف» مستحقّة (بذور)، «باء» غير مستحقّة. لا يظهر أيّ مصدر/متجر للمستأجر.
 */
test.describe('قاعدة المؤثرين', () => {
    test('75- وكالة مستحقّة: تصفّح → بحث → ملف → تواصل → مفضّلة', async ({ page }) => {
        await login(page, 'admin@a.test');
        // الرابط يظهر في القائمة الجانبية (محكوم بالاستحقاق + الدور)
        await expect(page.locator('aside')).toContainText('قاعدة المؤثرين');
        await page.goto('/app/creator-database');
        await expect(page.locator('body')).toContainText('نجم سناب');

        // بحث
        await page.fill('.ih-search input', 'مبدع تيك');
        await expect(page.locator('body')).toContainText('مبدع تيك');

        // فتح الملف — مدير الوكالة يرى التواصل
        await page.goto('/app/creator-database');
        await page.click('a:has-text("نجم سناب")');
        await expect(page.locator('body')).toContainText('التواصل');
        await expect(page.locator('body')).toContainText('واتساب');

        // مفضّلة
        await page.click('button:has-text("إضافة للمفضّلة")');
        await expect(page.locator('body')).toContainText('مفضّل');
    });

    test('76- ترشيح مبدع من القاعدة إلى حملة (المرحلة 2)', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creator-database');
        await page.click('a:has-text("نجم سناب")');
        // قسم الترشيح لحملة موجود لمن يملك الصلاحية
        await expect(page.locator('body')).toContainText('ترشيح لحملة');
        await page.click('button:has-text("إضافة وترشيح")');
        await expect(page.locator('body')).toContainText('رُشِّح المبدع للحملة');
    });

    test('77- خصوصية المصدر: لا متجر/مصدر في القاعدة ولا الملف', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creator-database');
        await expect(page.locator('body')).not.toContainText('وزنة');   // متجر (محظور)
        await expect(page.locator('body')).not.toContainText('المصدر');
        await page.click('a:has-text("نجم سناب")');
        await expect(page.locator('body')).not.toContainText('وزنة');
        await expect(page.locator('body')).not.toContainText('التكلفة');
    });

    test('78- المُطّلع يتصفّح بلا كشف تواصل', async ({ page }) => {
        await login(page, 'viewer@a.test');
        await page.goto('/app/creator-database');
        await expect(page.locator('body')).toContainText('نجم سناب');
        // لا أزرار تواصل للمُطّلع (VIEW_CONTACT محجوب)
        await expect(page.locator('button:has-text("واتساب")')).toHaveCount(0);
    });

    test('79- مؤسسة غير مستحقّة تُمنع (خادم) ولا يظهر الرابط', async ({ page }) => {
        await login(page, 'admin@b.test');
        // لا رابط قاعدة المؤثرين في قائمة «باء»
        await expect(page.locator('aside')).not.toContainText('قاعدة المؤثرين');
        // والوصول المباشر مرفوض من الخادم (لا اعتماد على إخفاء الواجهة)
        const res = await page.request.get('/app/creator-database');
        expect(res.status()).toBe(403);
    });

    test('80- بوابة العميل لا تتصفّح قاعدة المؤثرين', async ({ page }) => {
        await page.goto('/client/login');
        await page.fill('input[name="email"]', 'client@a.test');
        await page.fill('input[name="password"]', process.env.E2E_PASSWORD);
        await page.click('button[type="submit"]');
        await page.waitForURL('**/client/dashboard');
        const res = await page.request.get('/app/creator-database');
        expect([403, 302]).toContain(res.status());
    });
});
