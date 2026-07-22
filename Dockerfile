FROM node:24-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY tsconfig.json vite.config.js ./
RUN npm run build

FROM php:8.4-cli
RUN apt-get update && apt-get install -y git unzip libpq-dev libzip-dev \
 && docker-php-ext-install pdo pdo_pgsql zip bcmath \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . /app
RUN composer install --no-dev --optimize-autoloader --no-interaction
COPY --from=frontend /app/public/build /app/public/build
ENV PORT=8000
EXPOSE 8000
CMD sh -c 'mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; \
 test -n "$APP_KEY" || (echo "APP_KEY is required" >&2; exit 1); \
 php artisan optimize:clear \
 && php artisan migrate --force \
 && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}'