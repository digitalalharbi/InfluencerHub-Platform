// @ts-check
import { test, expect } from '@playwright/test';

/**
 * فحص دخان مُصادَق على الإنتاج — مساحة عمل الحملة (P1).
 * يسجّل الدخول بحساب بيئة العرض المُصرَّح به (يُمرَّر عبر البيئة من الـworkflow،
 * لا يُطبع أبدًا)، يفتح حملة حقيقية، ويتحقّق من التبويبات الستّة، فصل المالية،
 * الروابط العميقة، الإجراء التالي، التذييل العام، وسلامة الجوال.
 *
 * يفشل عند: 500 / 403 غير متوقّع / 404 غير متوقّع / خطأ JS غير ملتقَط /
 * خطأ console غير متوقّع.
 */

const EMAIL = process.env.SHOWCASE_EMAIL || 'showcase_admin@showcase.test';
const PASSWORD = process.env.SHOWCASE_PASSWORD || '';

const TABS = ['المخرجات', 'التعاونات', 'المحتوى', 'العقود', 'التحصيل', 'المستحقات'];

// أخطاء console حميدة معروفة لا تُفشِل الفحص (ضجيج متصفّح/طرف ثالث، لا خطأ تطبيق).
const BENIGN_CONSOLE = [/ResizeObserver loop/i, /favicon/i, /Failed to load resource.*status of 404 .*(favicon|\.map)/i];

function watch(page) {
  const errors = [];
  const badResponses = [];
  page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
  page.on('console', (m) => {
    if (m.type() !== 'error') return;
    const t = m.text();
    if (BENIGN_CONSOLE.some((re) => re.test(t))) return;
    errors.push(`console.error: ${t}`);
  });
  page.on('response', (r) => {
    const s = r.status();
    const url = r.url();
    // أخطاء الخادم على مسارات التطبيق فقط (لا نحاسب أصولًا/طرفًا ثالثًا)
    if (!/\/(app|contracts|invoices|payouts|login)\b/.test(url)) return;
    if (s >= 500 || s === 403 || (s === 404 && /\/app\//.test(url))) badResponses.push(`${s} ${url}`);
  });
  return { errors, badResponses };
}

async function login(page) {
  await page.goto('/login', { waitUntil: 'networkidle' });
  await page.fill('input[name=email]', EMAIL);
  await page.fill('input[name=password]', PASSWORD);
  await Promise.all([
    page.waitForURL(/\/app(\/|$)/, { timeout: 30_000 }),
    page.locator('form button[type=submit], form button').first().click(),
  ]);
  // يجب أن نبقى مُصادَقين — لا نعود إلى /login
  expect(page.url(), 'login must land on /app, not bounce to /login').toMatch(/\/app(\/|$)/);
}

test('authenticated campaign workspace smoke on production', async ({ page }, testInfo) => {
  expect(PASSWORD, 'SHOWCASE_PASSWORD must be provided by the workflow').not.toEqual('');
  const { errors, badResponses } = watch(page);
  const isMobile = testInfo.project.name === 'mobile';

  // 1) دخول مُصادَق
  await login(page);

  // 2) اكتشاف حملة حقيقية من روابط القائمة في DOM (لا اعتماد على خصائص Inertia:
  //    فبطاقات الحملات روابط <a href="/app/campaigns/{id}"> حقيقية).
  await page.goto('/app/campaigns', { waitUntil: 'networkidle' });
  await page.waitForSelector('a[href*="/campaigns/"]', { timeout: 20_000 });
  const hrefs = await page.locator('a[href*="/campaigns/"]').evaluateAll(
    (els) => els.map((e) => e.getAttribute('href') || ''),
  );
  const ids = [...new Set(
    hrefs.map((h) => (h.match(/\/campaigns\/(\d+)(?:\?|#|$)/) || [])[1]).filter(Boolean),
  )];
  expect(ids.length > 0, 'showcase tenant must expose at least one campaign').toBeTruthy();
  // الأولى ضمن مجموعة «قيد التنفيذ» غالبًا (غير منتهية) — والفحص التالي يتكيّف مع الحالة.
  const campaignId = Number(ids[0]);

  // 3) فتح مساحة عمل الحملة — يجب أن تظهر (شريط التبويبات) بلا شاشة خطأ
  await page.goto(`/app/campaigns/${campaignId}`, { waitUntil: 'networkidle' });
  await expect(page.locator('.ih-worktabs'), 'campaign workspace loaded').toBeVisible();

  // 4) الإجراء التالي: يُعرض لحملة حيّة، ويُخفى (بحقّ) للحملات المنتهية — كلاهما مقبول
  const nbaVisible = await page.locator('.ih-nba').first().isVisible().catch(() => false);
  if (nbaVisible) {
    await expect(page.locator('.ih-nba__title').first(), 'Next Action has a title').not.toBeEmpty();
  } else {
    testInfo.annotations.push({ type: 'next-action', description: 'NOT_APPLICABLE_TERMINAL_CAMPAIGN' });
  }

  // 5) التبويبات الستّة تُفتح بنجاح (تفعيل فعليّ عبر aria-selected؛ الصفحة تبقى سليمة)
  for (const label of TABS) {
    const btn = page.locator('button[role=tab]', { hasText: label }).first();
    await expect(btn, `tab "${label}" exists`).toBeVisible();
    await btn.click();
    await expect(btn, `tab "${label}" activates`).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('.ih-worktabs'), 'workspace remains intact').toBeVisible();
  }

  // 6) فصل المالية مرئيًّا: تبويبا «التحصيل» (تحصيل العميل) و«المستحقات» (مبدع) متمايزان
  await expect(page.locator('button[role=tab]', { hasText: 'التحصيل' }).first()).toBeVisible();
  await expect(page.locator('button[role=tab]', { hasText: 'المستحقات' }).first()).toBeVisible();

  // 6b) لا تسريب آيبان/حساب بنكي خاصّ في مساحة عمل الحملة — نفتح تبويب المستحقات ونفحص النصّ
  await page.locator('button[role=tab]', { hasText: 'المستحقات' }).first().click();
  await page.waitForTimeout(200);
  const bodyText = await page.locator('body').innerText();
  // آيبان سعودي كامل = SA + 22 رقمًا — يجب ألّا يظهر في مساحة عمل الحملة إطلاقًا.
  expect(bodyText, 'no full IBAN on the campaign workspace').not.toMatch(/SA\d{20,}/i);

  // 7) روابط عميقة حقيقية (إن وُجدت صفوف) — كلٌّ يفتح مساره الصحيح، وإلّا NOT_APPLICABLE
  const deep = [
    { tab: 'العقود', row: 'table tbody tr[style*="cursor"]', re: /\/contracts\/\d+/ },
    { tab: 'التحصيل', row: 'a[href*="/invoices/"]', re: /\/invoices\/\d+/ },
    { tab: 'المستحقات', row: 'table tbody tr[style*="cursor"]', re: /\/payouts\/\d+/ },
  ];
  for (const d of deep) {
    await page.locator('button[role=tab]', { hasText: d.tab }).first().click();
    await page.waitForTimeout(200);
    const row = page.locator(d.row).first();
    const has = await row.isVisible().catch(() => false);
    if (!has) { testInfo.annotations.push({ type: 'deep-link', description: `${d.tab}: NOT_APPLICABLE_NO_DATA` }); continue; }
    await Promise.all([page.waitForURL(d.re, { timeout: 20_000 }), row.click()]);
    expect(page.url(), `${d.tab} row opens its detail route`).toMatch(d.re);
    await page.goBack({ waitUntil: 'networkidle' });
    await expect(page.locator('.ih-worktabs').first()).toBeVisible();
  }

  // 8) التذييل العام (#63) على صفحة الحملة المُصادَقة
  const footer = page.locator('.ih-appfoot');
  await footer.scrollIntoViewIfNeeded();
  await expect(footer).toBeVisible();
  await expect(footer).toContainText('InfluencerHub');
  for (const [label, path] of [['الخصوصية', '/privacy'], ['الشروط', '/terms'], ['المساعدة', '/help']]) {
    const link = footer.locator(`a:has-text("${label}")`).first();
    await expect(link).toHaveAttribute('href', new RegExp(`${path}$`));
  }
  // التذييل في التدفّق الطبيعي: لا يغطّي منطقة المحتوى (أعلاه في الصفحة)
  const fbox = await footer.boundingBox();
  const cbox = await page.locator('.ih-content').boundingBox();
  if (fbox && cbox) expect(fbox.y, 'footer sits below content, not overlaying it').toBeGreaterThanOrEqual(cbox.y);

  // 9) الجوال (~390px): لا فيضان أفقي مدمّر
  if (isMobile) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'no destructive horizontal overflow on mobile').toBeLessThanOrEqual(4);
  }

  // 10) لا أخطاء خادم/ JS خلال الرحلة
  expect(badResponses, `no 500/unexpected 403/404 on app routes`).toEqual([]);
  expect(errors, `no uncaught JS / unexpected console errors`).toEqual([]);
});
