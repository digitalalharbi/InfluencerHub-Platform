// @ts-check
import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';

/**
 * N1 — قبول حيّ لميزة «ترشيح المؤثرين» (influencer_nomination) عبر الواجهة الحقيقية.
 *
 * يثبت من المتصفّح فعليًّا: مالك المنصّة يطفئ الميزة لمستأجر → القائمة تختفي، والوصول
 * المباشر/التصدير = 403، والبيانات المُخزَّنة تبقى؛ إعادة التفعيل تُعيد نفس السجلّ. عزل
 * المستأجر (A مطفأ، B يعمل) وعزل الصلاحية (دور المالية يُمنع رغم أن الميزة مفعّلة).
 *
 * أدلّة بصريّة تُحفظ في tests/e2e/evidence/ (لقطات لكل حالة).
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

async function loginOwner(page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'owner@platform.test');
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  // المالك (is_system_admin) يهبط على /app بعد الدخول؛ مساحة /platform تُفتح صراحةً بالتنقّل.
  await page.waitForLoadState('networkidle');
}

/** يفتح مستأجر «وكالة ألف» في مساحة المالك ويرجع رابط التفصيل. */
async function ownerOpenTenantA(page) {
  await page.goto('/platform/tenants');
  await page.click('a:has-text("وكالة ألف")');
  await page.waitForURL('**/platform/tenants/**');
  await expect(page.locator('[data-testid="feature-nomination"]')).toBeVisible();
}

/** يبدّل إتاحة ترشيح بوّابة الوكالة لمستأجر مفتوح، ثم ينتظر ثبات الحالة. */
async function toggleAgencyNomination(page) {
  await page.click('[data-testid="toggle-nomination-agency"]');
  await page.waitForLoadState('networkidle');
}

test.describe('N1 — قبول حيّ لترشيح المؤثرين', () => {
  test('98- إطفاء المنصّة يُخفي/يمنع (403) ويحفظ البيانات؛ عزل مستأجر + صلاحية', async ({ page, browser }) => {
    // سياق مالك منفصل (جلسة مستقلّة عن الوكالة)
    const ownerCtx = await browser.newContext();
    const owner = await ownerCtx.newPage();

    let shortlistUrl = '';

    await test.step('الأساس: الميزة مفعّلة — الوكالة ترى الترشيح والمرشّح المُخزَّن', async () => {
      await loginAgency(page, 'admin@a.test');
      // مركز الترشيحات يعمل (200) ويسرد الحملة مع رابط قائمتها
      await page.goto('/app/shortlisting');
      await expect(page.locator('body')).toContainText('حملة الصيف');
      const link = page.locator('a[href*="/campaigns/"][href*="/shortlist"]').first();
      shortlistUrl = new URL(await link.getAttribute('href'), page.url()).pathname;
      // صفحة القائمة تعرض المرشّح المزروع (نورة القحطاني)
      await page.goto(shortlistUrl);
      await expect(page.locator('body')).toContainText('نورة القحطاني');
      await page.screenshot({ path: `${EV}/n1-01-agency-shortlist-ON.png`, fullPage: true });
      // رابط «الترشيحات» ظاهر في القائمة (ضمن مجموعة «المزيد» القابلة للطي)
      const more = page.getByRole('button', { name: 'المزيد' });
      if (await more.count()) await more.first().click();
      await expect(page.getByRole('link', { name: 'الترشيحات', exact: true })).toBeVisible();
    });

    await test.step('مالك المنصّة يطفئ الميزة لبوّابة وكالة المستأجر A', async () => {
      await loginOwner(owner);
      await ownerOpenTenantA(owner);
      await owner.screenshot({ path: `${EV}/n1-02-platform-feature-control.png`, fullPage: true });
      await toggleAgencyNomination(owner); // agency: مُفعّلة → أوقِف
      await expect(owner.locator('[data-testid="toggle-nomination-agency"]')).toContainText('موقوفة');
      await owner.screenshot({ path: `${EV}/n1-03-platform-toggled-OFF.png`, fullPage: true });
    });

    await test.step('OFF: القائمة تُخفى والوصول المباشر/التصدير = 403', async () => {
      // مسار مباشر
      const r1 = await page.goto(shortlistUrl);
      expect(r1.status()).toBe(403);
      await page.screenshot({ path: `${EV}/n1-04-agency-direct-403.png`, fullPage: true });
      // مركز الترشيحات
      const r2 = await page.goto('/app/shortlisting');
      expect(r2.status()).toBe(403);
      // تصدير
      const r3 = await page.goto(`${shortlistUrl}/export`);
      expect(r3.status()).toBe(403);
      // القائمة اختفت من التنقّل
      await page.goto('/app');
      const more = page.getByRole('button', { name: 'المزيد' });
      if (await more.count()) await more.first().click();
      await expect(page.getByRole('link', { name: 'الترشيحات', exact: true })).toHaveCount(0);
      await page.screenshot({ path: `${EV}/n1-05-agency-nav-hidden.png`, fullPage: true });
    });

    await test.step('عزل المستأجر: A مطفأ لا يؤثّر على B', async () => {
      const bCtx = await browser.newContext();
      const b = await bCtx.newPage();
      await loginAgency(b, 'admin@b.test');
      const rb = await b.goto('/app/shortlisting');
      expect(rb.status()).toBe(200); // المستأجر B ما زال مفعّلًا
      await bCtx.close();
    });

    await test.step('عزل الصلاحية: دور المالية يُمنع رغم أن الميزة مفعّلة', async () => {
      // نعيد التفعيل أولًا حتى يكون المنع بسبب الدور لا الإطفاء
      await toggleAgencyNomination(owner); // agency: موقوفة → فعّل
      await expect(owner.locator('[data-testid="toggle-nomination-agency"]')).toContainText('مُفعّلة');
      const fCtx = await browser.newContext();
      const f = await fCtx.newPage();
      await loginAgency(f, 'finance@a.test');
      const rf = await f.goto('/app/shortlisting');
      expect(rf.status()).toBe(403); // finance خارج صلاحية العرض
      await fCtx.close();
    });

    await test.step('إعادة التفعيل: نفس المرشّح المُخزَّن يعود للوكالة', async () => {
      const r = await page.goto(shortlistUrl);
      expect(r.status()).toBe(200);
      await expect(page.locator('body')).toContainText('نورة القحطاني'); // بيانات محفوظة
      await page.screenshot({ path: `${EV}/n1-06-agency-reenabled-data-preserved.png`, fullPage: true });
    });

    await ownerCtx.close();
  });
});
