#!/usr/bin/env bash
# Menyiapkan database KHUSUS e2e-web (mill_smart_log_e2e) dari nol:
# migrate:fresh + seeder demo (DatabaseSeeder) + BrowserTestFixtureSeeder.
#
# DESTRUKTIF — migrate:fresh membuang SELURUH tabel. Karena itu skrip ini
# lebih dulu menanyakan ke Laravel database mana yang sebenarnya dipakai
# environment e2e, dan menolak berjalan kecuali namanya berakhiran "_e2e".
# Tanpa penjaga ini, .env.e2e yang hilang membuat --env=e2e diam-diam jatuh
# ke .env biasa — yaitu database dev.
#
# Pemakaian (dari mana saja):  e2e-web/scripts/prepare-db.sh
#                         atau: cd e2e-web && npm run db:prepare
set -euo pipefail

BACKEND_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../backend" && pwd)"
cd "$BACKEND_DIR"

if [[ ! -f .env.e2e ]]; then
  echo "backend/.env.e2e tidak ada. Salin dari backend/.env.e2e.example lalu isi APP_KEY dan DB_*." >&2
  exit 1
fi

read -r APP_ENV_NOW DB_NAME < <(php artisan tinker --env=e2e --no-interaction \
  --execute="echo app()->environment().' '.config('database.connections.'.config('database.default').'.database').PHP_EOL;" | tail -n 1)

if [[ "$APP_ENV_NOW" != "e2e" || "$DB_NAME" != *_e2e ]]; then
  echo "DITOLAK: --env=e2e menunjuk env='$APP_ENV_NOW' database='$DB_NAME'." >&2
  echo "migrate:fresh hanya boleh menyentuh database berakhiran _e2e." >&2
  exit 1
fi

echo "[prepare-db] env=$APP_ENV_NOW database=$DB_NAME"

php artisan migrate:fresh --env=e2e --force --seed
php artisan db:seed --env=e2e --force --class=BrowserTestFixtureSeeder

echo "[prepare-db] selesai. Jalankan server:  (cd backend && php artisan serve --env=e2e --port=8001)"
