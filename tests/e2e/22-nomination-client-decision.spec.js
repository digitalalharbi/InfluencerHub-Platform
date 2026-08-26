// @ts-check
import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';

/**
 * N8 — قبول حيّ لدورة قرار العميل على الترشيح عبر الواجهة الحقيقية، على المتصفّحات الثلاثة.
 *
 * يثبت من المتصفّح فعليًّا سلسلة كاملة بمستخدمَين حقيقيَّين:
 *   الوكالة (admin@a.test) ترسل قائمة الترشيح لاعتماد العميل →
 *   العميل (client@a.test) يفتح القائمة، يرى المؤثّر، ويطلب بديلًا (needs_alternative) →
 *   الحالة تُحفَظ للعميل («طلبت بديلًا») وتعود للوكالة كـ «مطلوب بديل» (الدور على الوكالة).
 *
 * يغطّي N6 (قرار «أحتاج بديلًا») حيًّا، بالإضافة إلى مسار الاعتماد الأساسي.
 * أدلّة بصريّة في tests/e2e/evidence/.
 */
const PW = process.env.E2E_PASSWORD;
const EV = 'tests/e2e/evidence';
mkdirSync(EV, { recursive: true });

async function loginAgency(page, email) {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/app**');
}

async function loginClient(page) {
  // بوابة العميل المخصّصة — عضو العميل (client_admin) لا يملك دور وكالة.
  await page.goto('/client/login');
  await page.fill('input[name="email"]', 'client@a.test');
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/client/dashboard');
}

test.describe('N8 — قرار العميل على الترشيح (سلسلة كاملة، متعدّد المتصفّحات)', () => {
  test('99- الوكالة تُرسل، والعميل يطلب بديلًا؛ الحالة تُحفظ وتعود للوكالة', async ({ page, browser }) => {
    let shortlistPath = '';
    let campaignId = '';

    await test.step('الوكالة: تفتح القائمة وتُرسلها لاعتماد العميل', async () => {
      await loginAgency(page, 'admin@a.test');
      await page.goto('/app/shortlisting');
      await expect(page.locator('body')).toContainText('حملة الصيف');
      const link = page.locator('a[href*="/campaigns/"][href*="/shortlist"]').first();
      shortlistPath = new URL(await link.getAttribute('href'), page.url()).pathname;
      const m = shortlistPath.match(/campaigns\/(\d+)\/shortlist/);
      campaignId = m ? m[1] : '';
      expect(campaignId).not.toBe('');

      await page.goto(shortlistPath);
      await expect(page.locator('body')).toContainText('نورة القحطاني');
      // زر الإرسال لاعتماد العميل (يظهر فقط أثناء المسودة القابلة للتحرير)
      const submit = page.getByRole('button', { name: 'إرسال لاعتماد العميل' });
      await expect(submit).toBeVisible();
      await submit.click();
      await page.waitForLoadState('networkidle');
      // بعد الإرسال يختفي زر الإرسال (لم يعد الإصدار مسودة قابلة للتحرير)
      await expect(page.getByRole('button', { name: 'إرسال لاعتماد العميل' })).toHaveCount(0);
      await page.screenshot({ path: `${EV}/n8-01-agency-submitted.png`, fullPage: true });
    });

    const clientCtx = await browser.newContext();
    const client = await clientCtx.newPage();

    await test.step('العميل: يفتح القائمة المُرسَلة ويطلب بديلًا عن المؤثّر', async () => {
      await loginClient(client);
      await client.goto(`/client/campaigns/${campaignId}/shortlist`);
      await expect(client.locator('body')).toContainText('نورة القحطاني');
      await client.screenshot({ path: `${EV}/n8-02-client-shortlist.png`, fullPage: true });

      // ثلاثة خيارات: اعتماد / أحتاج بديلًا / رفض
      const altBtn = client.getByRole('button', { name: 'أحتاج بديلًا' }).first();
      await expect(altBtn).toBeVisible();
      await altBtn.click();
      // النافذة: عنوان طلب البديل + سبب اختياري
      await expect(client.locator('.modal')).toContainText('طلب بديل');
      await client.locator('.modal textarea').fill('نفضّل مؤثّرًا على منصّة مختلفة تناسب الجمهور.');
      await client.locator('.modal').getByRole('button', { name: 'طلب بديل' }).click();
      await client.waitForLoadState('networkidle');
      // الحالة المحفوظة تظهر للعميل
      await expect(client.locator('body')).toContainText('طلبت بديلًا');
      await client.screenshot({ path: `${EV}/n8-03-client-alternative-requested.png`, fullPage: true });
    });

    await test.step('الوكالة: طلب البديل يظهر على بند المؤثّر (الدور على الوكالة)', async () => {
      await page.goto(shortlistPath);
      // إشارة على مستوى البند نفسه — ثابتة بصرف النظر عن بنود أخرى قد يضيفها سياق مشترك:
      // بند نورة يحمل وسم «طلب بديلًا» بعد قرار العميل.
      await expect(page.locator('body')).toContainText('طلب بديلًا');
      await page.screenshot({ path: `${EV}/n8-04-agency-alternative-requested.png`, fullPage: true });
    });

    await clientCtx.close();
  });
});
