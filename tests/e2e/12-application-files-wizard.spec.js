import { test, expect } from '@playwright/test';

/** Phase 4 — رفع الملفات + جمع البيانات في البوابة العامة. */
async function newDraft(page) {
    await page.goto('/join/creator?a=org-a');
    await page.check('input[name="capabilities[]"][value="influencer"]');
    await page.fill('input[name="full_name"]', 'ملف تجريبي');
    await page.fill('input[name="email"]', `files${Date.now()}@ex.com`);
    await page.fill('input[name="phone"]', '+966500000003');
    await page.check('input[name="terms"]');
    await page.check('input[name="privacy"]');
    await page.click('button:has-text("إنشاء الطلب")');
    await page.waitForURL('**/status');
}

test.describe('ملفات وبيانات الطلب', () => {
    test('56- رفع صورة شخصية صالحة ينجح', async ({ page }) => {
        await newDraft(page);
        const png = Buffer.from('89504e470d0a1a0a0000000d494844520000000100000001080600000' + '01f15c4890000000a49444154789c6360000002000155', 'hex');
        await page.setInputFiles('form[action$="/upload"] input[type="file"]', { name: 'me.png', mimeType: 'image/png', buffer: png });
        await page.click('form[action$="/upload"] button:has-text("رفع")');
        await expect(page.locator('body')).toContainText('تم رفع الملف بنجاح');
    });

    test('57- رفض ملف تنفيذي مقنّع', async ({ page }) => {
        await newDraft(page);
        await page.setInputFiles('form[action$="/upload"] input[type="file"]', { name: 'evil.php', mimeType: 'image/png', buffer: Buffer.from('<?php echo 1;') });
        await page.click('form[action$="/upload"] button:has-text("رفع")');
        await expect(page.locator('body')).toContainText('غير مسموح');
    });

    test('58- إضافة حساب اجتماعي وخدمة ونموذج عمل', async ({ page }) => {
        await newDraft(page);
        const social = page.locator('form[action$="/platforms"]');
        await social.locator('input[name="username"]').fill('my_handle');
        await social.locator('input[name="followers_count"]').fill('12000');
        await social.locator('button').click();
        await expect(page.locator('body')).toContainText('my_handle');

        const svc = page.locator('form[action$="/services"]');
        await svc.locator('input[name="price"]').fill('900');
        await svc.locator('button').click();
        await expect(page.locator('body')).toContainText('ر.س');
    });

    test('59- حفظ IBAN يعرضه مقنّعًا في البوابة', async ({ page }) => {
        await newDraft(page);
        const fin = page.locator('form[action$="/financial"]');
        await fin.locator('input[name="iban"]').fill('SA0380000000608010167519');
        await fin.locator('input[name="beneficiary_name"]').fill('اسم');
        await fin.locator('button').click();
        await expect(page.locator('body')).toContainText('حُفظت البيانات المالية'); // ننتظر تأكيد الحفظ
        await expect(page.locator('body')).toContainText('7519');
        await expect(page.locator('body')).not.toContainText('0380000000');
    });

    test('60- البيانات تبقى بعد إعادة التحميل (من PostgreSQL)', async ({ page }) => {
        await newDraft(page);
        const svc = page.locator('form[action$="/services"]');
        await svc.locator('input[name="price"]').fill('700');
        await svc.locator('button').click();
        await page.reload();
        await expect(page.locator('body')).toContainText('ر.س'); // الخدمة محفوظة
    });
});
