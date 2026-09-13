#!/usr/bin/env bash
# "Existing site" migration simulation against the LOCAL docker stack (never a real site).
#
#   bash plugin/heartland-k9s-core/tests/adopt-existing-suite.sh
#
# 1. resets the local site (tools/reset-local-site.sh — empties it),
# 2. rebuilds the shape of the client's live Avada site with the LIVE post/attachment ids and
#    slugs the payload keys reference (tests/adopt-existing-oldsite.php),
# 3. runs the real payload with --adopt-existing (dry run, import, re-run), rolls it back, imports
#    again, and asserts: adopt counts, no "-2" slugs, ids/URLs preserved, registry pages converted
#    to BarKode records (+ legacy path 301), live attachments reused (both halves of a duplicated
#    live upload by their own id), a failed conversion leaves the page + template intact, rollback
#    restores the old site exactly, the fresh import afterwards succeeds, and a converted record
#    whose map row is lost (crash window) is re-adopted instead of duplicated.
#
# The site is left EMPTY apart from the fixture at the end; re-populate it with
#   bash tools/reset-local-site.sh && tools/wp.sh hk9 import /var/www/html/wp-content/hk9-payload --user=admin
set -uo pipefail
cd "$(dirname "$0")/../../.."
WP="tools/wp.sh ${HK9_WP_FLAGS:-}"
PAYLOAD_C=/var/www/html/wp-content/hk9-payload
PLUGIN_C=/var/www/html/wp-content/plugins/heartland-k9s-core
SITE="${HK9_SITE_URL:-http://localhost:8093}"
pass=0; fail=0; last=""
ok()   { pass=$((pass+1)); printf '  PASS  %s\n' "$1"; }
bad()  { fail=$((fail+1)); printf '  FAIL  %s\n' "$1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 (got '$2', want '$3')"; fi; }
wp()   { $WP "$@" 2>/dev/null; }
run_import() { last=$($WP hk9 import "$@" --user=admin --quiet-progress 2>&1); rc=$?; }
count() { # column looked up by header name in the tab-separated summary
  local v; v=$(printf '%s\n' "$last" | awk -F'\t' -v s="$1" -v name="$2" '
    $1 == "step" { for (i = 1; i <= NF; i++) if ($i == name) col = i; next }
    $1 == s && col { print $col; exit }')
  printf '%s' "${v:-?}"
}
run_id() { printf '%s\n' "$last" | sed -n 's/.*Run \([0-9a-z-]*\) status.*/\1/p' | head -1; }
map_id()  { wp eval 'echo (int) (HK9\Core\Import\Map::get("'"$1"'")["object_id"] ?? 0);'; }
map_kind(){ wp eval '$r = HK9\Core\Import\Map::get("'"$1"'"); echo $r ? (null === $r["created_by_run"] ? "adopted" : "created") : "none";'; }
http()    { curl -s -o /dev/null -w '%{http_code}' -A hk9-adopt-suite --max-time 30 "$SITE$1"; }
location(){ curl -s -o /dev/null -D - -A hk9-adopt-suite --max-time 30 "$SITE$1" | grep -i '^location:' | head -1 | sed 's/^[Ll]ocation: *//' | tr -d '\r'; }
has_fusion() { wp eval 'echo (int) str_contains((string) get_post('"$1"')->post_content, "[fusion_");'; }
has_blocks() { wp eval 'echo (int) str_contains((string) get_post('"$1"')->post_content, "<!-- wp:");'; }
dup_slugs()  { # pages whose slug is a payload page slug with a numeric suffix (WordPress "-2" duplicates)
  wp eval '$m = json_decode(file_get_contents("'"$PAYLOAD_C"'/manifest.json"), true); $slugs = []; foreach ($m["records"] as $r) { if (in_array($r["type"], ["page","hk9_barkode"], true)) { $slugs[] = preg_quote(sanitize_title($r["slug"]), "/"); } } $re = "/^(" . implode("|", $slugs) . ")-[0-9]+$/"; $n = 0; foreach (get_posts(["post_type"=>["page","hk9_barkode"],"post_status"=>"any","numberposts"=>-1,"fields"=>"ids"]) as $id) { if (preg_match($re, (string) get_post($id)->post_name)) { $n++; } } echo $n;'
}

echo "== existing-site (adopt) suite =="
wp eval 'if ( ! defined("HK9_LOCAL_DEV") ) { exit(1); }' || { echo "Refusing: not the local dev stack."; exit 1; }

echo "-- 0. reset + old site"
bash tools/reset-local-site.sh >/dev/null 2>&1
fixture=$(wp eval-file "$PLUGIN_C/tests/adopt-existing-oldsite.php" "$PAYLOAD_C" 2>&1 | tail -1)
check "old site built" "$(printf '%s' "$fixture" | python3 -c 'import sys,json; d=json.load(sys.stdin); print(d["pages"], d["media"], d["menu_items"], d["page_on_front"], d["show_on_front"])' 2>/dev/null)" "10 6 3 6 page"
check "page 6 carries Avada content" "$(has_fusion 6)" "1"
check "page 2910 is a page" "$(wp post get 2910 --field=post_type)" "page"
old_att=$(wp post list --post_type=attachment --format=count)
old_pages=$(wp post list --post_type=page --post_status=any --format=count)
old_menu=$(wp eval 'echo (int) (wp_get_nav_menu_object("Old Menu")->term_id ?? 0);')
old_6_tpl=$(wp post meta get 6 _wp_page_template)
old_2910_content=$(wp post get 2910 --field=post_content | shasum | cut -c1-12)
old_1963_alt=$(wp post meta get 1963 _wp_attachment_image_alt)
old_3028_file=$(wp post meta get 3028 _wp_attached_file)
old_3028_sizes=$(wp eval 'echo count((array) (wp_get_attachment_metadata(3028)["sizes"] ?? []));')

echo "-- 0b. a conversion that fails leaves the registry page a page, with its template"
probe=$(wp eval-file "$PLUGIN_C/tests/adopt-existing-convert-fail.php" "$PAYLOAD_C" live:barkode:2910 2910 2>&1 | tail -1)
probe_get() { printf '%s' "$probe" | python3 -c 'import sys,json; d=json.load(sys.stdin); print(d["'"$1"'"])' 2>/dev/null; }
check "probe: conversion failed (1 fail, 0 adopt)" "$(probe_get fail_count)-$(probe_get adopt_count)" "1-0"
check "probe: error withheld (sensitive record)" "$(printf '%s' "$(probe_get error)" | grep -c 'details withheld')" "1"
check "probe: #2910 still a page" "$(probe_get post_type)" "page"
check "probe: #2910 template kept" "$(probe_get template)" "100-width.php"
check "probe: no map row left" "$(probe_get map_row)" "none"
check "probe: no source marker left" "$(probe_get marker)" ""
check "probe: #2910 still a page (db)" "$(wp post get 2910 --field=post_type)" "page"

echo "-- 1. pre-flight detects the existing content"
pre=$($WP hk9 preflight "$PAYLOAD_C" --format=json 2>/dev/null)
check "preflight ok" "$(printf '%s' "$pre" | python3 -c 'import sys,json; d=json.load(sys.stdin); print(d["ok"])')" "True"
check "preflight existing pages/registry/media" "$(printf '%s' "$pre" | python3 -c 'import sys,json; e=json.load(sys.stdin)["existing"]; print(e["pages"], e["registry"], e["media"])')" "7 2 5"
check "preflight table prints the existing line" "$($WP hk9 preflight "$PAYLOAD_C" 2>/dev/null | grep -c 'Existing content: 7 page(s), 2 legacy BarKode page(s), 5 attachment(s)')" "1"
check "preflight: legacy builder plugins check passes (none active)" "$(printf '%s' "$pre" | python3 -c 'import sys,json; c=[c for c in json.load(sys.stdin)["checks"] if c["id"]=="legacy_plugins"]; print(c[0]["status"] if c else "missing")')" "pass"
# A stub "FooGallery" plugin activated in the container must turn that check into a warning (pre-flight still ok).
docker exec hk9-wordpress-1 sh -c 'mkdir -p /var/www/html/wp-content/plugins/foogallery && printf "<?php\n/*\nPlugin Name: FooGallery (stub)\n*/\n" > /var/www/html/wp-content/plugins/foogallery/foogallery.php && chown -R www-data:www-data /var/www/html/wp-content/plugins/foogallery' >/dev/null 2>&1
wp plugin activate foogallery >/dev/null 2>&1
pre_stub=$($WP hk9 preflight "$PAYLOAD_C" --format=json 2>/dev/null)
check "preflight: active legacy plugin -> warn naming it" "$(printf '%s' "$pre_stub" | python3 -c 'import sys,json; c=[c for c in json.load(sys.stdin)["checks"] if c["id"]=="legacy_plugins"][0]; print(c["status"], "FooGallery (stub)" in c["detail"], "plugins.php" in c.get("action",{}).get("url",""))')" "warn True True"
check "preflight: still ok with the warning" "$(printf '%s' "$pre_stub" | python3 -c 'import sys,json; print(json.load(sys.stdin)["ok"])')" "True"
check "preflight table prints NOTE for it" "$($WP hk9 preflight "$PAYLOAD_C" 2>/dev/null | grep -c 'NOTE  Legacy builder plugins')" "1"
wp plugin deactivate foogallery >/dev/null 2>&1
wp plugin delete foogallery >/dev/null 2>&1
docker exec hk9-wordpress-1 rm -rf /var/www/html/wp-content/plugins/foogallery >/dev/null 2>&1
check "stub plugin removed again" "$(wp plugin list --field=name 2>/dev/null | grep -c '^foogallery$')" "0"

log_count() { docker exec hk9-wordpress-1 grep -c "$1" "$(printf '%s\n' "$last" | sed -n 's/^Log: //p' | head -1)" 2>/dev/null | tr -d '[:space:]'; }

echo "-- 2. dry run with --adopt-existing"
run_import "$PAYLOAD_C" --adopt-existing --dry-run
check "dry run exit" "$rc" "0"
check "dry run posts_stub adopt" "$(count posts_stub adopt)" "9"
check "dry run posts_stub create (the rest)" "$(count posts_stub create)" "70"
check "dry run posts_hierarchy adopt" "$(count posts_hierarchy adopt)" "9"
check "dry run posts_content adopt" "$(count posts_content adopt)" "9"
check "dry run media_files adopt (5 by id + 1 by sha256)" "$(count media_files adopt)" "6"
check "dry run media_files create" "$(count media_files create)" "235"
check "dry run: both halves of the live pair adopted by id, neither shared" "$(log_count 'live:media:2149 ADOPT #2149 by id')-$(log_count 'live:media:2806 ADOPT #2806 by id')-$(log_count 'live:media:2806 SKIP would share')" "1-1-0"
check "dry run: the shared logo record would share #3028" "$(log_count 'ref:asset:heartland-k9s-logo SKIP would share the attachment bound to live:media:3028')" "1"
check "dry run fail total" "$(( $(count validate fail) + $(count posts_stub fail) + $(count posts_hierarchy fail) + $(count posts_content fail) + $(count media_files fail) ))" "0"
check "dry run warns about the renamed page #2072" "$(log_count 'Page #2072 exists but its slug')" "1"
check "dry run wrote nothing (pages)" "$(wp post list --post_type=page --post_status=any --format=count)" "$old_pages"
check "dry run wrote nothing (attachments)" "$(wp post list --post_type=attachment --format=count)" "$old_att"

echo "-- 3. import with --adopt-existing"
run_import "$PAYLOAD_C" --adopt-existing
check "import exit" "$rc" "0"
run1=$(run_id)
check "posts_stub adopt" "$(count posts_stub adopt)" "9"
check "posts_hierarchy adopt" "$(count posts_hierarchy adopt)" "9"
check "posts_content adopt" "$(count posts_content adopt)" "9"
check "media_files adopt" "$(count media_files adopt)" "6"
check "media_files create" "$(count media_files create)" "235"
check "no sha256 mismatch warnings (live files are the payload's bytes)" "$(log_count 'file bytes differ from the payload')" "0"
check "no record errors" "$(printf '%s\n' "$last" | grep -c 'record error')" "0"
check "no duplicate '-N' page slugs" "$(dup_slugs)" "0"
for id in 6 16 2032 2944 3 2483 9999; do
  check "page #$id still a page" "$(wp post get "$id" --field=post_type)" "page"
  check "page #$id has no Avada shortcodes" "$(has_fusion "$id")" "0"
done
check "page #16 has block content" "$(has_blocks 16)" "1"
check "page #3 has block content" "$(has_blocks 3)" "1"
check "page #6 slug" "$(wp post get 6 --field=post_name)" "home"
check "page #6 template" "$(wp post meta get 6 _wp_page_template)" "page-templates/home.php"
check "page #6 is page_on_front" "$(wp option get page_on_front)-$(wp option get show_on_front)" "6-page"
check "front page renders (HTTP 200)" "$(http /)" "200"
check "live:page:16 -> #16 adopted" "$(map_id live:page:16)-$(map_kind live:page:16)" "16-adopted"
check "ref:page:home -> #6 adopted" "$(map_id ref:page:home)-$(map_kind ref:page:home)" "6-adopted"
check "ref:page:contact -> #2032 adopted" "$(map_id ref:page:contact)" "2032"
check "live:page:2120 (donate) -> #9999 by slug" "$(map_id live:page:2120)" "9999"
check "live:page:2072 not adopted (slug differs) -> new page" "$(wp eval 'echo (int) (HK9\Core\Import\Map::get("live:page:2072")["object_id"] !== 2072);')" "1"
check "renamed old page #2072 untouched" "$(wp post get 2072 --field=post_name)" "back-the-pack-old"
check "trashed page #2124 untouched" "$(wp post get 2124 --field=post_status)" "trash"
check "Avada meta on adopted page kept" "$(wp post meta get 6 pyre_page_title)" "yes"
check "page #6 date kept" "$(wp post get 6 --field=post_date)" "2021-01-01 10:00:00"
# registry pages converted in place
for id in 2910 3675; do
  check "#$id is now hk9_barkode" "$(wp post get "$id" --field=post_type)" "hk9_barkode"
  check "#$id has no Avada shortcodes" "$(has_fusion "$id")" "0"
done
check "#2910 slug kept" "$(wp post get 2910 --field=post_name)" "larry-and-archie-service-k9"
check "#3675 slug kept" "$(wp post get 3675 --field=post_name)" "barkode-mosby-hk9t26-01"
check "live:barkode:2910 -> #2910 adopted" "$(map_id live:barkode:2910)-$(map_kind live:barkode:2910)" "2910-adopted"
check "no second record with that slug" "$(wp post list --post_type=hk9_barkode --post_status=any --name=larry-and-archie-service-k9 --format=count)" "1"
check "/larry-and-archie-service-k9/ -> 301" "$(http /larry-and-archie-service-k9/)" "301"
check "... to /barkode/larry-and-archie-service-k9/" "$(location /larry-and-archie-service-k9/)" "$SITE/barkode/larry-and-archie-service-k9/"
check "/barkode/larry-and-archie-service-k9/ -> 200" "$(http /barkode/larry-and-archie-service-k9/)" "200"
check "record is noindex" "$(curl -s -D - -o /dev/null -A hk9-adopt-suite "$SITE/barkode/larry-and-archie-service-k9/" | grep -ci 'x-robots-tag: noindex')" "1"
check "/barkode-mosby-hk9t26-01/ -> 301" "$(http /barkode-mosby-hk9t26-01/)" "301"
# attachments reused
check "live:media:3028 -> #3028 adopted" "$(map_id live:media:3028)-$(map_kind live:media:3028)" "3028-adopted"
check "live:media:1942 -> #1942" "$(map_id live:media:1942)" "1942"
check "live:media:1963 -> #1963" "$(map_id live:media:1963)" "1963"
check "live:media:1961 -> #555 (sha256 fallback)" "$(map_id live:media:1961)-$(map_kind live:media:1961)" "555-adopted"
check "live:media:2149 -> #2149 (own id)" "$(map_id live:media:2149)-$(map_kind live:media:2149)" "2149-adopted"
check "live:media:2806 -> #2806 (own id, not shared with 2149)" "$(map_id live:media:2806)-$(map_kind live:media:2806)" "2806-adopted"
check "#2806 is its own owner" "$(wp eval 'echo (string) (HK9\Core\Import\Map::owner("attachment", 2806)["source_key"] ?? "");')" "live:media:2806"
check "{{media:live:media:2806}} resolves to #2806" "$(wp eval '$m = HK9\Core\Import\Manifest::load("'"$PAYLOAD_C"'"); $t = new HK9\Core\Import\Tokens($m, false); echo (int) $t->resolve("media", "live:media:2806");')" "2806"
check "#2149 alt kept as-is" "$(wp post meta get 2149 _wp_attachment_image_alt)" "Heartland Canines for Veterans"
check "#3028 file not re-uploaded" "$(wp post meta get 3028 _wp_attached_file)" "$old_3028_file"
check "#3028 missing sizes filled (not regenerated)" "$(wp eval 'echo (int) (count((array) (wp_get_attachment_metadata(3028)["sizes"] ?? [])) > '"$old_3028_sizes"');')" "1"
check "#1963 alt kept as-is" "$(wp post meta get 1963 _wp_attachment_image_alt)" "$old_1963_alt"
check "one attachment file 2023/05/Concept-1-rocker-outlined-2.png" "$(wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = \"_wp_attached_file\" AND meta_value LIKE \"%/Concept-1-rocker-outlined-2.png\"");')" "1"
check "attachment slug 'about' freed for the About page" "$(wp post get 1963 --field=post_name)" "media-about"
check "About page published at /about/ (not about-2)" "$(wp eval 'echo (string) get_post((int) (HK9\Core\Import\Map::get("ref:page:about")["object_id"] ?? 0))->post_name;')" "about"
check "shared logo record bound to #3028" "$(wp eval '$keys = []; foreach (HK9\Core\Import\Map::rows_for_object("attachment", 3028) as $r) { $keys[] = $r["source_key"]; } echo count($keys) >= 2 ? "shared" : "single";')" "shared"
check "attachments total = 6 old + 235 new" "$(wp post list --post_type=attachment --format=count)" "241"
check "Old Menu still exists with 3 items" "$(wp eval '$m = wp_get_nav_menu_object("Old Menu"); echo $m ? count(wp_get_nav_menu_items($m->term_id) ?: []) : 0;')" "3"
check "primary location now the payload menu" "$(wp eval '$l = get_theme_mod("nav_menu_locations"); echo (int) ((int) ($l["primary"] ?? 0) !== '"$old_menu"');')" "1"
check "run log has ADOPT lines" "$(docker exec hk9-wordpress-1 grep -c ' ADOPT #' "/var/www/html/wp-content/uploads/hk9-import/$run1.log")" "$((9 * 3 + 6))"
check "ADOPT lines withhold registry slugs" "$(docker exec hk9-wordpress-1 grep ' ADOPT #' "/var/www/html/wp-content/uploads/hk9-import/$run1.log" | grep -c 'larry-and-archie\|mosby')" "0"
check "url matrix passes" "$(bash tools/url-matrix.sh "$SITE" >/dev/null 2>&1 && echo pass || echo fail)" "pass"

echo "-- 4. re-run: everything skips"
run_import "$PAYLOAD_C" --adopt-existing
check "re-run exit" "$rc" "0"
total=0; for s in terms media_files media_sizes posts_stub posts_hierarchy reading posts_content menus options redirects; do for k in create adopt update conflict fail; do total=$((total + $(count $s $k))); done; done
check "re-run create+adopt+update+conflict+fail" "$total" "0"
run_import "$PAYLOAD_C"
total=0; for s in terms media_files media_sizes posts_stub posts_hierarchy reading posts_content menus options redirects; do for k in create adopt update conflict fail; do total=$((total + $(count $s $k))); done; done
check "re-run without the flag also skips" "$total" "0"

echo "-- 5. rollback restores the old site"
out=$($WP hk9 rollback --run="$run1" --yes --user=admin 2>&1)
check "rollback ran" "$(printf '%s\n' "$out" | grep -c 'Success: Rollback:')" "1"
check "rollback skipped nothing" "$(printf '%s\n' "$out" | grep -c 'Warning: skipped')" "0"
check "rollback un-adopted the bound objects" "$(printf '%s\n' "$out" | grep -c '^  unadopted ')" "16"
for id in 6 16 2032 2944 3 2483; do
  check "page #$id back to Avada content" "$(has_fusion "$id")" "1"
done
check "page #6 template restored" "$(wp post meta get 6 _wp_page_template)" "$old_6_tpl"
check "page #6 still page_on_front" "$(wp option get page_on_front)-$(wp option get show_on_front)" "6-page"
check "#2910 back to a page" "$(wp post get 2910 --field=post_type)" "page"
check "#2910 old content back" "$(wp post get 2910 --field=post_content | shasum | cut -c1-12)" "$old_2910_content"
check "#2910 slug kept" "$(wp post get 2910 --field=post_name)" "larry-and-archie-service-k9"
check "#2910 template restored" "$(wp post meta get 2910 _wp_page_template)" "100-width.php"
check "#2910 no registry meta left" "$(wp eval 'echo metadata_exists("post", 2910, "hk9_dog_name") ? "present" : "absent";')" "absent"
check "#3675 back to a page" "$(wp post get 3675 --field=post_type)" "page"
check "adopted pages un-adopted (no map row)" "$(wp eval 'echo (int) (bool) HK9\Core\Import\Map::get("live:page:16") + (int) (bool) HK9\Core\Import\Map::get("live:barkode:2910") + (int) (bool) HK9\Core\Import\Map::get("ref:page:home");')" "0"
check "source marker removed from #16" "$(wp eval 'echo metadata_exists("post", 16, "_hk9_source_key") ? "present" : "absent";')" "absent"
check "attachments back to the 6 old ones" "$(wp post list --post_type=attachment --format=count)" "$old_att"
check "#3028 kept" "$(wp post get 3028 --field=post_type)" "attachment"
check "#555 kept" "$(wp post get 555 --field=post_type)" "attachment"
check "pages back to the old count" "$(wp post list --post_type=page --post_status=any --format=count)" "$old_pages"
check "Old Menu intact" "$(wp eval '$m = wp_get_nav_menu_object("Old Menu"); echo $m ? count(wp_get_nav_menu_items($m->term_id) ?: []) : 0;')" "3"
check "primary location back to Old Menu" "$(wp eval '$l = get_theme_mod("nav_menu_locations"); echo (int) ($l["primary"] ?? 0);')" "$old_menu"
check "payload menus gone" "$(wp eval 'echo wp_get_nav_menu_object("Primary") ? "present" : "gone";')" "gone"
check "front page still renders" "$(http /)" "200"

echo "-- 6. fresh import again after the rollback"
run_import "$PAYLOAD_C" --adopt-existing
check "second import exit" "$rc" "0"
check "second import adopts again" "$(count posts_stub adopt)" "9"
check "second import media adopt" "$(count media_files adopt)" "6"
check "no duplicate '-N' page slugs" "$(dup_slugs)" "0"
check "#2910 converted again" "$(wp post get 2910 --field=post_type)" "hk9_barkode"
check "/larry-and-archie-service-k9/ -> 301 again" "$(http /larry-and-archie-service-k9/)" "301"
check "front page renders" "$(http /)" "200"
check "url matrix passes again" "$(bash tools/url-matrix.sh "$SITE" >/dev/null 2>&1 && echo pass || echo fail)" "pass"

echo "-- 7. crash window: a converted record whose map row is gone is re-adopted, not duplicated"
wp eval 'HK9\Core\Import\Map::delete("live:barkode:2910"); delete_post_meta(2910, "_hk9_source_key"); delete_post_meta(2910, "_hk9_import_run"); clean_post_cache(2910);' >/dev/null
check "row + marker removed (#2910 still hk9_barkode)" "$(map_kind live:barkode:2910)-$(wp post get 2910 --field=post_type)" "none-hk9_barkode"
pre=$($WP hk9 preflight "$PAYLOAD_C" --format=json 2>/dev/null)
check "preflight counts it as a registry record again" "$(printf '%s' "$pre" | python3 -c 'import sys,json; e=json.load(sys.stdin)["existing"]; print(e["pages"], e["registry"], e["media"])')" "0 1 0"
run_import "$PAYLOAD_C" --adopt-existing --dry-run
check "dry run: 1 adopt, 0 create in posts_stub" "$(count posts_stub adopt)-$(count posts_stub create)" "1-0"
check "dry run: no conversion, ADOPT lines withhold the slug" "$(log_count 'converted to hk9_barkode')-$(log_count ' ADOPT #.*larry-and-archie')" "0-0"
check "dry run: the posts_stub ADOPT line carries the id only" "$(log_count '\[posts_stub\] sensitive#[0-9a-f]* ADOPT #2910$')" "1"
run_import "$PAYLOAD_C" --adopt-existing
check "import exit" "$rc" "0"
check "posts_stub adopt 1 / create 0 / fail 0" "$(count posts_stub adopt)-$(count posts_stub create)-$(count posts_stub fail)" "1-0-0"
check "live:barkode:2910 -> #2910 adopted again" "$(map_id live:barkode:2910)-$(map_kind live:barkode:2910)" "2910-adopted"
check "#2910 still hk9_barkode with its slug" "$(wp post get 2910 --field=post_type)-$(wp post get 2910 --field=post_name)" "hk9_barkode-larry-and-archie-service-k9"
check "still exactly one record with that slug" "$(wp post list --post_type=hk9_barkode --post_status=any --name=larry-and-archie-service-k9 --format=count)" "1"
check "no duplicate '-N' slugs" "$(dup_slugs)" "0"
check "/barkode/larry-and-archie-service-k9/ -> 200" "$(http /barkode/larry-and-archie-service-k9/)" "200"
run_import "$PAYLOAD_C" --adopt-existing
total=0; for s in terms media_files media_sizes posts_stub posts_hierarchy reading posts_content menus options redirects; do for k in create adopt update conflict fail; do total=$((total + $(count $s $k))); done; done
check "re-run after the re-adoption skips everything" "$total" "0"

echo "== $pass passed, $fail failed =="
[ "$fail" -eq 0 ]
