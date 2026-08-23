import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/** Phase 4 — المبدعون: تصفية بالنوع + إنشاء عبر النافذة + تفاصيل + سياسات + عزل. */
test.describe('المبدعون', () => {
    test('38- قائمة المؤثرين تصفّي بالنوع (تشمل both وتستثني UGC الصرف)', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creators?type=influencer');
        await expect(page.locator('body')).toContainText('نورة القحطاني');
        await expect(page.locator('body')).toContainText('محمد الشمري'); // both
        await expect(page.locator('body')).not.toContainText('ستوديو لقطة'); // ugc صرف
    });

    test('39- قائمة صنّاع UGC تصفّي بالنوع', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creators?type=ugc_creator');
        await expect(page.locator('body')).toContainText('ستوديو لقطة');
        await expect(page.locator('body')).toContainText('محمد الشمري'); // both
        await expect(page.locator('body')).not.toContainText('نورة القحطاني'); // مؤثّر صرف
    });

    test('40- إنشاء مبدع عبر النافذة يظهر في القائمة', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creators');
        // React/Inertia: النافذة بلا <form>؛ الحقول بلا سمة name؛ القدرة «مؤثرون»
        // مُحدّدة افتراضيًا (النوع صار قدرات لا حقلًا مفردًا)؛ زر «حفظ المبدع».
        await page.getByRole('button', { name: 'مبدع جديد' }).first().click();
        await expect(page.locator('.modal')).toBeVisible();
        await page.locator('.modal input').first().fill('سلمى الرشيد');
        await page.locator('.modal').getByRole('button', { name: 'حفظ المبدع' }).click();
        await expect(page.locator('body')).toContainText('سلمى الرشيد');
    });

    test('41- فتح ملف المبدع يعرض رقمه وبياناته', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creators?type=influencer');
        await page.click('a:has-text("نورة القحطاني")');
        await expect(page.locator('body')).toContainText('ملف المبدع');
        await expect(page.locator('body')).toContainText('CR-1-');
    });

    test('42- القائمة الجانبية فيها رابط صناع المحتوى ويعمل', async ({ page }) => {
        // وُحِّدت وجهتا «المؤثرون»/«صناع المحتوى» في رابط واحد «صناع المحتوى» →
        // /app/creators (الفلترة بالقدرة صارت داخل الصفحة).
        await login(page, 'admin@a.test');
        await page.goto('/app');
        await page.locator('aside').getByRole('link', { name: 'صناع المحتوى' }).first().click();
        await expect(page).toHaveURL(/\/app\/creators/);
        await expect(page.locator('body')).toContainText('نورة القحطاني');
    });

    test('43- المشاهد لا يستطيع إنشاء مبدع (403)', async ({ page }) => {
        await login(page, 'viewer@a.test');
        await page.goto('/app/creators');
        await expect(page.locator('body')).toContainText('نورة القحطاني'); // يرى
        const res = await page.request.post('/app/creators', {
            headers: { Accept: 'application/json' }, maxRedirects: 0,
            form: { display_name: 'x', type: 'influencer', _token: await page.getAttribute('meta[name="csrf-token"]', 'content') },
        });
        expect(res.status()).toBe(403); // لا ينشئ
    });
});
