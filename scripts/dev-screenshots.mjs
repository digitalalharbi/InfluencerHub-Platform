// أداة إثبات بصري: تسجّل الدخول لبيئة العرض على خادم التطوير وتحفظ لقطات Desktop+Mobile.
// الاستخدام: SHOWCASE_PW=... node scripts/dev-screenshots.mjs <outDir> <email> <url:name> [url:name...]
// مثال: SHOWCASE_PW=xxx node scripts/dev-screenshots.mjs storage/app/private/development-screenshots/x/campaigns showcase_admin@showcase.test "/app/campaigns/98:command-center"
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8010';
const PW = process.env.SHOWCASE_PW;
const [, , outDir, email, ...targets] = process.argv;
if (!PW || !outDir || !email || targets.length === 0) {
  console.error('Missing args. Need SHOWCASE_PW env + <outDir> <email> <url:name>...');
  process.exit(1);
}
mkdirSync(outDir, { recursive: true });

async function login(page) {
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PW);
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type="submit"]')]);
}

const browser = await chromium.launch();
for (const [label, vp] of [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 390, height: 844 }]]) {
  const ctx = await browser.newContext({ viewport: vp, locale: 'ar' });
  const page = await ctx.newPage();
  await login(page);
  for (const t of targets) {
    // صيغة الهدف: "url:name" أو "url:name:نص-زر-يُنقر-قبل-التصوير"
    const parts = t.split(':');
    const url = parts[0], name = parts[1], clickText = parts[2];
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);
    if (clickText) {
      const btn = page.getByRole('button', { name: clickText }).first();
      if (await btn.count()) { await btn.click(); await page.waitForTimeout(300); }
    }
    const path = `${outDir}/${name}-${label}.png`;
    // لقطة بحجم النافذة — الشريط الجانبي/التبويبات لاصقة، وfullPage يشوّه العناصر اللاصقة
    await page.screenshot({ path });
    console.log('saved', path);
  }
  await ctx.close();
}
await browser.close();
console.log('done');
