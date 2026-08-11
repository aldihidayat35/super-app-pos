#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PUBLIC_DIR="${1:-${PUBLIC_DIR:-}}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

if [[ -z "$PUBLIC_DIR" ]]; then
    echo "Document root domain belum ditentukan." >&2
    exit 1
fi

if [[ ! -d "$PUBLIC_DIR" ]]; then
    echo "Document root domain tidak ditemukan: $PUBLIC_DIR" >&2
    exit 1
fi

for command_name in "$PHP_BIN" "$COMPOSER_BIN" "$NPM_BIN"; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Command tidak tersedia: $command_name" >&2
        exit 1
    fi
done

cd "$APP_DIR"

echo "[1/6] Memasang dependency PHP..."
"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "[2/6] Membuat build frontend..."
rm -f "$APP_DIR/public/hot" "$PUBLIC_DIR/hot"
"$NPM_BIN" ci
"$NPM_BIN" run build

echo "[3/6] Menyalin asset ke document root..."
mkdir -p "$PUBLIC_DIR/assets" "$PUBLIC_DIR/build"
cp -a "$APP_DIR/public/assets/." "$PUBLIC_DIR/assets/"
cp -a "$APP_DIR/public/build/." "$PUBLIC_DIR/build/"
cp "$APP_DIR/public/.htaccess" "$PUBLIC_DIR/.htaccess"

if ! cmp -s "$APP_DIR/public/build/manifest.json" "$PUBLIC_DIR/build/manifest.json"; then
    echo "Manifest build di document root tidak sama dengan hasil build terbaru." >&2
    exit 1
fi

echo "[4/6] Menjalankan migrasi database..."
"$PHP_BIN" artisan migrate --force

echo "[5/6] Memperbarui cache Laravel..."
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart

echo "[6/6] Auto-deploy selesai."
"$PHP_BIN" artisan up
