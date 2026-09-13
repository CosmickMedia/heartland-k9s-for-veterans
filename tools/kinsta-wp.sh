#!/usr/bin/env bash
# WP-CLI against the DevKinsta site (runs wp-cli under PHP 8.3 inside devkinsta_fpm):  tools/kinsta-wp.sh <wp args>
SITE="${HK9_KINSTA_SITE:-heartland-canines-for-veterans}"
exec docker exec -i devkinsta_fpm sh -c "cd /www/kinsta/public/$SITE && php8.3 /usr/local/bin/wp --allow-root $(printf '%q ' "$@")"
