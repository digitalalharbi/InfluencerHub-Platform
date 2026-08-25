import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/** تدفقات الواجهة التفاعلية لـPhase 3 (بلا أزرار ميتة): علامات/أعضاء/حقول/مستندات. */
test.describe('تدفقات واجهة CRM', () => {
    test.beforeEach(async ({ page }) => login(page, 'admin@a.test'));

    test('31- إضافة علامة من ملف العميل تظهر فورًا', async ({ page }) => {
        await page.goto('/app/clients/1');
        // مُهاجَر من Alpine: التبويب زرّ role=tab بعنوانه، ولوح الإضافة AddPanel يُفتح بزرّ نصّه التسمية
        await page.getByRole('tab', { name: 'العلامات' }).click();
        await page.getByRole('button', { name: 'إضافة علامة' }).click();
        // الحقول بلا name — تُملأ عبر الربط label→input داخل مكوّن Field
        await page.getByLabel('اسم العلامة').fill('Air Max');
        await page.getByLabel('القطاع').fill('أحذية');
        const panel = page.locator('.card').filter({ has: page.getByLabel('اسم العلامة') });
        await panel.getByRole('button', { name: 'حفظ' }).click();
        // النص الحالي للفلاش (تُنشأ العلامة كمسوّدة في مسار الاعتماد)
        await expect(page.locator('body')).toContainText('أُضيفت العلامة كمسوّدة');
        // النتيجة الفعلية: بطاقة العلامة تظهر في تبويب العلامات
        await expect(page.locator('body')).toContainText('Air Max');
    });

    test('32- العلامة تظهر في صفحة العلامات على مستوى الوكالة', async ({ page }) => {
        await page.goto('/app/brands');
        await expect(page.locator('body')).toContainText('Air Max');
    });

    test('33- دعوة عضو بوابة تُظهر الرمز مرة واحدة', async ({ page }) => {
        await page.goto('/app/clients/1');
        // مُهاجَر من Alpine: التبويب الحقيقي «الفريق» لا «أعضاء الفريق»
        await page.getByRole('tab', { name: 'الفريق' }).click();
        await page.getByRole('button', { name: 'دعوة عضو بوابة' }).click();
        await page.getByLabel('البريد').fill('partner@nike.test');
        await page.getByLabel('الدور').selectOption('client_admin');
        const panel = page.locator('.card').filter({ has: page.getByLabel('البريد') });
        await panel.getByRole('button', { name: 'حفظ' }).click();
        // كتلة الرمز «رمز الدعوة — يُعرض مرة واحدة» تُعرض عند وصول inviteToken
        await expect(page.locator('body')).toContainText('رمز الدعوة');
    });

    test('34- تعريف حقل مخصّص ثم ضبط قيمته', async ({ page }) => {
        await page.goto('/app/clients/1');
        // مُهاجَر من Alpine: تبويب + لوح AddPanel «تعريف حقل» بحقول Field بلا name
        await page.getByRole('tab', { name: 'حقول مخصّصة' }).click();
        await page.getByRole('button', { name: 'تعريف حقل' }).click();
        await page.getByLabel('المفتاح').fill('tier');
        await page.getByLabel('التسمية').fill('مستوى الحساب');
        const defPanel = page.locator('.card').filter({ has: page.getByLabel('المفتاح') });
        await defPanel.getByRole('button', { name: 'حفظ' }).click();
        await expect(page.locator('body')).toContainText('تم تعريف الحقل المخصّص');
        // اضبط القيمة — بطاقة «ضبط القيم» تظهر بعد وجود تعريف، وزرّها «حفظ» بجوار الحقل
        const setCard = page.locator('.card').filter({ hasText: 'ضبط القيم' });
        await setCard.getByRole('textbox').first().fill('ذهبي');
        await setCard.getByRole('button', { name: 'حفظ' }).first().click();
        await expect(page.locator('body')).toContainText('تم حفظ القيمة');
    });

    test('35- إضافة جهة اتصال من الواجهة', async ({ page }) => {
        await page.goto('/app/clients/1');
        // مُهاجَر من Alpine: تبويب + لوح AddPanel «إضافة جهة اتصال»
        await page.getByRole('tab', { name: 'جهات الاتصال' }).click();
        await page.getByRole('button', { name: 'إضافة جهة اتصال' }).click();
        await page.getByLabel('الاسم').fill('سارة أحمد');
        await page.getByLabel('المسمّى').fill('مديرة تسويق');
        const panel = page.locator('.card').filter({ has: page.getByLabel('الاسم') });
        await panel.getByRole('button', { name: 'حفظ' }).click();
        // النتيجة الفعلية: بطاقة جهة الاتصال الجديدة تظهر في التبويب
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
        // «مركز المعاينة» ضمن مجموعة «الإدارة» الثانوية المطويّة افتراضيًّا — نفتحها أولًا
        await page.click('button.ih-nav__group--toggle:has-text("الإدارة")');
        await page.click('a:has-text("مركز المعاينة")');
        await expect(page).toHaveURL(/\/app\/preview/);
        await expect(page.locator('body')).toContainText('Preview Center');
    });
});
