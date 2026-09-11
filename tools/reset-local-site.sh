#!/usr/bin/env bash
# Reset the LOCAL docker WordPress site to a clean, content-free state (keeps core install, users, theme + plugin activation).
# Used before a fresh import run. Never run against a real site.
set -euo pipefail
cd "$(dirname "$0")/.."
WP=tools/wp.sh
$WP eval 'if ( ! defined("HK9_LOCAL_DEV") ) { fwrite(STDERR, "Refusing: HK9_LOCAL_DEV not defined\n"); exit(1); }'
echo "==> Emptying site content + uploads…"
$WP site empty --uploads --yes
echo "==> Resetting reading settings, theme mods, menus, importer state…"
$WP option update show_on_front posts >/dev/null
$WP option update page_on_front 0 >/dev/null
$WP option update page_for_posts 0 >/dev/null
$WP option delete hk9_settings >/dev/null 2>&1 || true
$WP option delete hk9_redirects >/dev/null 2>&1 || true
$WP theme mod remove --all >/dev/null 2>&1 || true
$WP hk9 reset-state --yes >/dev/null 2>&1 || true
$WP db query "TRUNCATE TABLE $($WP db prefix 2>/dev/null | tr -d '\r')hk9_import_map" >/dev/null 2>&1 || true
$WP transient delete --all >/dev/null 2>&1 || true
$WP hk9 redirects seed >/dev/null 2>&1 || true
$WP rewrite flush --hard >/dev/null 2>&1 || true
docker exec hk9-wordpress-1 sh -c ': > /var/www/html/wp-content/debug.log' 2>/dev/null || true
echo "==> Done. Posts: $($WP post list --post_type=any --post_status=any --format=count), attachments: $($WP post list --post_type=attachment --format=count)"
