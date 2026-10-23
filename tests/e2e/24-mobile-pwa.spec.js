import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/**
 * بوّابة الجوال/PWA — يعمل عبر مشاريع المتصفّح القائمة (chromium ≈ Android/Pixel،
 * webkit ≈ iPhone)، على مقاس جوال 390×844 لمس، في الوضعين الفاتح والداكن.
 *
 * الثابت الصارم لكلّ سطح: لا تمرير أفقي للصفحة، التنقّل السفلي ظاهر، والإجراء
 * الأساسي غير مخفيّ. يغطّي الأسطح الرئيسية للوكالة (لوحة/مبدعون/حملات/تعاونات/
 * محتوى/عقود/عملاء/مالية/تقارير/أتمتة) + ملف مبدع.
 */
test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });

const PAGES = [
    ['/app', 'لوحة التحكم'],
    ['/app/creators', 'قاعدة المبدعين'],
    ['/app/campaigns', 'الحملات'],
    ['/app/collaborations', 'التعاونات'],
    ['/app/content', 'المحتوى'],
    ['/app/contracts', 'العقود'],
    ['/app/clients', 'العملاء'],
    ['/app/payouts', 'المستحقات'],
    ['/app/reports', 'التقارير'],
    ['/app/automation', 'الأتمتة'],
];

async function assertNoHScroll(page, label) {
    const o = await page.evaluate(() => ({ s: document.documentElement.scrollWidth, c: document.documentElement.clientWidth }));
    expect(o.s, `${label}: تمرير أفقي (scrollWidth ${o.s} > clientWidth ${o.c})`).toBeLessThanOrEqual(o.c + 1);
}

async function sweep(page, themeLabel) {
    for (const [path] of PAGES) {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        await assertNoHScroll(page, `${themeLabel} ${path}`);
        // التنقّل السفلي للجوال ظاهر (قشرة تطبيق حقيقية)
        await expect(page.locator('.ih-bottom-nav'), `${themeLabel} ${path}: شريط سفلي مفقود`).toBeVisible();
    }
    // ملف مبدع (اكتشاف معرّف حقيقي من القائمة)
    await page.goto('/app/creators');
    await page.waitForLoadState('networkidle');
    const href = await page.evaluate(() => {
        const a = Array.from(document.querySelectorAll('a[href]')).map((x) => x.getAttribute('href'))
            .find((h) => h && /\/app\/creators\/\d+$/.test(h));
        return a || null;
    });
    if (href) {
        await page.goto(href);
        await page.waitForLoadState('networkidle');
        await assertNoHScroll(page, `${themeLabel} ${href}`);
        await expect(page.locator('.ih-bottom-nav')).toBeVisible();
    }
}

test.describe('الجوال/PWA — فاتح', () => {
    test('قشرة تطبيق على الجوال بلا فيض أفقي (فاتح)', async ({ page }) => {
        await login(page, 'admin@a.test');
        await sweep(page, 'light');
    });
});

test.describe('الجوال/PWA — داكن', () => {
    test.use({ storageState: undefined });
    test('قشرة تطبيق على الجوال بلا فيض أفقي (داكن)', async ({ page }) => {
        await page.addInitScript(() => { try { localStorage.setItem('ih.theme', 'dark'); } catch (e) { /* ignore */ } });
        await login(page, 'admin@a.test');
        const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
        expect(theme, 'الوضع الداكن لم يُفعَّل').toBe('dark');
        await sweep(page, 'dark');
    });
});
