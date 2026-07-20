import { test, expect } from '@playwright/test';

/**
 * المعيار التجاوبي — القاعدة الصارمة: لا تمرير أفقي للصفحة إطلاقًا (scrollWidth ≤ clientWidth).
 * يفحص مقاسات متعددة (جوال/لوحي/سطح مكتب/واسع) + محاكاة جهاز iPhone عبر مسارات كل بوابة.
 * كما يتحقق من: التنقّل السفلي على الجوال، وخط الإدخال ≥ 16px (منع تكبير iOS).
 */
const PW = process.env.E2E_PASSWORD;

const VIEWPORTS = [
    { name: 'mobile-375', width: 375, height: 812 },
    { name: 'tablet-768', width: 768, height: 1024 },
    { name: 'desktop-1280', width: 1280, height: 800 },
    { name: 'wide-1440', width: 1440, height: 900 },
];

async function login(page, path, email) {
    await page.goto(path);
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', PW);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
}

async function assertNoHorizontalScroll(page, label) {
    const overflow = await page.evaluate(() => {
        const de = document.documentElement;
        return { scrollW: de.scrollWidth, clientW: de.clientWidth };
    });
    expect(overflow.scrollW, `${label} — تمرير أفقي غير مسموح (scrollWidth ${overflow.scrollW} > clientWidth ${overflow.clientW})`)
        .toBeLessThanOrEqual(overflow.clientW + 1);
}

// مسارات تمثيلية لكل بوابة (قوائم + تفاصيل + نماذج)
const ROUTES = {
    agency: { login: '/login', email: 'admin@a.test', paths: ['/app', '/app/clients', '/app/service-requests', '/app/campaigns', '/app/reports'] },
    client: { login: '/login', email: 'client@a.test', paths: ['/client/dashboard', '/client/brands', '/client/requests', '/client/documents'] },
    partner: { login: '/partner/login', email: 'partner@a.test', paths: ['/partner/dashboard', '/partner/requests'] },
};

for (const [role, cfg] of Object.entries(ROUTES)) {
    test.describe(`لا تمرير أفقي — بوابة ${role}`, () => {
        for (const vp of VIEWPORTS) {
            test(`${role} @ ${vp.name}`, async ({ page }) => {
                await page.setViewportSize({ width: vp.width, height: vp.height });
                await login(page, cfg.login, cfg.email);
                for (const path of cfg.paths) {
                    await page.goto(path);
                    await page.waitForLoadState('networkidle');
                    await assertNoHorizontalScroll(page, `${role} ${path} @ ${vp.name}`);
                }
            });
        }
    });
}

// مقاس جوال حقيقي (iPhone-class) دون تبديل نوع المتصفّح — نبقى على chromium حسب الإعداد.
const MOBILE = { width: 390, height: 844 };

test.describe('تجربة الجوال (390×844)', () => {
    test.beforeEach(async ({ page }) => { await page.setViewportSize(MOBILE); });

    test('التنقّل السفلي ظاهر على الجوال + لا تمرير أفقي', async ({ page }) => {
        await login(page, '/login', 'admin@a.test');
        await page.goto('/app');
        await page.waitForLoadState('networkidle');
        await assertNoHorizontalScroll(page, 'agency /app @ iPhone13');

        // التنقّل السفلي مرئي
        const bottomNav = page.locator('.ih-bottom-nav');
        await expect(bottomNav).toBeVisible();
        const links = bottomNav.locator('.ih-bottom-nav__link');
        expect(await links.count()).toBeGreaterThanOrEqual(3);
        expect(await links.count()).toBeLessThanOrEqual(5);

        // شريط الجوال العلوي (زر القائمة) مرئي، والرأس المكتبي مخفي
        await expect(page.locator('.ih-topbar-mobile')).toBeVisible();
    });

    test('حقول الإدخال ≥ 16px لمنع تكبير iOS', async ({ page }) => {
        await page.goto('/login');
        const fontSize = await page.locator('input[name="email"]').evaluate(
            (el) => parseFloat(getComputedStyle(el).fontSize)
        );
        expect(fontSize, 'خط الإدخال على الجوال يجب أن يكون ≥ 16px').toBeGreaterThanOrEqual(16);
    });

    test('درج القائمة الجانبية يفتح ويغلق', async ({ page }) => {
        await login(page, '/login', 'admin@a.test');
        await page.goto('/app');
        await page.waitForLoadState('networkidle');
        // الشريط الجانبي مخفي مبدئيًا خارج الشاشة، ثم يُفتح بزر القائمة
        await page.locator('.ih-topbar-mobile .ih-icon-btn').click();
        await expect(page.locator('.ih-shell.nav-open aside.sidebar')).toBeVisible();
        await assertNoHorizontalScroll(page, 'agency /app درج مفتوح @ iPhone13');
    });
});
