#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

composer install --no-dev --prefer-dist --no-interaction --working-dir "$SCRIPT_DIR"
php "$SCRIPT_DIR/sync_css_selector.php"
