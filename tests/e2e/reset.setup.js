// @ts-check
import { test as setup } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * عزل قاعدة E2E بين مشاريع المتصفّحات.
 *
 * كل المشاريع (chromium/firefox/webkit) تعمل بعامل واحد على خادم واحد وقاعدة E2E
 * واحدة. الاختبارات المُطفِرة (إرسال علامة، قبول دعوة أحادية الاستخدام، قبول طلب)
 * كانت تلوّث الحالة للمشروع التالي: يمرّ chromium ثم يفشل firefox/webkit على حالة
 * مُطفَّرة مسبقًا. يُعيد هذا الإعداد ضبط القاعدة (migrate:fresh + e2e:seed) قبل كل
 * مشروع متصفّح عبر سلسلة تبعيات، فيبدأ كلٌّ من حالة بذور نظيفة حتمية.
 *
 * أبسط حلّ موثوق: بلا تعقيد قواعد بيانات متعدّدة ولا خوادم متعدّدة — إعادة بذر
 * حتمية بين المشاريع فقط. لا يُقلّل أيّ تغطية ولا يحوّل اختبار طفرة إلى قراءة.
 */
setup('reset e2e database', () => {
  const env = {
    ...process.env,
    // نفس بيئة tests/e2e/boot.sh — متغيّرات البيئة تتقدّم على .env في Laravel
    PATH: `/opt/homebrew/opt/php@8.4/bin:/opt/homebrew/bin:${process.env.PATH ?? ''}`,
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    DB_CONNECTION: 'pgsql',
    DB_HOST: '127.0.0.1',
    DB_PORT: '5432',
    DB_DATABASE: 'influencerhub_e2e',
    SESSION_DRIVER: 'database',
    CACHE_STORE: 'database',
    QUEUE_CONNECTION: 'sync',
    E2E_PASSWORD: process.env.E2E_PASSWORD || 'e2e-local-secret',
  };
  execSync('php artisan migrate:fresh --force && php artisan e2e:seed', {
    stdio: 'inherit',
    env,
    timeout: 90_000,
  });
});
