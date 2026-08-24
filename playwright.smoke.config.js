// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * إعداد فحص الدخان للإنتاج (Production smoke) — منفصل تمامًا عن e2e المحلّي:
 * لا webServer، ولا سلسلة إعادة تهيئة، ولا قاعدة اختبار. يستهدف الإنتاج الحقيقي
 * ويسجّل الدخول بحساب بيئة العرض المُصرَّح به. يُشغَّل يدويًّا عبر workflow_dispatch.
 * لا لقطات شاشة افتراضيًّا (تفاديًا لأيّ بيانات إنتاج حسّاسة في المخرجات).
 */
const baseURL = process.env.SMOKE_BASE_URL || 'https://influencerhub.io';

export default defineConfig({
  testDir: './tests/smoke',
  fullyParallel: false,
  workers: 1,
  retries: 1,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: [['list']],
  use: {
    baseURL,
    locale: 'ar',
    ignoreHTTPSErrors: false,
    screenshot: 'off',   // لا لقطات — قد تحمل بيانات إنتاج حسّاسة
    video: 'off',
    trace: 'off',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 900 } } },
    // فحص الجوال ~390px — تبويبات قابلة للاستخدام، بلا فيضان أفقي مدمّر، تذييل يلتفّ
    { name: 'mobile', use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 } } },
  ],
});
