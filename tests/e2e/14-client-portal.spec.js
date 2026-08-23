import { test, expect } from '@playwright/test';

const PW = process.env.E2E_PASSWORD;

async function clientLogin(page) {
    // بوابة العميل المخصّصة (‎/client/login‎) — لا بوابة الوكالة. عضو العميل
    // (client_admin) لا يملك دور وكالة، فبوابة الوكالة ترفضه بحقّ.
    await page.goto('/client/login');
    await page.fill('input[name="email"]', 'client@a.test');
    await page.fill('input[name="password"]', PW);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/client/dashboard');
    await expect(page.locator('body')).toContainText('نايك السعودية');
}

test.describe('بوابة العميل — Phase 5', () => {
    test('64- دخول العميل ولوحة التحكم بأعداد فعلية', async ({ page }) => {
        await clientLogin(page);
        await expect(page.locator('aside')).toContainText('العلامات');
        await expect(page.locator('aside')).toContainText('الفريق');
    });

    test('65- سير عمل العلامة: تعديل مسودة ثم إرسال للمراجعة', async ({ page }) => {
        // React/Inertia: صفحة تفصيل العلامة بزر «إرسال للمراجعة» (لا form action).
        await clientLogin(page);
        await page.goto('/client/brands');
        await expect(page.locator('body')).toContainText('Nike Air');
        // البطاقة/الصف بالكامل رابط (لا رابط «فتح» فرعي).
        await page.click('a:has-text("Nike Air")');
        await expect(page.locator('body')).toContainText('مسودة');
        page.on('dialog', d => d.accept());
        await page.getByRole('button', { name: 'إرسال للمراجعة' }).click();
        // التأكيد الفعلي: رسالة النجاح + الحالة تصبح «مُرسل».
        await expect(page.locator('body')).toContainText('أُرسلت العلامة للمراجعة');
    });

    test('66- إدارة الفريق: دعوة عضو تُظهر رمزًا مرة واحدة', async ({ page }) => {
        // React: زر «دعوة عضو» يفتح نافذة؛ حقل البريد بلا سمة name؛ الرمز يُعرض مرة.
        await clientLogin(page);
        await page.goto('/client/team');
        await expect(page.locator('body')).toContainText('عميل نايك');
        await page.getByRole('button', { name: 'دعوة عضو' }).first().click();
        await page.locator('.modal input[type="email"], .modal input').first().fill('newmember@nike.test');
        await page.locator('.modal').getByRole('button', { name: 'إرسال الدعوة' }).click();
        await expect(page.locator('body')).toContainText('رمز الدعوة');
        await expect(page.locator('body')).toContainText('newmember@nike.test');
    });

    test('67- الإعدادات: عرض الجلسات وتفضيلات الإشعارات', async ({ page }) => {
        // ‎/client/settings‎ يُحوّل إلى ‎/client/account#settings‎ (مكوّن AccountSecurity).
        await clientLogin(page);
        await page.goto('/client/settings');
        await expect(page.locator('body')).toContainText('تفضيلات الإشعارات');
        await expect(page.locator('body')).toContainText('الجلسات النشطة');
        // النص الفعلي «التحقّق بخطوتين» (كان الاختبار يطلب «المصادقة الثنائية»).
        await expect(page.locator('body')).toContainText('التحقّق بخطوتين');
    });

    test('68- ملف المنشأة يُعرض في حساب المنشأة', async ({ page }) => {
        // ‎/client/profile‎ يُحوّل إلى ‎/client/account#profile‎ (العنوان: حساب المنشأة).
        await clientLogin(page);
        await page.goto('/client/profile');
        await expect(page.locator('body')).toContainText('حساب المنشأة');
        await expect(page.locator('body')).toContainText('بيانات المنشأة');
    });
});
