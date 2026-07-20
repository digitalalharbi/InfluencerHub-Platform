import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('المصادقة', () => {
    test('1- زائر يُحوَّل إلى صفحة الدخول', async ({ page }) => {
        await page.goto('/app');
        await expect(page).toHaveURL(/\/login/);
    });
    test('2- صفحة الدخول بالعربية وRTL', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('body')).toContainText('البريد الإلكتروني');
        await expect(page.locator('button[type="submit"]')).toContainText('دخول');
    });
    test('3- بيانات خاطئة تُرفض برسالة', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'admin@a.test');
        await page.fill('input[name="password"]', 'wrong');
        await page.click('button[type="submit"]');
        await expect(page.locator('body')).toContainText('غير صحيحة');
    });
    test('4- دخول ناجح يصل للوحة التحكم', async ({ page }) => {
        await login(page);
        await expect(page.locator('body')).toContainText('لوحة التحكم');
    });
    test('5- تسجيل الخروج يعيد لصفحة الدخول', async ({ page }) => {
        await login(page);
        await page.click('button:has-text("تسجيل الخروج")');
        await expect(page).toHaveURL(/\/login/);
    });
    test('6- بعد الخروج لا وصول للتطبيق', async ({ page }) => {
        await login(page);
        await page.click('button:has-text("تسجيل الخروج")');
        await page.goto('/app/clients');
        await expect(page).toHaveURL(/\/login/);
    });
});
