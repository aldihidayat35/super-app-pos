#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/home/pinp7981/pos-super}"
PUBLIC_DIR="${PUBLIC_DIR:-/home/pinp7981/public_html/super-app-kedaung.demokan.online}"

if [[ ! -d "$APP_DIR/.git" ]]; then
    echo "Repository Git tidak ditemukan: $APP_DIR" >&2
    exit 1
fi

if [[ ! -d "$PUBLIC_DIR" ]]; then
    echo "Document root domain tidak ditemukan: $PUBLIC_DIR" >&2
    exit 1
fi

cd "$APP_DIR"

chmod +x \
    "$APP_DIR/.githooks/post-merge" \
    "$APP_DIR/scripts/sync-cpanel-after-pull.sh"

git config core.hooksPath "$APP_DIR/.githooks"
git config gudangtoko.publicDir "$PUBLIC_DIR"

echo "Auto-deploy cPanel berhasil diaktifkan."
echo "Repository : $APP_DIR"
echo "Document root: $PUBLIC_DIR"
echo
echo "Menjalankan sinkronisasi pertama..."
bash "$APP_DIR/scripts/sync-cpanel-after-pull.sh" "$PUBLIC_DIR"
echo
echo "Mulai sekarang jalankan dari folder mana pun:"
echo "git -C \"$APP_DIR\" pull --ff-only"
