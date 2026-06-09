#!/bin/bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "$0")" && pwd)"
WP_CONTENT="/www/wwwroot/datxevietnam.vn/wp-content"

cd "$REPO_DIR"
git pull origin main

rsync -av --delete \
  "$REPO_DIR/themes/generatepress_child/" \
  "$WP_CONTENT/themes/generatepress_child/"

rsync -av --delete \
  "$REPO_DIR/plugins/minh-thang-transport-flow/" \
  "$WP_CONTENT/plugins/minh-thang-transport-flow/"

echo "Deploy completed."
