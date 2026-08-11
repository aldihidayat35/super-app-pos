#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/gudangtoko/current}"
SOURCE_PUBLIC_DIR="$APP_DIR/public"
PUBLIC_DIR="${PUBLIC_DIR:-$SOURCE_PUBLIC_DIR}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

cd "$APP_DIR"

"$PHP_BIN" artisan down --render="errors::503" || true

GUDANGTOKO_SKIP_POST_MERGE=1 git pull --ff-only
"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
rm -f "$SOURCE_PUBLIC_DIR/hot" "$PUBLIC_DIR/hot"
"$NPM_BIN" ci
"$NPM_BIN" run build
rm -f "$SOURCE_PUBLIC_DIR/hot" "$PUBLIC_DIR/hot"

if [[ "$PUBLIC_DIR" != "$SOURCE_PUBLIC_DIR" ]]; then
    mkdir -p "$PUBLIC_DIR/assets" "$PUBLIC_DIR/build"
    cp -a "$SOURCE_PUBLIC_DIR/assets/." "$PUBLIC_DIR/assets/"
    cp -a "$SOURCE_PUBLIC_DIR/build/." "$PUBLIC_DIR/build/"
    cp "$SOURCE_PUBLIC_DIR/.htaccess" "$PUBLIC_DIR/.htaccess"
fi

for required_asset in \
    "$PUBLIC_DIR/build/manifest.json" \
    "$PUBLIC_DIR/assets/css/ki-icons-fallback.css" \
    "$PUBLIC_DIR/assets/vendor/metronic/css/style.bundle.css"
do
    if [[ ! -f "$required_asset" ]]; then
        echo "Asset production tidak ditemukan: $required_asset" >&2
        exit 1
    fi
done

if [[ -f "$SOURCE_PUBLIC_DIR/hot" || -f "$PUBLIC_DIR/hot" ]]; then
    echo "File hot tidak boleh tersedia pada source public maupun document root production." >&2
    exit 1
fi

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart
"$PHP_BIN" artisan system:encrypted-backup --connection=mysql --dry-run
"$PHP_BIN" artisan up
"$PHP_BIN" artisan about
