import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('العملاء', () => {
    test.beforeEach(async ({ page }) => login(page));

    test('7- لوحة التحكم تعرض إحصاءات حقيقية', async ({ page }) => {
        await page.goto('/app');
        await expect(page.locator('body')).toContainText('العملاء');
        await expect(page.locator('body')).toContainText('نايك السعودية');
    });
    test('8- قائمة العملاء تعرض بيانات قاعدة البيانات', async ({ page }) => {
        await page.goto('/app/clients');
        await expect(page.locator('body')).toContainText('نايك السعودية');
        await expect(page.locator('body')).toContainText('stc');
        await expect(page.locator('body')).toContainText('CL-1-0001');
    });
    test('9- البحث بالاسم يصفّي النتائج', async ({ page }) => {
        // بحث حيّ (React/Inertia) — لا زر «بحث»؛ الإدخال يُرشّح بعد مهلة قصيرة.
        await page.goto('/app/clients');
        await page.fill('.ih-search input', 'نايك');
        await expect(page.locator('body')).toContainText('نايك السعودية');
        await expect(page.locator('body')).not.toContainText('stc');
    });
    test('10- تصفية بالحالة (lead)', async ({ page }) => {
        // فلتر الحالة أول قائمة منسدلة في شريط الفلاتر (React) — بلا سمة name.
        await page.goto('/app/clients');
        await page.locator('.ih-filterbar select').first().selectOption('lead');
        await expect(page.locator('body')).toContainText('نون');
        await expect(page.locator('body')).not.toContainText('نايك السعودية');
    });
    test('11- زر عميل جديد يفتح النافذة', async ({ page }) => {
        await page.goto('/app/clients');
        await page.getByRole('button', { name: 'عميل جديد' }).first().click();
        await expect(page.locator('.modal')).toBeVisible();
        await expect(page.locator('.modal')).toContainText('اسم العميل');
    });
    test('12- إنشاء عميل جديد عبر النافذة', async ({ page }) => {
        await page.goto('/app/clients');
        await page.getByRole('button', { name: 'عميل جديد' }).first().click();
        await expect(page.locator('.modal')).toBeVisible();
        // أول حقل في النافذة هو «اسم العميل» (autoFocus) — لا سمة name في React.
        await page.locator('.modal input').first().fill('عميل بلاي رايت');
        await page.locator('.modal').getByRole('button', { name: 'إنشاء العميل' }).click();
        await expect(page.locator('body')).toContainText('عميل بلاي رايت');
    });
    test('13- زر إلغاء يغلق النافذة', async ({ page }) => {
        await page.goto('/app/clients');
        await page.getByRole('button', { name: 'عميل جديد' }).first().click();
        await expect(page.locator('.modal')).toBeVisible();
        await page.locator('.modal').getByRole('button', { name: 'إلغاء' }).click();
        await expect(page.locator('.modal')).toBeHidden();
    });
    test('14- فتح ملف عميل يعرض رقمه وحالته', async ({ page }) => {
        await page.goto('/app/clients');
        await page.click('a:has-text("نايك السعودية")');
        await expect(page.locator('body')).toContainText('CL-1-0001');
        await expect(page.locator('body')).toContainText('ملف العميل');
    });
    test('15- تبويبات ملف العميل تعمل (Alpine)', async ({ page }) => {
        await page.goto('/app/clients/1');
        await page.click('button:has-text("جهات الاتصال")');
        await expect(page.locator('body')).toContainText('لا جهات اتصال');
        await page.click('button:has-text("المستندات")');
        await expect(page.locator('body')).toContainText('لا مستندات');
    });
    test('16- زر الأرشفة ظاهر للمدير', async ({ page }) => {
        await page.goto('/app/clients/1');
        await expect(page.locator('button:has-text("أرشفة")')).toBeVisible();
    });
});
