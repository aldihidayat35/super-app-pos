#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/home/pinp7981/pos-super}"
REPOSITORY_URL="${REPOSITORY_URL:-https://github.com/aldihidayat35/super-app-pos.git}"
BRANCH="${BRANCH:-main}"

if [[ ! -d "$APP_DIR" ]]; then
    echo "Direktori aplikasi tidak ditemukan: $APP_DIR" >&2
    exit 1
fi

for command_name in git php; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Command wajib tidak tersedia: $command_name" >&2
        exit 1
    fi
done

cd "$APP_DIR"

if [[ -f .env ]]; then
    backup_dir="$APP_DIR/storage/app/deployment-backups"
    mkdir -p "$backup_dir"
    cp .env "$backup_dir/env-before-git-$(date +%Y%m%d-%H%M%S).backup"
fi

if [[ ! -d .git ]]; then
    git init
    git symbolic-ref HEAD "refs/heads/$BRANCH"
    git remote add origin "$REPOSITORY_URL"
    git fetch --prune origin "$BRANCH"

    # Daftarkan file terhadap commit GitHub tanpa mengubah isi file server.
    git reset --mixed "origin/$BRANCH"
else
    current_branch="$(git branch --show-current)"

    if [[ -n "$current_branch" && "$current_branch" != "$BRANCH" ]]; then
        echo "Branch aktif '$current_branch' bukan '$BRANCH'. Setup dihentikan agar branch tidak terganti otomatis." >&2
        exit 1
    fi

    if git remote get-url origin >/dev/null 2>&1; then
        git remote set-url origin "$REPOSITORY_URL"
    else
        git remote add origin "$REPOSITORY_URL"
    fi

    git fetch --prune origin "$BRANCH"
fi

git branch --set-upstream-to="origin/$BRANCH" "$BRANCH"

echo
echo "Repository cPanel terhubung ke $REPOSITORY_URL ($BRANCH)."
echo "Pemeriksaan perubahan file server:"
git status --short

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo >&2
    echo "Masih ada file source server yang berbeda dari GitHub." >&2
    echo "File tidak diubah oleh script setup. Tinjau daftar di atas sebelum menjalankan deployment." >&2
    exit 2
fi

echo
echo "Setup selesai. Update berikutnya jalankan:"
echo "bash $APP_DIR/scripts/deploy-cpanel.sh"
