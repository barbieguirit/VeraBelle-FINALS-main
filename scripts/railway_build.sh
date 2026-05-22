#!/usr/bin/env bash
set -euo pipefail

# Railway build script: installs dependencies, writes JWT files from env vars,
# runs migrations and clears cache. Intended to be used as Railway Build Command.

echo "==> Install PHP deps (composer)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Install JS deps and build assets"
if [ -f package.json ]; then
  npm ci
  npm run build
fi

echo "==> Write JWT keys from env variables (if provided)"
if [ -n "${JWT_PRIVATE:-}" ] && [ -n "${JWT_PUBLIC:-}" ]; then
  mkdir -p config/jwt
  printf '%s' "$JWT_PRIVATE" > config/jwt/private.pem
  printf '%s' "$JWT_PUBLIC"  > config/jwt/public.pem
  chmod 600 config/jwt/private.pem || true
  echo "Wrote config/jwt/private.pem and public.pem"
else
  echo "JWT_PRIVATE or JWT_PUBLIC not provided; skipping writing keys"
fi

echo "==> Run migrations (if any)"
php bin/console doctrine:migrations:migrate --no-interaction --env=prod || echo "migrations failed or none"

echo "==> Clear cache"
php bin/console cache:clear --env=prod || true

echo "Build finished"
