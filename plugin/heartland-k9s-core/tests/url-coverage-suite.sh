#!/usr/bin/env bash
# URL coverage simulation on the throw-away FRESH docker stack (port 8095; the main stack on 8093 is never touched).
#
#   bash plugin/heartland-k9s-core/tests/url-coverage-suite.sh
#
# Proves that no crawled / sitemapped live URL is lost by the in-place migration (docs/install.md §B):
#   1. brings docker/fresh up (a clean WordPress, no bind-mounted sources), installs core,
#   2. rebuilds the OLD live site the way tests/adopt-existing-oldsite.php + tests/lite-payload-oldsite.php do,
#      but complete: all 40 live pages (live ids, slugs, parents, Avada templates), all 244 live attachments at
#      their live upload paths with their live ids (files copied from the FULL payload, media/live__media__<id>/…,
#      into the container's uploads; core image sizes generated like on the old site), the second live author
#      (hk9director), an "Old Menu", the live Reading settings,
#   3. installs plugin + theme from build/*.zip (HK9_PLUGIN_ZIP / HK9_THEME_ZIP override), copies payload-lite/
#      into the container and runs the content-only payload with --adopt-existing (pre-flight, dry run, import),
#   4. asserts with curl against http://localhost:8095: every crawled page 200, the 16 registry paths 301 →
#      /barkode/…/ → 200, both author archives 301 → /, EVERY crawled image URL 200 — except the FooGallery
#      /uploads/cache/ thumbs, reported separately (kept on disk on the real site; not part of the payload) —,
#      every upload URL the NEW theme output references resolves (and none is a FooGallery cache path),
#      tools/url-matrix.sh all ✅, debug.log clean,
#   5. writes the results into docs/reports/url-coverage.md (node tools/url-coverage.mjs --suite=…),
#   6. tears the fresh stack down (docker compose … down -v) and removes every temporary file.
#
# Env: HK9_KEEP_STACK=1 keeps the stack running afterwards; HK9_TMPDIR=<dir> for the temporary files
# (default: mktemp under $TMPDIR); HK9_FRESH_URL (default http://localhost:8095).
set -uo pipefail
cd "$(dirname "$0")/../../.."
ROOT=$(pwd)
C="docker compose -f docker/fresh/docker-compose.yml"
WPC=hk9fresh-wordpress-1
BASE="${HK9_FRESH_URL:-http://localhost:8095}"
PLUGIN_ZIP="${HK9_PLUGIN_ZIP:-build/heartland-k9s-core.zip}"
THEME_ZIP="${HK9_THEME_ZIP:-build/heartland-k9s.zip}"
TMP=$(mktemp -d "${HK9_TMPDIR:-${TMPDIR:-/tmp}}/hk9-url-coverage.XXXXXX")
CTMP=/var/www/html/hk9-tmp                # inside the container (removed with the stack)
LITE_C=$CTMP/payload-lite
pass=0; fail=0; last=""
ok()   { pass=$((pass+1)); printf '  PASS  %s\n' "$1"; }
bad()  { fail=$((fail+1)); printf '  FAIL  %s\n' "$1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 (got '$2', want '$3')"; fi; }
wp()   { $C run --rm -T wpcli "$@" 2> >(grep -v -E '^\s*Container |^\s*Network |^\s*Volume ' >&2); }
wpq()  { $C run --rm -T wpcli "$@" 2>/dev/null; }
run_import() { $C run --rm -T wpcli hk9 import "$@" --user=admin --quiet-progress > "$TMP/import.out" 2>&1; rc=$?; last=$(grep -vE '^\s*(Container|Network|Volume) ' "$TMP/import.out"); }
count() { # column looked up by header name in the tab-separated summary
  local v; v=$(printf '%s\n' "$last" | awk -F'\t' -v s="$1" -v name="$2" '
    $1 == "step" { for (i = 1; i <= NF; i++) if ($i == name) col = i; next }
    $1 == s && col { print $col; exit }')
  printf '%s' "${v:-?}"
}
http() { curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -A hk9-url-coverage --max-time 30 "$BASE$1"; }
py()   { python3 -c "$@"; }
notes=()
cleanup() {
  if [ -z "${HK9_KEEP_STACK:-}" ]; then
    echo "-- teardown (docker compose … down -v)"
    $C down -v --remove-orphans >/dev/null 2>&1
  else
    echo "-- HK9_KEEP_STACK set: the fresh stack stays up at $BASE (remove with: $C down -v)"
  fi
  rm -rf "$TMP"
}
trap cleanup EXIT

echo "== URL coverage simulation (fresh stack $BASE) =="
[ -f "$PLUGIN_ZIP" ] && [ -f "$THEME_ZIP" ] || { echo "Missing $PLUGIN_ZIP / $THEME_ZIP (npm run package)"; exit 1; }
[ -f payload-lite/manifest.json ] && [ -f payload/media-index.json ] && [ -d payload/media ] || { echo "Need payload-lite/ and the FULL payload/ (media files) in the checkout"; exit 1; }
[ -f urls-internal_all.csv ] || { echo "Missing urls-internal_all.csv"; exit 1; }

echo "-- 0. classify the crawled / sitemapped URLs (tools/url-coverage.mjs --emit-urls)"
node tools/url-coverage.mjs --emit-urls="$TMP/urls.json" >/dev/null || { echo "url-coverage.mjs reported problems; fix them first"; exit 1; }
check "URL list emitted" "$(py 'import json;d=json.load(open("'"$TMP"'/urls.json"));print(len(d), sum(1 for u in d if u["type"]=="page"), sum(1 for u in d if u["type"]=="registry page"), sum(1 for u in d if u["type"]=="author archive"), sum(1 for u in d if u["type"].startswith("image ")), sum(1 for u in d if u["type"]=="FooGallery cache thumb"))')" "371 24 16 2 126 84"

echo "-- 1. fresh stack up (clean volume)"
$C down -v --remove-orphans >/dev/null 2>&1
$C up -d >/dev/null 2>&1 || { echo "docker compose up failed"; exit 1; }
for i in $(seq 1 90); do wpq core version >/dev/null 2>&1 && break; sleep 2; done
wp_version=$(wpq core version)
check "WordPress reachable" "$( [ -n "$wp_version" ] && echo yes )" "yes"
wpq core is-installed >/dev/null 2>&1 || wpq core install --url="$BASE" --title="Heartland (old site simulation)" --admin_user=admin --admin_password=admin --admin_email=admin@fresh.test --skip-email >/dev/null
wpq option update timezone_string America/Chicago >/dev/null
wpq rewrite structure '/%postname%/' --hard >/dev/null
check "core installed at $BASE" "$(wpq option get siteurl)" "$BASE"
docker exec "$WPC" sh -c "mkdir -p $CTMP && chown 33:33 $CTMP" || { echo "container $WPC not running"; exit 1; }

echo "-- 2. rebuild the old live site (40 pages with live ids/slugs, 244 attachments at live paths; takes a few minutes)"
node -e '
const inv = JSON.parse(require("fs").readFileSync("discovery/live/inventory.json", "utf8"));
const pages = inv.pages.map(p => ({ id: p.id, path: p.slug_path, template: p.template || "default", registry: p.classification === "barkode-record" || p.classification === "master-template",
  title: (p.classification === "barkode-record" || p.classification === "master-template") ? `Registry page ${p.id}` : p.title }));
require("fs").writeFileSync(process.argv[1], JSON.stringify(pages));
' "$TMP/pages.json"
cat > "$TMP/fixture.php" <<'PHP'
<?php
/**
 * LOCAL-ONLY fixture (tests/url-coverage-suite.sh): the old heartlandk9s.org site — every live page with its live id,
 * slug, parent and Avada template (registry pages carry generic placeholder content, never registry data), every
 * live attachment with its live id at its live upload path (files copied from the FULL payload, core image sizes
 * generated as on the old site), the second live author, an old menu and the live Reading settings.
 *   wp eval-file fixture.php <full-payload-dir> <pages.json>
 */
$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( $host, [ 'localhost', '127.0.0.1' ], true ) ) {
	fwrite( STDERR, "Refusing: {$host} is not a local stack.\n" );
	exit( 1 );
}
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$payload_dir = rtrim( (string) $args[0], '/' );
$pages       = json_decode( (string) file_get_contents( (string) $args[1] ), true );
$manifest    = json_decode( (string) file_get_contents( $payload_dir . '/manifest.json' ), true );
$index       = json_decode( (string) file_get_contents( $payload_dir . '/media-index.json' ), true );
if ( ! is_array( $pages ) || ! is_array( $manifest ) || ! is_array( $index ) ) {
	fwrite( STDERR, "Need pages.json + the FULL payload (manifest.json, media-index.json, media/).\n" );
	exit( 1 );
}
$fusion = static fn( string $title ): string => '[fusion_builder_container hundred_percent="no" equal_height_columns="no"][fusion_builder_row][fusion_builder_column type="1_1" layout="1_1"][fusion_text]<h1>' . esc_html( $title ) . '</h1><p>Old Avada builder content for ' . esc_html( $title ) . ' (pre-migration state).</p>[/fusion_text][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]';

/* users: the live site has a second author whose archive is crawled */
$director = get_user_by( 'login', 'hk9director' );
$director_id = $director ? (int) $director->ID : (int) wp_insert_user( [ 'user_login' => 'hk9director', 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'director@fresh.test', 'role' => 'administrator', 'display_name' => 'HK9 Director' ] );

/* pages: parents first */
usort( $pages, static fn( $a, $b ) => substr_count( $a['path'], '/' ) <=> substr_count( $b['path'], '/' ) ?: $a['id'] <=> $b['id'] );
$by_path = [];
$created = 0;
foreach ( $pages as $p ) {
	$id   = (int) $p['id'];
	$path = trim( (string) $p['path'], '/' );
	$slug = '' === $path ? 'home' : basename( $path );
	$parent_path = str_contains( $path, '/' ) ? dirname( $path ) : '';
	$parent = '' !== $parent_path && isset( $by_path[ $parent_path ] ) ? $by_path[ $parent_path ] : 0;
	$existing = get_post( $id );
	if ( $existing instanceof WP_Post ) {
		wp_delete_post( $id, true );
	}
	$new = wp_insert_post(
		[
			'import_id'    => $id,
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => (string) $p['title'],
			'post_content' => $fusion( (string) $p['title'] ),
			'post_parent'  => $parent,
			'post_date'    => '2021-01-01 10:00:00',
			'post_author'  => $id % 2 ? 1 : $director_id,
		],
		true
	);
	if ( is_wp_error( $new ) || (int) $new !== $id ) {
		fwrite( STDERR, sprintf( "Could not create page #%d (%s): %s\n", $id, $slug, is_wp_error( $new ) ? $new->get_error_message() : 'got #' . (int) $new ) );
		exit( 1 );
	}
	update_post_meta( $id, '_wp_page_template', (string) $p['template'] );
	update_post_meta( $id, 'pyre_page_title', 'yes' );
	$by_path[ $path ] = $id;
	++$created;
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', 6 );
update_option( 'page_for_posts', 0 );
update_option( 'wp_page_for_privacy_policy', 3 );

/* attachments: every live:media:<id> at its live path (source_url of the media index) with its live id */
$items   = is_array( $index['items'] ?? null ) ? $index['items'] : [];
$uploads = wp_upload_dir();
$media   = 0;
$paths   = [];
$errors  = [];
foreach ( (array) ( $manifest['records'] ?? [] ) as $record ) {
	$key = (string) ( $record['key'] ?? '' );
	if ( 'attachment' !== ( $record['type'] ?? '' ) || ! preg_match( '/^live:media:(\d+)$/', $key, $m ) ) {
		continue;
	}
	$id   = (int) $m[1];
	$item = $items[ $key ] ?? null;
	$url  = (string) ( $item['source_url'] ?? $item['served_full_url'] ?? '' );
	if ( ! preg_match( '#^https?://[^/]+/wp-content/uploads/(.+)$#i', $url, $u ) ) {
		$errors[] = "{$key}: no uploads source_url in the media index";
		continue;
	}
	$live_path = ltrim( rawurldecode( strtok( $u[1], '?' ) ), '/' );
	$src       = $payload_dir . '/' . (string) $record['file'];
	if ( ! is_file( $src ) ) {
		$errors[] = "{$key}: payload file missing ({$record['file']})";
		continue;
	}
	$existing = get_post( $id );
	if ( $existing instanceof WP_Post ) {
		wp_delete_post( $id, true );
	}
	$dest = trailingslashit( $uploads['basedir'] ) . $live_path;
	wp_mkdir_p( dirname( $dest ) );
	if ( ! copy( $src, $dest ) ) {
		$errors[] = "{$key}: could not copy to {$live_path}";
		continue;
	}
	$sensitive = ! empty( $record['sensitive'] );
	$title     = $sensitive ? 'Registry image' : (string) ( $record['title'] ?? pathinfo( $live_path, PATHINFO_FILENAME ) );
	$date      = (string) ( $record['date'] ?? '' );
	$type      = wp_check_filetype( basename( $live_path ) );
	$post      = [
		'import_id'      => $id,
		'post_mime_type' => (string) ( $type['type'] ?: ( $record['mime'] ?? 'application/octet-stream' ) ),
		'post_title'     => $title,
		'post_status'    => 'inherit',
		'guid'           => trailingslashit( $uploads['baseurl'] ) . $live_path,
	];
	if ( '' !== $date && false !== strtotime( $date ) ) {
		$post['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
		$post['post_date']     = get_date_from_gmt( $post['post_date_gmt'] );
	}
	$new = wp_insert_attachment( $post, $dest, 0, true );
	if ( is_wp_error( $new ) || (int) $new !== $id ) {
		$errors[] = sprintf( '%s: could not create attachment #%d (%s)', $key, $id, is_wp_error( $new ) ? $new->get_error_message() : 'got #' . (int) $new );
		continue;
	}
	$meta = wp_generate_attachment_metadata( $id, $dest ); // core sizes (thumbnail/medium/medium_large/large) + -scaled for big images, as on the old site
	if ( is_array( $meta ) && $meta ) {
		wp_update_attachment_metadata( $id, $meta );
	}
	if ( ! $sensitive && '' !== (string) ( $record['alt'] ?? '' ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', (string) $record['alt'] );
	}
	$paths[ $id ] = (string) get_post_meta( $id, '_wp_attached_file', true );
	++$media;
}

/* old menu (left untouched by the importer) */
$old_menu = wp_get_nav_menu_object( 'Old Menu' );
$menu_id  = $old_menu ? (int) $old_menu->term_id : (int) wp_create_nav_menu( 'Old Menu' );
if ( ! $old_menu ) {
	foreach ( [ [ 6, 'Home' ], [ 2032, 'Contact' ] ] as $i => [ $object, $title ] ) {
		wp_update_nav_menu_item( $menu_id, 0, [ 'menu-item-status' => 'publish', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $object, 'menu-item-title' => $title, 'menu-item-position' => $i + 1 ] );
	}
}
clean_post_cache( 6 );
flush_rewrite_rules( false );

foreach ( $errors as $e ) {
	fwrite( STDERR, $e . "\n" );
}
if ( $errors ) {
	exit( 1 );
}
$files = 0;
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $uploads['basedir'], FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	if ( $f->isFile() ) {
		++$files;
	}
}
echo wp_json_encode( [ 'pages' => $created, 'attachments' => $media, 'files' => $files, 'director' => $director_id, 'page_on_front' => (int) get_option( 'page_on_front' ) ] ) . "\n";
PHP
docker cp "$TMP/fixture.php" "$WPC:$CTMP/fixture.php" >/dev/null && docker cp "$TMP/pages.json" "$WPC:$CTMP/pages.json" >/dev/null
docker exec "$WPC" chown -R 33:33 $CTMP
t0=$(date +%s)
fixture=$(wp eval-file "$CTMP/fixture.php" /payload "$CTMP/pages.json" 2>&1 | tail -1)
echo "    fixture: $fixture ($(( $(date +%s) - t0 )) s)"
fx_pages=$(printf '%s' "$fixture" | py 'import sys,json;print(json.load(sys.stdin)["pages"])' 2>/dev/null)
fx_media=$(printf '%s' "$fixture" | py 'import sys,json;print(json.load(sys.stdin)["attachments"])' 2>/dev/null)
fx_files=$(printf '%s' "$fixture" | py 'import sys,json;print(json.load(sys.stdin)["files"])' 2>/dev/null)
check "old site: 40 pages with live ids" "${fx_pages:-?}" "40"
check "old site: 244 attachments with live ids at live paths" "${fx_media:-?}" "244"
check "old site: registry page #2910 is a plain page" "$(wpq post get 2910 --field=post_type)" "page"
check "old site: /author/hk9director/ is a real archive (200)" "$(http /author/hk9director/ | cut -d' ' -f1)" "200"
check "old site: a crawled -scaled rendition exists" "$(http /wp-content/uploads/2021/03/DSC0012-scaled.jpeg | cut -d' ' -f1)" "200"
check "old site: a crawled medium rendition exists" "$(http /wp-content/uploads/2023/05/OIP-300x261.jpg | cut -d' ' -f1)" "200"

echo "-- 3. install plugin + theme from the ZIPs, copy payload-lite, pre-flight"
docker cp "$PLUGIN_ZIP" "$WPC:$CTMP/plugin.zip" >/dev/null && docker cp "$THEME_ZIP" "$WPC:$CTMP/theme.zip" >/dev/null
docker cp payload-lite "$WPC:$CTMP/payload-lite" >/dev/null
docker exec "$WPC" chown -R 33:33 $CTMP
wpq plugin install "$CTMP/plugin.zip" --activate >/dev/null 2>&1
wpq theme install "$CTMP/theme.zip" --activate >/dev/null 2>&1
wpq rewrite flush --hard >/dev/null
plugin_version=$(wpq plugin get heartland-k9s-core --field=version)
theme_version=$(wpq theme get heartland-k9s --field=version)
check "plugin active" "$(wpq plugin get heartland-k9s-core --field=status)" "active"
check "theme active" "$(wpq theme get heartland-k9s --field=status)" "active"
pre=$(wpq hk9 preflight "$LITE_C" --format=json)
check "pre-flight ok" "$(printf '%s' "$pre" | py 'import sys,json;d=json.load(sys.stdin);print(d["ok"])')" "True"
check "pre-flight: 244 of 244 media found, 24 pages + 16 registry pages detected" "$(printf '%s' "$pre" | py 'import sys,json;d=json.load(sys.stdin);print(d["lite"]["found"], d["lite"]["total"], d["existing"]["pages"], d["existing"]["registry"])')" "244 244 24 16"

echo "-- 4. content-only payload with --adopt-existing (dry run, then import)"
run_import "$LITE_C" --adopt-existing --dry-run
check "dry run exit" "$rc" "0"
check "dry run: media_files adopt 244 / fail 0" "$(count media_files adopt)-$(count media_files fail)" "244-0"
run_import "$LITE_C" --adopt-existing
check "import exit" "$rc" "0"
import_line="media_files adopt $(count media_files adopt) create $(count media_files create) fail $(count media_files fail); posts_stub adopt $(count posts_stub adopt) create $(count posts_stub create) fail $(count posts_stub fail); redirects create $(count redirects create) fail $(count redirects fail)"
echo "    $import_line"
fails=0; for st in validate terms media_files media_sizes posts_stub posts_hierarchy reading posts_content menus options redirects finalize; do f=$(count $st fail); [ "$f" = "?" ] || fails=$((fails + f)); done
check "no step failed" "$fails" "0"
check "media_files adopt (every live attachment reused by id)" "$(count media_files adopt)" "244"
check "#2910 converted in place to hk9_barkode" "$(wpq post get 2910 --field=post_type)-$(wpq post get 2910 --field=post_name)" "hk9_barkode-larry-and-archie-service-k9"
check "page 6 still the front page" "$(wpq option get page_on_front)" "6"
# The author-archive rules are seeded by Redirects\Store (plugin source) and carried by redirects.json; a plugin ZIP
# packaged before that change lacks them — say so, add them as the seed would, and record it in the report.
if ! wpq hk9 redirects list --format=json | py 'import sys,json;d=json.load(sys.stdin);sys.exit(0 if any(r["source"]=="/author/admin/" for r in d) else 1)'; then
  notes+=("The installed plugin ZIP ($PLUGIN_ZIP, $plugin_version) predates the author-archive seed rules: the suite added \`/author/admin/\` and \`/author/hk9director/\` → \`/\` with \`wp hk9 redirects add\` (what \`Store::seeds()\` in the working tree does on activation and what \`payload-src/records/redirects.json\` carries); re-package (npm run package) and re-run to test the seeded path.")
  echo "    NOTE: plugin ZIP $plugin_version has no author-archive seed rules; adding them as the working-tree seed does"
  wpq hk9 redirects add /author/admin/ / --note="Author archive (suite: seed of the working tree)" >/dev/null
  wpq hk9 redirects add /author/hk9director/ / --note="Author archive (suite: seed of the working tree)" >/dev/null
  author_rules="added by the suite"
else
  author_rules="seeded by the plugin"
fi

echo "-- 5. every crawled / sitemapped URL against $BASE"
: > "$TMP/results.tsv"
pages_ok=0; pages_n=0; reg_ok=0; reg_n=0; auth_ok=0; auth_n=0; img_ok=0; img_n=0; cache_n=0; cache_absent=0; skipped=0; failures=()
while IFS=$'\t' read -r path type expect; do
  case "$expect" in
    skip) skipped=$((skipped+1)); continue ;;
  esac
  res=$(http "$path"); code=${res%% *}; loc=${res#* }; [ "$loc" = "$res" ] && loc=""
  okv=0
  case "$expect" in
    200) [ "$code" = "200" ] && okv=1 ;;
    301:*) target=${expect#301:}; if [ "$code" = "301" ] && [ "$loc" = "$BASE$target" ]; then
             final=$(http "$target" | cut -d' ' -f1); [ "$final" = "200" ] && okv=1 && loc="$loc → $final"; fi ;;
    absent) [ "$code" = "404" ] && okv=1 ;;
  esac
  printf '%s\t%s\t%s\t%s\t%s\n' "$path" "$type" "$code" "$loc" "$okv" >> "$TMP/results.tsv"
  case "$type" in
    page) pages_n=$((pages_n+1)); [ $okv = 1 ] && pages_ok=$((pages_ok+1)) ;;
    "registry page") reg_n=$((reg_n+1)); [ $okv = 1 ] && reg_ok=$((reg_ok+1)) ;;
    "author archive") auth_n=$((auth_n+1)); [ $okv = 1 ] && auth_ok=$((auth_ok+1)) ;;
    "image original"|"image size variant") img_n=$((img_n+1)); [ $okv = 1 ] && img_ok=$((img_ok+1)) ;;
    "FooGallery cache thumb") cache_n=$((cache_n+1)); [ $okv = 1 ] && cache_absent=$((cache_absent+1)) ;;
  esac
  [ $okv = 1 ] || [ "$type" = "FooGallery cache thumb" ] || failures+=("$path → $code $loc")
done < <(py 'import json
for u in json.load(open("'"$TMP"'/urls.json")): print("\t".join([u["path"], u["type"], u["expect"]]))')
check "pages: all 200 (19 crawled + 3 sitemap-only; /success/ + sample story 301 → /stories/)" "$pages_ok/$pages_n" "24/24"
check "registry paths: 16 × 301 → /barkode/…/ → 200" "$reg_ok/$reg_n" "16/16"
check "author archives: 2 × 301 → / ($author_rules)" "$auth_ok/$auth_n" "2/2"
check "crawled image URLs (originals, -scaled, -WxH): all 200" "$img_ok/$img_n" "126/126"
check "FooGallery cache thumbs: not in the payload (404 here; kept on disk on the real site)" "$cache_absent/$cache_n" "84/84"
echo "    skipped (host-level http→https rows, Avada/plugin/core assets): $skipped"
for f in "${failures[@]:-}"; do [ -n "$f" ] && echo "    FAIL $f"; done
check "author archive 301 carries X-Redirect-By: hk9-legacy" "$(curl -s -o /dev/null -D - -A hk9-url-coverage "$BASE/author/admin/" | grep -ic 'x-redirect-by: hk9-legacy')" "1"
check "?author=1 stays 404" "$(http '/?author=1' | cut -d' ' -f1)" "404"
check "registry record answers noindex" "$(curl -s -o /dev/null -D - -A hk9-url-coverage "$BASE/barkode/hk923-005/" | grep -ic 'x-robots-tag: noindex')" "1"

echo "-- 6. upload URLs referenced by the NEW theme output"
ref_pages=$(py 'import json
for u in json.load(open("'"$TMP"'/urls.json")):
    if u["type"]=="page" and not u["expect"].startswith("301"): print(u["path"])
    if u["type"]=="registry page" and u["expect"].startswith("301:/barkode/") and u["expect"]!="301:/barkode/": print(u["expect"][4:])')
: > "$TMP/refs.txt"
for p in $ref_pages; do
  curl -s -A hk9-url-coverage --max-time 30 "$BASE$p" > "$TMP/page.html"
  py 'import re,sys
html=open("'"$TMP"'/page.html",encoding="utf-8",errors="replace").read()
for u in re.findall(r"(?:https?://[^/\"\x27\s]+)?(/wp-content/uploads/[^\"\x27\s)>,]+)", html):
    if "*" not in u: print(u)  # skip core speculation-rules globs such as /wp-content/uploads/*' >> "$TMP/refs.txt"
done
sort -u "$TMP/refs.txt" -o "$TMP/refs.txt"
ref_total=$(grep -c . "$TMP/refs.txt"); ref_ok=0; ref_bad=()
while read -r u; do [ -z "$u" ] && continue; c=$(http "$u" | cut -d' ' -f1); if [ "$c" = "200" ]; then ref_ok=$((ref_ok+1)); else ref_bad+=("$u → $c"); fi; done < "$TMP/refs.txt"
check "every upload URL the new pages reference resolves (200)" "$ref_ok/$ref_total" "$ref_total/$ref_total"
check "none of them is a FooGallery cache path" "$(grep -c '/uploads/cache/' "$TMP/refs.txt")" "0"
check "the new output references upload URLs (attachments rendered by id)" "$(( ref_total > 50 ))" "1"
for b in "${ref_bad[@]:-}"; do [ -n "$b" ] && echo "    FAIL $b"; done
crawled_reused=$(py 'import json
refs=set(open("'"$TMP"'/refs.txt").read().split())
print(sum(1 for u in json.load(open("'"$TMP"'/urls.json")) if u["type"].startswith("image ") and u["path"] in refs))')
echo "    $crawled_reused of the 126 crawled image files are referenced verbatim by the new output (the rest are rendered through other sizes of the same attachments)"

echo "-- 7. url-matrix + debug.log"
matrix=$(bash tools/url-matrix.sh "$BASE" 2>/dev/null); mrc=$?
m_ok=$(printf '%s\n' "$matrix" | grep -c '✅'); m_bad=$(printf '%s\n' "$matrix" | grep -c '❌')
check "tools/url-matrix.sh on the fresh stack: exit 0" "$mrc" "0"
check "tools/url-matrix.sh: 0 ❌" "$m_bad" "0"
printf '%s\n' "$matrix" | grep '❌' | sed 's/^/    /'
dbg=$(docker exec "$WPC" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null' | grep -vE 'Deprecated|Notice|hk9-tmp' | grep -c 'PHP ')
check "debug.log: no PHP warnings/errors" "$dbg" "0"

echo "-- 8. record the numbers in docs/reports/url-coverage.md"
py '
import json, datetime, sys
res = {}
for line in open("'"$TMP"'/results.tsv"):
    p, t, code, loc, okv = line.rstrip("\n").split("\t")
    res[p] = {"status": int(code) if code.isdigit() else code, "location": loc, "ok": okv == "1"}
out = {
  "ran_at": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
  "base": "'"$BASE"'", "wp_version": "'"$wp_version"'", "plugin_version": "'"$plugin_version"'", "theme_version": "'"$theme_version"'",
  "zips": "'"$PLUGIN_ZIP"' + '"$THEME_ZIP"'",
  "fixture": {"pages": '"${fx_pages:-0}"', "attachments": '"${fx_media:-0}"', "files": '"${fx_files:-0}"'},
  "import": "'"$import_line"'",
  "summary": {
    "pages (19 crawled + 3 sitemap-only → 200; /success/ + sample story → 301 /stories/)": "'"$pages_ok/$pages_n"'",
    "registry paths (16 × 301 → /barkode/…/ → 200)": "'"$reg_ok/$reg_n"'",
    "author archives (301 → /; '"$author_rules"')": "'"$auth_ok/$auth_n"'",
    "crawled image URLs: originals + -scaled + -WxH → 200": "'"$img_ok/$img_n"'",
    "FooGallery /uploads/cache/ thumbs: absent here (not in the payload; kept on disk on the real site)": "'"$cache_absent/$cache_n"'",
    "upload URLs referenced by the new theme output → 200 (none under /uploads/cache/)": "'"$ref_ok/$ref_total"'",
    "crawled image files referenced verbatim by the new output": "'"$crawled_reused"' of 126",
    "tools/url-matrix.sh": "'"$m_ok"' ✅ / '"$m_bad"' ❌",
    "debug.log PHP warnings/errors": "'"$dbg"'",
    "suite checks": "'"$pass"' passed, '"$fail"' failed"
  },
  "notes": json.loads(sys.argv[1]),
  "results": res,
}
json.dump(out, open("'"$TMP"'/results.json", "w"))
' "$(py 'import json,sys;print(json.dumps([n for n in sys.argv[1:] if n]))' "${notes[@]:-}")"
node tools/url-coverage.mjs --suite="$TMP/results.json" | sed 's/^/    /'
echo "== $pass passed, $fail failed =="
[ "$fail" -eq 0 ]
