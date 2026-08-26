// @ts-check
import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';

/**
 * N6 — قبول حيّ لتحويل المعتمَدين للتنفيذ، على المتصفّحات الثلاثة.
 *
 * السلسلة الكاملة بمستخدمَين: الوكالة (admin@a.test) تُرسل قائمة الترشيح → العميل
 * (client@a.test) يعتمد كل المرشّحين → الوكالة تضغط «تحويل المعتمَدين للتنفيذ» فيُنشَأ
 * تعاون تنفيذ لكل معتمَد (عبر خدمة التعاون القانونية)، والحالة تعود «حُوِّل … للتنفيذ».
 *
 * يغطّي N6 conversion حيًّا. أدلّة بصريّة في tests/e2e/evidence/.
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
  await page.goto('/client/login');
  await page.fill('input[name="email"]', 'client@a.test');
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/client/dashboard');
}

test.describe('N6 — تحويل المعتمَدين للتنفيذ (سلسلة كاملة، متعدّد المتصفّحات)', () => {
  test('100- الوكالة تُرسل، العميل يعتمد الكل، والوكالة تُحوّل للتنفيذ', async ({ page, browser }) => {
    test.setTimeout(60000); // سلسلة بمستخدمَين + عدّة إعادات تحميل
    let shortlistPath = '';
    let campaignId = '';

    await test.step('الوكالة: تفتح القائمة وتُرسلها لاعتماد العميل', async () => {
      await loginAgency(page, 'admin@a.test');
      await page.goto('/app/shortlisting');
      const link = page.locator('a[href*="/campaigns/"][href*="/shortlist"]').first();
      shortlistPath = new URL(await link.getAttribute('href'), page.url()).pathname;
      campaignId = (shortlistPath.match(/campaigns\/(\d+)\/shortlist/) || [])[1] || '';
      expect(campaignId).not.toBe('');
      await page.goto(shortlistPath);
      // في سلسلة CI قد يكون اختبار سابق حسم الإصدار — نبدأ نظيفًا بإصدار جديد (مسودة).
      const revise = page.getByRole('button', { name: 'إنشاء إصدار جديد' });
      if (await revise.count()) {
        await revise.click();
        await expect(page.getByRole('button', { name: 'إرسال لاعتماد العميل' })).toBeVisible();
      }
      const submit = page.getByRole('button', { name: 'إرسال لاعتماد العميل' });
      await expect(submit).toBeVisible();
      await submit.click();
      await page.waitForLoadState('networkidle');
    });

    const clientCtx = await browser.newContext();
    const client = await clientCtx.newPage();

    await test.step('العميل: يعتمد كل المرشّحين (لا يبقى معلّق)', async () => {
      await loginClient(client);
      await client.goto(`/client/campaigns/${campaignId}/shortlist`);
      await expect(client.locator('body')).toContainText('نورة القحطاني');
      // اعتماد كل بند معلّق حتى لا يبقى معلّق يمنع التحويل. ننتظر تناقص العدّ بعد كل
      // قرار (توثّق ثابت) بدل networkidle — زرّ الاعتماد يُعطَّل لحظة الإرسال.
      const approveBtn = client.getByRole('button', { name: 'اعتماد', exact: true });
      let remaining = await approveBtn.count();
      expect(remaining).toBeGreaterThan(0);
      while (remaining > 0) {
        await approveBtn.first().click();
        await expect(approveBtn).toHaveCount(remaining - 1);
        remaining--;
      }
      await client.screenshot({ path: `${EV}/n6-01-client-approved-all.png`, fullPage: true });
    });

    await test.step('الوكالة: «تحويل المعتمَدين للتنفيذ» يُنشئ التعاونات', async () => {
      await page.goto(shortlistPath);
      const convert = page.getByRole('button', { name: /تحويل المعتمَدين للتنفيذ/ });
      await expect(convert).toBeVisible();
      await convert.click();
      await page.waitForLoadState('networkidle');
      // بعد التحويل: الحالة تُظهر «حُوِّل … للتنفيذ» (مدفوعة بعدّ التعاونات الفعلي عبر الأثر الرجعي)
      await expect(page.locator('body')).toContainText('حُوِّل');
      // Idempotency بصريًّا: زرّ التحويل لم يعُد ظاهرًا (لا معتمَد غير محوّل)
      await expect(page.getByRole('button', { name: /تحويل المعتمَدين للتنفيذ/ })).toHaveCount(0);
      await page.screenshot({ path: `${EV}/n6-02-agency-converted.png`, fullPage: true });
    });

    await clientCtx.close();
  });
});
