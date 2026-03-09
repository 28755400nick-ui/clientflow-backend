#!/bin/bash
set -e

PROJECT_DIR="/var/www/clientflow-backend"

echo "==> Pulling latest code..."
cd $PROJECT_DIR
git pull origin main

echo "==> Building and restarting containers..."
docker compose down
docker compose up -d --build

echo "==> Waiting for MySQL to be ready..."
until docker compose exec -T db mysqladmin ping -h "localhost" --silent; do
    echo "MySQL not ready yet, waiting 5s..."
    sleep 5
done

echo "==> Running migrations..."
docker compose exec -T app php artisan migrate --force

echo "==> Clearing cache..."
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan config:cache

echo "==> Deploy complete!"
