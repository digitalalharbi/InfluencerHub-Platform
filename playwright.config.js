// @ts-check
import { defineConfig, devices } from '@playwright/test';

// كلمة مرور E2E موحّدة (ليست ثابتة في كود المصدر التطبيقي) — تُورَّث للخادم والبذور.
process.env.E2E_PASSWORD = process.env.E2E_PASSWORD || 'e2e-local-secret';

/** إعداد Playwright لـPhase 3 — يهيّئ قاعدة E2E ويشغّل الخادم قبل السيناريوهات. */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,          // ترتيب حتمي على قاعدة مشتركة
    workers: 1,
    retries: 0,
    timeout: 30000,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'tests/e2e/report' }]],
    use: {
        baseURL: 'http://127.0.0.1:8020',
        locale: 'ar',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    // عزل الحالة بين المتصفّحات: تُعاد بذور القاعدة قبل كل مشروع متصفّح عبر سلسلة
    // تبعيات تفرض الترتيب: reset→chromium→reset→firefox→reset→webkit. هكذا يبدأ كل
    // متصفّح من حالة نظيفة، فلا تلوّث اختبارات الطفرة (إرسال/قبول أحادي) المشروع التالي.
    projects: [
        { name: 'reset-chromium', testMatch: /reset\.setup\.js/ },
        { name: 'chromium', use: { ...devices['Desktop Chrome'] }, testIgnore: /reset\.setup\.js/, dependencies: ['reset-chromium'] },

        { name: 'reset-firefox', testMatch: /reset\.setup\.js/, dependencies: ['chromium'] },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] }, testIgnore: /reset\.setup\.js/, dependencies: ['reset-firefox'] },

        { name: 'reset-webkit', testMatch: /reset\.setup\.js/, dependencies: ['firefox'] },
        { name: 'webkit', use: { ...devices['Desktop Safari'] }, testIgnore: /reset\.setup\.js/, dependencies: ['reset-webkit'] },
    ],
    // يهيّئ المخطط + البذور ثم يشغّل الخادم على قاعدة E2E
    webServer: {
        command: 'bash tests/e2e/boot.sh',
        url: 'http://127.0.0.1:8020/login',
        timeout: 60000,
        reuseExistingServer: false,
    },
});
