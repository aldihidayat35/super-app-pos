#!/usr/bin/env bash
set -euo pipefail

export APP_DIR="${APP_DIR:-/home/pinp7981/pos-super}"
export PUBLIC_DIR="${PUBLIC_DIR:-/home/pinp7981/public_html/super-app-kedaung.demokan.online}"

exec bash "$APP_DIR/scripts/deploy-production.sh"
