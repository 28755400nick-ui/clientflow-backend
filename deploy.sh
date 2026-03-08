#!/bin/bash
set -e

PROJECT_DIR="/var/www/clientflow-backend"

echo "==> Pulling latest code..."
cd $PROJECT_DIR
git pull origin main

echo "==> Building and restarting containers..."
docker compose down
docker compose up -d --build

echo "==> Running migrations..."
docker compose exec -T app php artisan migrate --force

echo "==> Clearing cache..."
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan config:cache

echo "==> Deploy complete!"
