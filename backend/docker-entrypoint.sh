#!/bin/sh
set -e

# Wait for database to be ready
echo "Waiting for database..."
for i in $(seq 1 60); do
  if php -r "try { new PDO('mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); echo 'OK'; } catch (Exception \$e) { exit(1); }" >/dev/null 2>&1; then
    echo "Database is ready."
    break
  fi
  sleep 1
done

# Wait for Redis to be ready when configured
if [ -n "$REDIS_HOST" ] && [ "$REDIS_HOST" != "127.0.0.1" ]; then
  echo "Waiting for Redis..."
  for i in $(seq 1 30); do
    if php -r "try { \$r = new Redis(); \$r->connect('${REDIS_HOST}', ${REDIS_PORT:-6379}); echo 'OK'; } catch (Exception \$e) { exit(1); }" >/dev/null 2>&1; then
      echo "Redis is ready."
      break
    fi
    sleep 1
  done
fi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
  php artisan key:generate
fi

# Cache config and routes for production (uses runtime env vars)
if [ "$APP_ENV" = "production" ]; then
  php artisan config:cache
  php artisan route:cache
fi

# Run migrations
php artisan migrate --force

# Seed only if no admin user exists and we are in local/development
if [ "$APP_ENV" = "local" ] || [ "$APP_ENV" = "development" ]; then
  if ! php artisan tinker --execute="echo App\\Models\\AppUser::where('email', 'admin@example.com')->exists() ? '1' : '0';" 2>/dev/null | grep -q "1"; then
    echo "Seeding database..."
    php artisan db:seed --force
  fi
fi

# Ensure storage permissions
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Route container role: worker, scheduler or web
if [ "$CONTAINER_ROLE" = "worker" ]; then
  echo "Starting queue worker..."
  exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
fi

if [ "$CONTAINER_ROLE" = "scheduler" ]; then
  echo "Starting scheduler..."
  exec php artisan schedule:work
fi

exec "$@"
