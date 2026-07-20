#!/usr/bin/env bash
set -e
export PATH="/opt/homebrew/opt/php@8.4/bin:/opt/homebrew/bin:$PATH"
cd "$(dirname "$0")/../.."

# نصدّر متغيّرات البيئة مباشرة — متغيّرات البيئة الحقيقية تتقدّم على .env في Laravel،
# فيضمن ذلك أن كل العمليات (migrate/seed/serve) تستهدف قاعدة E2E فعليًا.
export APP_ENV=local
export APP_DEBUG=true
export DB_CONNECTION=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=influencerhub_e2e
export SESSION_DRIVER=database
export CACHE_STORE=database
export QUEUE_CONNECTION=sync
export E2E_PASSWORD="${E2E_PASSWORD:-e2e-local-secret}"

php artisan config:clear >/dev/null 2>&1 || true
php artisan migrate:fresh --force
php artisan e2e:seed
exec php artisan serve --host=127.0.0.1 --port=8020
