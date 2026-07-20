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
        await page.click('button:has-text("+ مبدع جديد")');
        await expect(page.locator('.modal')).toBeVisible();
        const f = page.locator('.modal form');
        await f.locator('input[name="display_name"]').fill('سلمى الرشيد');
        await f.locator('select[name="type"]').selectOption('influencer');
        await f.locator('input[name="handle"]').fill('salma_r');
        await f.locator('button:has-text("حفظ")').click();
        await page.waitForURL('**/app/creators**');
        await expect(page.locator('body')).toContainText('سلمى الرشيد');
    });

    test('41- فتح ملف المبدع يعرض رقمه وبياناته', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app/creators?type=influencer');
        await page.click('a:has-text("نورة القحطاني")');
        await expect(page.locator('body')).toContainText('ملف المبدع');
        await expect(page.locator('body')).toContainText('CR-1-');
    });

    test('42- القائمة الجانبية فيها روابط المبدعين وتعمل', async ({ page }) => {
        await login(page, 'admin@a.test');
        await page.goto('/app');
        await page.click('a:has-text("المؤثرون")');
        await expect(page).toHaveURL(/type=influencer/);
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
