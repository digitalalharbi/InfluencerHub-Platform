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

/**
 * يقرأ خصائص Inertia من HTML الخام (قبل الترطيب) عبر طلب مُصادَق — لأن Inertia
 * يحذف سمة data-page من الجذر بعد الترطيب فلا تصلح قراءتها من DOM الحيّ.
 */
async function inertiaProps(page, path) {
  const res = await page.request.get(path);
  expect(res.ok(), `GET ${path} ok`).toBeTruthy();
  const html = await res.text();
  const m = html.match(/id="app"[^>]*data-page="([^"]*)"/);
  expect(m, `Inertia data-page present in ${path}`).toBeTruthy();
  const json = m[1]
    .replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&#0?34;/g, '"')
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
  return JSON.parse(json).props;
}

test('authenticated campaign workspace smoke on production', async ({ page }, testInfo) => {
  expect(PASSWORD, 'SHOWCASE_PASSWORD must be provided by the workflow').not.toEqual('');
  const { errors, badResponses } = watch(page);
  const isMobile = testInfo.project.name === 'mobile';

  // 1) دخول مُصادَق
  await login(page);

  // 2) اكتشاف حملة حقيقية من قائمة الحملات (لا افتراض معرّف)
  await page.goto('/app/campaigns', { waitUntil: 'networkidle' });
  const listProps = await inertiaProps(page, '/app/campaigns');
  const rows = listProps?.campaigns?.data ?? [];
  expect(Array.isArray(rows) && rows.length > 0, 'showcase tenant must expose at least one campaign').toBeTruthy();
  // فضّل حملة غير منتهية ليكون فحص «الإجراء التالي» ذا معنى (يُخفى للمكتملة/الملغاة)
  const FINAL = ['completed', 'cancelled'];
  const chosen = rows.find((r) => r.status && !FINAL.includes(r.status)) ?? rows[0];
  const campaignId = chosen.id;

  // 3) فتح تفاصيل الحملة — تحميل كامل للواجهة + قراءة الحمولة من HTML الخام
  await page.goto(`/app/campaigns/${campaignId}`, { waitUntil: 'networkidle' });
  const props = await inertiaProps(page, `/app/campaigns/${campaignId}`);
  expect(props.campaign?.id, 'campaign detail payload must load').toBe(campaignId);

  // فصل المالية في الحمولة نفسها: تحصيل العميل (invoices) ≠ مستحقات المبدع (payouts)
  expect(Array.isArray(props.invoices), 'invoices (client collection) array present').toBeTruthy();
  expect(Array.isArray(props.payouts), 'payouts (creator dues) array present').toBeTruthy();
  expect(Array.isArray(props.contracts), 'contracts array present').toBeTruthy();
  // لا تسريب آيبان/بيانات بنكية خاصّة في حمولة المستحقات
  const payoutStr = JSON.stringify(props.payouts).toLowerCase();
  expect(payoutStr).not.toContain('iban');
  expect(JSON.stringify(props.payouts)).not.toMatch(/SA\d{20,}/);

  // 4) الإجراء التالي يُعرض ويشتقّ من الحالة (عنوان + مرحلة) — يُخفى فقط للحملات المنتهية
  const isFinalCampaign = FINAL.includes(props.campaign?.status);
  if (!isFinalCampaign) {
    await expect(page.locator('.ih-nba__eyebrow').first(), 'Next Action renders for a live campaign').toBeVisible();
    await expect(page.locator('.ih-nba__title').first()).not.toBeEmpty();
  } else {
    testInfo.annotations.push({ type: 'next-action', description: 'campaign is terminal — Next Action intentionally hidden' });
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
