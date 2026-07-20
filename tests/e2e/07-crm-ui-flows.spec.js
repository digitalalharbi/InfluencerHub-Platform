import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/** تدفقات الواجهة التفاعلية لـPhase 3 (بلا أزرار ميتة): علامات/أعضاء/حقول/مستندات. */
test.describe('تدفقات واجهة CRM', () => {
    test.beforeEach(async ({ page }) => login(page, 'admin@a.test'));

    test('31- إضافة علامة من ملف العميل تظهر فورًا', async ({ page }) => {
        await page.goto('/app/clients/1');
        await page.click('button:has-text("العلامات")');
        const f = page.locator('form[action$="/brands"]');
        await f.locator('input[name="name"]').fill('Air Max');
        await f.locator('input[name="sector"]').fill('أحذية');
        await f.locator('button').click();
        await expect(page.locator('body')).toContainText('تمت إضافة العلامة');
        await page.click('button:has-text("العلامات")');
        await expect(page.locator('body')).toContainText('Air Max');
    });

    test('32- العلامة تظهر في صفحة العلامات على مستوى الوكالة', async ({ page }) => {
        await page.goto('/app/brands');
        await expect(page.locator('body')).toContainText('Air Max');
    });

    test('33- دعوة عضو بوابة تُظهر الرمز مرة واحدة', async ({ page }) => {
        await page.goto('/app/clients/1');
        await page.click('button:has-text("أعضاء الفريق")');
        const f = page.locator('form[action$="/members/invite"]');
        await f.locator('input[name="email"]').fill('partner@nike.test');
        await f.locator('select[name="role"]').selectOption('client_admin');
        await f.locator('button').click();
        await expect(page.locator('body')).toContainText('رمز الدعوة');
    });

    test('34- تعريف حقل مخصّص ثم ضبط قيمته', async ({ page }) => {
        await page.goto('/app/clients/1');
        await page.click('button:has-text("حقول مخصّصة")');
        const def = page.locator('form[action$="/custom-fields"]');
        await def.locator('input[name="key"]').fill('tier');
        await def.locator('input[name="label"]').fill('مستوى الحساب');
        await def.locator('button').click();
        await expect(page.locator('body')).toContainText('مستوى الحساب');
        // اضبط القيمة
        await page.click('button:has-text("حقول مخصّصة")');
        const setForm = page.locator('form[action$="/set"]').first();
        await setForm.locator('input[name="value"]').fill('ذهبي');
        await setForm.locator('button').click();
        await expect(page.locator('body')).toContainText('تم حفظ القيمة');
    });

    test('35- إضافة جهة اتصال من الواجهة', async ({ page }) => {
        await page.goto('/app/clients/1');
        await page.click('button:has-text("جهات الاتصال")');
        const f = page.locator('form[action$="/contacts"]');
        await f.locator('input[name="name"]').fill('سارة أحمد');
        await f.locator('input[name="job_title"]').fill('مديرة تسويق');
        await f.locator('button').click();
        await expect(page.locator('body')).toContainText('سارة أحمد');
    });

    test('36- مركز المعاينة يفتح ويعرض حالة الوحدات', async ({ page }) => {
        await page.goto('/app/preview');
        await expect(page.locator('body')).toContainText('Preview Center');
        await expect(page.locator('body')).toContainText('العملاء');
        await expect(page.locator('body')).toContainText('مُتحقَّق بالمتصفّح');
    });

    test('37- رابط مركز المعاينة في القائمة الجانبية يعمل (لا رابط ميت)', async ({ page }) => {
        await page.goto('/app');
        await page.click('a:has-text("مركز المعاينة")');
        await expect(page).toHaveURL(/\/app\/preview/);
        await expect(page.locator('body')).toContainText('Preview Center');
    });
});
