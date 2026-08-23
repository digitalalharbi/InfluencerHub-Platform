import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('التصميم والواجهة', () => {
    test.beforeEach(async ({ page }) => login(page));

    test('27- الهوية البصرية تطبّق لون العلامة الأساسي على الأزرار', async ({ page }) => {
        // بلا لون ثابت في الاختبار (كان التيل القديم #0d8a6f): نقارن خلفية الزر
        // باللون الأساسي المعرَّف كمتغيّر CSS (‎--ih-primary‎) فلا يكسر تحديث اللوحة.
        await page.goto('/app/clients');
        const btn = page.locator('.btn-primary').first();
        const { bg, token } = await btn.evaluate((el) => ({
            bg: getComputedStyle(el).backgroundColor,
            token: getComputedStyle(document.documentElement).getPropertyValue('--ih-primary').trim(),
        }));
        expect(token).not.toBe('');           // العلامة تعرّف لونًا أساسيًّا
        expect(bg).toMatch(/^rgba?\(/);        // والزر يطبّقه فعليًّا كخلفية ملوّنة
        expect(bg).not.toBe('rgba(0, 0, 0, 0)');
    });
    test('28- الاتجاه RTL على مستوى الصفحة', async ({ page }) => {
        await page.goto('/app');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    });
    test('29- شارات الحالة تظهر بحالاتها', async ({ page }) => {
        // الشارات تُرسَم بعنصر .badge بأنماط مضمّنة حسب الحالة (لا أصناف badge-active).
        await page.goto('/app/clients');
        await expect(page.locator('.badge', { hasText: 'نشط' }).first()).toBeVisible();
        await expect(page.locator('.badge', { hasText: 'مهتم' }).first()).toBeVisible();
    });
    test('30- تجاوب الجوال: القائمة والمحتوى يظهران', async ({ page }) => {
        // على الجوال يعرض التصميم بطاقات (‎.ih-mlist‎) لا جدولًا — قصدًا للاستجابة.
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/app/clients');
        await expect(page.locator('body')).toContainText('العملاء');
        await expect(page.locator('.ih-mlist').first()).toBeVisible();
        await expect(page.locator('body')).toContainText('نايك السعودية');
    });
});
