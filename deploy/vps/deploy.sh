#!/usr/bin/env bash
set -euo pipefail

docker compose -f deploy/vps/docker-compose.yml --env-file deploy/vps/.env build
docker compose -f deploy/vps/docker-compose.yml --env-file deploy/vps/.env up -d
docker compose -f deploy/vps/docker-compose.yml --env-file deploy/vps/.env exec app php artisan migrate --force
docker compose -f deploy/vps/docker-compose.yml --env-file deploy/vps/.env exec app php artisan db:seed --force
docker compose -f deploy/vps/docker-compose.yml --env-file deploy/vps/.env exec app php artisan optimize:clear