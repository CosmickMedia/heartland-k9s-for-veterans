#!/usr/bin/env bash
# Fresh-site install test: WordPress + ZIPs from dist/ + payload import, then the URL matrix.
set -uo pipefail
cd "$(dirname "$0")/../.."
C="docker compose -f docker/fresh/docker-compose.yml"
wp() { $C run --rm -T wpcli "$@" 2> >(grep -v -E '^\s*Container ' >&2); }
echo "==> Waiting for WordPress…"; for i in $(seq 1 60); do wp core version >/dev/null 2>&1 && break; sleep 2; done
echo "    WordPress $(wp core version)"
wp core is-installed >/dev/null 2>&1 || wp core install --url=http://localhost:8095 --title="Fresh Install Test" --admin_user=admin --admin_password=admin --admin_email=admin@fresh.test --skip-email
wp option update timezone_string America/Chicago >/dev/null
wp rewrite structure '/%postname%/' --hard >/dev/null
echo "==> Installing from ZIPs…"
wp plugin install /dist/heartland-k9s-core.zip --activate 2>&1 | tail -1
wp theme install /dist/heartland-k9s.zip --activate 2>&1 | tail -1
wp rewrite flush --hard >/dev/null
echo "==> Import (dry run, then real)…"
wp hk9 import /payload --dry-run --user=admin 2>&1 | tail -3
time wp hk9 import /payload --user=admin 2>&1 | tail -16
echo "==> Re-import (must be all skips)…"
wp hk9 import /payload --user=admin 2>&1 | grep -E "^(media_files|posts_hierarchy|posts_content|menus|options|redirects)\s"
echo "==> URL matrix…"
bash tools/url-matrix.sh http://localhost:8095 > docs/reports/url-matrix-fresh.md; echo "url matrix: $(grep -c '✅' docs/reports/url-matrix-fresh.md) ✅ / $(grep -c '❌' docs/reports/url-matrix-fresh.md) ❌"
echo "==> debug.log:"; docker exec hk9fresh-wordpress-1 sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null | sort | uniq -c | sort -rn | head -5; echo "(end)"'
