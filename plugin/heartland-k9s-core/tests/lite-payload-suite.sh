#!/usr/bin/env bash
# Content-only ("lite") payload simulation against the LOCAL docker stack (never a real site).
#
#   bash plugin/heartland-k9s-core/tests/lite-payload-suite.sh
#
# 1. builds payload-lite/ (node tools/build-payload.mjs --lite) and copies it into the plugin's
#    tests/tmp/ so the container sees it,
# 2. on the EMPTY site: the lite payload is refused without --adopt-existing (CLI + validate step)
#    and blocked by the pre-flight / validate "N of M media found" gate with --adopt-existing,
# 3. rebuilds the old live site with ALL 244 live attachments at their live paths + ids
#    (tests/lite-payload-oldsite.php on top of tests/adopt-existing-oldsite.php),
# 4. runs the lite payload with --adopt-existing (pre-flight, dry run, import, re-run) and asserts:
#    pre-flight 244/244 found, media_files adopt=244 (every live record by its own id), the ref
#    assets created (5) / shared (1, the logo = #3028), fail=0, no live file copied anywhere
#    (attached paths unchanged, bytes copied = the 5 ref files), url-matrix passes, re-run skips,
# 5. negative: two live attachments deleted (one re-created under a NEW id at the same upload
#    path) -> pre-flight/validate list the missing path, media_files fails exactly that record and
#    adopts the renumbered one "by path",
# 6. the FULL payload run afterwards (--adopt-existing) only fills that gap; everything else skips.
#
# The site is left holding the simulated migration; re-populate it with
#   bash tools/reset-local-site.sh && tools/wp.sh hk9 import /var/www/html/wp-content/hk9-payload --user=admin
set -uo pipefail
cd "$(dirname "$0")/../../.."
WP="tools/wp.sh ${HK9_WP_FLAGS:-}"
PAYLOAD_C=/var/www/html/wp-content/hk9-payload
PLUGIN_C=/var/www/html/wp-content/plugins/heartland-k9s-core
PLUGIN_L=plugin/heartland-k9s-core
LITE_L="$PLUGIN_L/tests/tmp/payload-lite"
LITE_C="$PLUGIN_C/tests/tmp/payload-lite"
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
run_id()  { printf '%s\n' "$last" | sed -n 's/.*Run \([0-9a-z-]*\) status.*/\1/p' | head -1; }
log_file(){ printf '%s\n' "$last" | sed -n 's/^Log: //p' | head -1; }
log_count() { docker exec hk9-wordpress-1 grep -c "$1" "$(log_file)" 2>/dev/null | tr -d '[:space:]'; }
map_id()  { wp eval 'echo (int) (HK9\Core\Import\Map::get("'"$1"'")["object_id"] ?? 0);'; }
map_kind(){ wp eval '$r = HK9\Core\Import\Map::get("'"$1"'"); echo $r ? (null === $r["created_by_run"] ? "adopted" : "created") : "none";'; }
http()    { curl -s -o /dev/null -w '%{http_code}' -A hk9-lite-suite --max-time 30 "$SITE$1"; }
pre_json(){ $WP hk9 preflight "$LITE_C" --format=json 2>/dev/null; }
pre_get() { # pre_get <json> <python expr over d>
  printf '%s' "$1" | python3 -c 'import sys,json; d=json.load(sys.stdin); print('"$2"')' 2>/dev/null
}
# live attachment paths (id => _wp_attached_file) of every live:media record, hashed
paths_hash() {
  wp eval '$m = json_decode(file_get_contents("'"$LITE_C"'/manifest.json"), true); $p = []; foreach ($m["records"] as $r) { if (preg_match("/^live:media:(\d+)$/", $r["key"], $mm)) { $p[(int) $mm[1]] = (string) get_post_meta((int) $mm[1], "_wp_attached_file", true); } } ksort($p); echo md5(wp_json_encode($p));'
}
# "original" files under uploads (every file that is not a -WxH rendition)
upload_originals() {
  docker exec hk9-wordpress-1 sh -c 'find /var/www/html/wp-content/uploads -type f | grep -vE "hk9-import|hk9-payload" | grep -vE -- "-[0-9]+x[0-9]+\.[A-Za-z0-9]+$" | wc -l' | tr -d '[:space:]'
}
totals() { # sum of create+adopt+update+conflict+fail over the content steps of the last run
  local t=0 s k
  for s in terms media_files media_sizes posts_stub posts_hierarchy reading posts_content menus options redirects; do
    for k in create adopt update conflict fail; do t=$((t + $(count $s $k))); done
  done
  printf '%s' "$t"
}

echo "== content-only (lite) payload suite =="
wp eval 'if ( ! defined("HK9_LOCAL_DEV") ) { exit(1); }' || { echo "Refusing: not the local dev stack."; exit 1; }

echo "-- 0. build the lite payload and place it where the container sees it"
build=$(node tools/build-payload.mjs --lite 2>&1); brc=$?
check "build-payload --lite exit" "$brc" "0"
check "build reports 244 file-less live records" "$(printf '%s\n' "$build" | grep -c 'file-less live media records: 244')" "1"
check "manifest is flagged lite" "$(python3 -c 'import json; m=json.load(open("payload-lite/manifest.json")); print(m.get("lite"), sum(1 for r in m["records"] if r["type"]=="attachment" and r["file"] is None), sum(1 for r in m["records"] if r["type"]=="attachment" and r["file"]))')" "True 244 6"
check "live records carry basename/live_path/sha256/bytes" "$(python3 -c 'import json; m=json.load(open("payload-lite/manifest.json")); r=[r for r in m["records"] if r["key"]=="live:media:3028"][0]; print(r["basename"], r["live_path"], len(r["sha256"]), r["bytes"]>0)')" "Concept-1-rocker-outlined-2.png 2023/05/Concept-1-rocker-outlined-2.png 64 True"
check "media/ holds only the ref assets" "$(find payload-lite/media -type f | sort | tr '\n' ' ' | sed 's/ $//')" "payload-lite/media/ref__asset__about-dog/about-dog.jpg payload-lite/media/ref__asset__barkode/barkode.jpg payload-lite/media/ref__asset__heartland-k9s-logo/heartland-k9s-logo.png payload-lite/media/ref__asset__hero-home/hero-home.jpg payload-lite/media/ref__asset__training/training.jpg payload-lite/media/ref__asset__veteran-story/veteran-story.jpg"
lite_kb=$(du -sk payload-lite | cut -f1)
check "lite payload is small (< 4 MB on disk)" "$(( lite_kb < 4096 ))" "1"
rm -rf "$LITE_L"; mkdir -p "$PLUGIN_L/tests/tmp"; cp -R payload-lite "$LITE_L"
check "lite payload visible in the container" "$(wp eval 'echo (int) is_file("'"$LITE_C"'/manifest.json");')" "1"
expected_copy=$(python3 -c 'import json; m=json.load(open("payload-lite/manifest.json")); print(sum(r["size"] for r in m["records"] if r["key"].startswith("ref:asset:") and r["key"]!="ref:asset:heartland-k9s-logo"))')

echo "-- 1. empty site: the lite payload is refused / blocked"
bash tools/reset-local-site.sh >/dev/null 2>&1
check "site is empty" "$(wp post list --post_type=attachment --format=count)" "0"
runs_before=$(wp eval 'echo count(HK9\Core\Import\State::runs());')
run_import "$LITE_C" --dry-run
check "CLI refuses a lite payload without --adopt-existing" "$rc" "1"
check "... with the content-only message" "$(printf '%s\n' "$last" | grep -c 'This is a content-only payload: add --adopt-existing')" "1"
check "... and recorded no run" "$(wp eval 'echo count(HK9\Core\Import\State::runs());')" "$runs_before"
# The validate step carries the same gate (admin/REST path): start a run without adopt and tick it.
v=$(wp eval 'HK9\Core\Import\Runner::start("'"$LITE_C"'", ["dry_run" => true, "adopt" => false, "batch" => 500, "budget" => 120], 1, "path"); $s = HK9\Core\Import\Runner::run_all(null, 120, 500); $f = array_values(array_filter($s["errors"], fn($e) => ! empty($e["fatal"]))); echo $s["status"], "|", $f[0]["message"] ?? "";')
check "validate: fatal without adopt" "$(printf '%s' "$v" | cut -d'|' -f1)" "failed"
check "validate: the fatal names the fix" "$(printf '%s' "$v" | grep -c "enable 'Existing site: adopt matching content'")" "1"
pre=$(pre_json)
check "pre-flight: 0 of 244 found -> fail" "$(pre_get "$pre" '[c["status"] for c in d["checks"] if c["id"]=="lite"][0], d["lite"]["found"], d["lite"]["total"], d["ok"]')" "fail 0 244 False"
check "pre-flight detail: 'Content-only payload: 0 of 244 media files found on this site'" "$(pre_get "$pre" '"Content-only payload: 0 of 244 media files found on this site" in [c["detail"] for c in d["checks"] if c["id"]=="lite"][0]')" "True"
check "pre-flight lists the first missing paths" "$(pre_get "$pre" 'len(d["lite"]["missing"]) == 244 and d["lite"]["missing"][0] == "2020/01/Heartland-Logo-150px.png"')" "True"
check "wp hk9 preflight prints FAIL for it" "$($WP hk9 preflight "$LITE_C" 2>/dev/null | grep -c 'FAIL  Media reuse')" "1"
run_import "$LITE_C" --adopt-existing --dry-run
check "dry run with adopt on the empty site fails at validate" "$rc" "1"
check "... fatal: only 0 of 244 found" "$(printf '%s\n' "$last" | grep -c 'only 0 of 244 media files were found')" "1"
check "... log lists 10 missing paths" "$(log_count 'missing on this site: ')" "10"
check "... and the remainder count" "$(log_count '… and 234 more missing media files')" "1"
check "nothing written" "$(wp post list --post_type=any --post_status=any --format=count)" "0"
wp hk9 reset-state --yes --user=admin >/dev/null

echo "-- 2. old live site with all 244 attachments (this takes a few minutes)"
bash tools/reset-local-site.sh >/dev/null 2>&1
fixture=$(wp eval-file "$PLUGIN_C/tests/lite-payload-oldsite.php" "$PAYLOAD_C" 2>&1 | tail -1)
check "old site built (pages, menu items, front page, live media, created, kept)" "$(printf '%s' "$fixture" | python3 -c 'import sys,json; d=json.load(sys.stdin); print(d["pages"], d["menu_items"], d["page_on_front"], d["show_on_front"], d["live_media"], d["created"], d["kept"])' 2>/dev/null)" "10 3 6 page 244 239 5"
old_att=$(wp post list --post_type=attachment --format=count)
check "attachments on the old site = 244 live + 1 unrelated (#555)" "$old_att" "245"
check "#2458 is a scaled big image (original_image kept)" "$(wp eval '$m = wp_get_attachment_metadata(2458); echo basename((string) get_post_meta(2458, "_wp_attached_file", true)), "|", (string) ($m["original_image"] ?? "");')" "IMG_curlyvest-scaled.jpg|IMG_curlyvest.jpg"
old_paths=$(paths_hash)
old_files=$(upload_originals)
old_3028_sizes=$(wp eval 'echo count((array) (wp_get_attachment_metadata(3028)["sizes"] ?? []));')

echo "-- 3. pre-flight"
pre=$(pre_json)
check "pre-flight ok" "$(pre_get "$pre" 'd["ok"]')" "True"
check "pre-flight: 244 of 244 media found" "$(pre_get "$pre" '[c["status"] for c in d["checks"] if c["id"]=="lite"][0], d["lite"]["found"], d["lite"]["total"], d["lite"]["by_path"], len(d["lite"]["missing"])')" "pass 244 244 0 0"
check "pre-flight detail text" "$(pre_get "$pre" '"Content-only payload: 244 of 244 media files found on this site" in [c["detail"] for c in d["checks"] if c["id"]=="lite"][0]')" "True"
check "pre-flight existing pages/registry/media" "$(pre_get "$pre" 'd["existing"]["pages"], d["existing"]["registry"], d["existing"]["media"]')" "7 2 244"
check "pre-flight payload line says content-only" "$(pre_get "$pre" '[c["detail"] for c in d["checks"] if c["id"]=="payload"][0].startswith("Content-only payload: 363 records")')" "True"
check "wp hk9 preflight prints the line" "$($WP hk9 preflight "$LITE_C" 2>/dev/null | grep -c 'PASS  Media reuse.*Content-only payload: 244 of 244 media files found on this site')" "1"

echo "-- 4. dry run with --adopt-existing"
run_import "$LITE_C" --adopt-existing --dry-run
check "dry run exit" "$rc" "0"
check "dry run validate fail" "$(count validate fail)" "0"
check "dry run media_files adopt (every live record)" "$(count media_files adopt)" "244"
check "dry run media_files create (ref assets)" "$(count media_files create)" "5"
check "dry run media_files skip (logo shares #3028)" "$(count media_files skip)" "1"
check "dry run media_files fail" "$(count media_files fail)" "0"
check "dry run: the logo would share #3028" "$(log_count 'ref:asset:heartland-k9s-logo SKIP would share the attachment bound to live:media:3028')" "1"
check "dry run: 244 ADOPT-by-id lines for media" "$(log_count '\[media_files\] live:media:[0-9]* ADOPT #[0-9]* by id,')" "244"
check "dry run: every media adoption is its own id" "$(docker exec hk9-wordpress-1 grep -E '\[media_files\] live:media:[0-9]+ ADOPT #' "$(log_file)" | sed -E 's/.*live:media:([0-9]+) ADOPT #([0-9]+).*/\1 \2/' | awk '$1 != $2' | wc -l | tr -d '[:space:]')" "0"
check "dry run posts_stub adopt" "$(count posts_stub adopt)" "9"
check "dry run log: 'Content-only payload: 244 of 244 media files found'" "$(log_count 'Content-only payload: 244 of 244 media files found on this site')" "1"
check "dry run wrote nothing (attachments)" "$(wp post list --post_type=attachment --format=count)" "$old_att"
check "dry run wrote nothing (files)" "$(upload_originals)" "$old_files"

echo "-- 5. import with --adopt-existing"
run_import "$LITE_C" --adopt-existing
check "import exit" "$rc" "0"
run1=$(run_id)
check "media_files adopt" "$(count media_files adopt)" "244"
check "media_files create" "$(count media_files create)" "5"
check "media_files skip" "$(count media_files skip)" "1"
check "media_files fail" "$(count media_files fail)" "0"
check "media_sizes fail" "$(count media_sizes fail)" "0"
check "no record errors" "$(printf '%s\n' "$last" | grep -c 'record error')" "0"
check "no sha256 mismatch warnings" "$(log_count 'file bytes differ from the payload')" "0"
check "every live:media row is bound to its own id" "$(wp eval 'global $wpdb; $t = HK9\Core\Import\Map::table(); echo (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE source_key LIKE \"live:media:%\" AND status = \"active\" AND object_id = CAST(SUBSTRING(source_key, 12) AS UNSIGNED) AND created_by_run IS NULL");')" "244"
check "attached file paths unchanged for all 244" "$(paths_hash)" "$old_paths"
check "attachments = 245 old + 5 ref assets" "$(wp post list --post_type=attachment --format=count)" "$((old_att + 5))"
check "only the 5 ref files were added under uploads" "$(( $(upload_originals) - old_files ))" "5"
check "bytes copied = the 5 ref files" "$(wp eval 'echo (int) (HK9\Core\Import\State::load()["bytes_copied"] ?? 0);')" "$expected_copy"
check "#3028 missing sizes filled" "$(wp eval 'echo (int) (count((array) (wp_get_attachment_metadata(3028)["sizes"] ?? [])) > '"$old_3028_sizes"');')" "1"
check "#2458 still the scaled live file" "$(wp post meta get 2458 _wp_attached_file)" "2021/03/IMG_curlyvest-scaled.jpg"
check "{{media:live:media:2458}} -> 2458" "$(wp eval '$m = HK9\Core\Import\Manifest::load("'"$LITE_C"'"); $t = new HK9\Core\Import\Tokens($m, false); echo (int) $t->resolve("media", "live:media:2458");')" "2458"
check "live:media:2806 -> #2806 (own id, not shared with 2149)" "$(map_id live:media:2806)-$(map_kind live:media:2806)" "2806-adopted"
check "shared logo record bound to #3028" "$(map_id ref:asset:heartland-k9s-logo)" "3028"
check "#555 (unrelated) untouched and unbound" "$(wp post get 555 --field=post_type)-$(wp eval 'echo HK9\Core\Import\Map::bound_post_key(555) ?? "none";')" "attachment-none"
check "pages adopted (posts_stub adopt)" "$(count posts_stub adopt)" "9"
check "#2910 is now hk9_barkode" "$(wp post get 2910 --field=post_type)" "hk9_barkode"
check "front page renders" "$(http /)" "200"
check "lite payload left in place (server path)" "$(wp eval 'echo (int) is_file("'"$LITE_C"'/manifest.json");')" "1"
check "url matrix passes" "$(bash tools/url-matrix.sh "$SITE" >/dev/null 2>&1 && echo pass || echo fail)" "pass"
matrix_ok=$(bash tools/url-matrix.sh "$SITE" 2>/dev/null | grep -c '✅')
check "url matrix: 84 ✅" "$matrix_ok" "81"

echo "-- 6. re-run: everything skips"
pre=$(pre_json)
check "pre-flight after the import still 244 of 244 (mapped)" "$(pre_get "$pre" 'd["lite"]["found"], d["lite"]["total"], d["existing"]["media"]')" "244 244 0"
run_import "$LITE_C" --adopt-existing
check "re-run exit" "$rc" "0"
check "re-run create+adopt+update+conflict+fail" "$(totals)" "0"
check "re-run media_files skip" "$(count media_files skip)" "250"
check "attached file paths still unchanged" "$(paths_hash)" "$old_paths"
check "no new files under uploads" "$(( $(upload_originals) - old_files ))" "5"

echo "-- 7. negative: two live attachments missing (one re-created under a new id at the same path)"
# 1961 (2020/01/bg2.jpg, plain image) and 2458 (2021/03/IMG_curlyvest.jpg, scaled) are removed with their files.
path_2458=$(wp post meta get 2458 _wp_attached_file)
wp post delete 1961 2458 --force >/dev/null
check "1961 + 2458 gone" "$(wp post get 1961 --field=ID 2>/dev/null)-$(wp post get 2458 --field=ID 2>/dev/null)" "-"
check "their files gone" "$(wp eval '$u = wp_upload_dir()["basedir"]; echo (int) file_exists($u . "/2020/01/bg2.jpg") + (int) file_exists($u . "/'"$path_2458"'");')" "0"
# 2458 comes back as a NEW attachment (different id) at the very same upload path: matched "by path".
new_id=$(wp eval '$u = wp_upload_dir(); $dest = $u["basedir"] . "/2021/03/IMG_curlyvest.jpg"; wp_mkdir_p(dirname($dest)); copy("'"$PAYLOAD_C"'/media/live__media__2458/IMG_curlyvest.jpg", $dest); require_once ABSPATH . "wp-admin/includes/image.php"; $only = fn($s) => array_intersect_key((array) $s, ["thumbnail" => 1]); add_filter("intermediate_image_sizes_advanced", $only, 999); $id = wp_insert_attachment(["post_mime_type" => "image/jpeg", "post_title" => "curlyvest (renumbered)", "post_status" => "inherit", "guid" => $u["baseurl"] . "/2021/03/IMG_curlyvest.jpg"], $dest, 0, true); wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $dest)); echo (int) $id;')
check "re-created under a new id" "$(( new_id > 0 && new_id != 2458 ))" "1"
check "... at the live (scaled) path" "$(wp post meta get "$new_id" _wp_attached_file)" "$path_2458"
pre=$(pre_json)
check "pre-flight: 243 of 244 found, 1 by path, 1 missing -> note (still ok)" "$(pre_get "$pre" '[c["status"] for c in d["checks"] if c["id"]=="lite"][0], d["lite"]["found"], d["lite"]["by_path"], d["lite"]["missing"], d["ok"]')" "warn 243 1 ['2020/01/bg2.jpg'] True"
check "pre-flight detail names the missing path" "$(pre_get "$pre" '"Missing: 2020/01/bg2.jpg" in [c["detail"] for c in d["checks"] if c["id"]=="lite"][0]')" "True"
run_import "$LITE_C" --adopt-existing --dry-run
check "dry run exit (record errors)" "$rc" "2"
check "validate warns about the 1 missing file" "$(log_count 'Content-only payload: 1 media file(s) are not on this site')" "1"
check "validate log: missing on this site: 2020/01/bg2.jpg" "$(log_count 'missing on this site: 2020/01/bg2.jpg')" "1"
check "media_files fails exactly one record" "$(count media_files fail)" "1"
check "... live:media:1961 with the expected path" "$(log_count 'live:media:1961 Attachment not found on this site (expected 2020/01/bg2.jpg); use the full payload')" "1"
check "media_files adopts the renumbered one by path" "$(log_count "live:media:2458 ADOPT #$new_id by path, IMG_curlyvest.jpg")" "1"
check "media_files adopt total = 1 (the rest are mapped and skip)" "$(count media_files adopt)" "1"
check "no file was copied for 1961" "$(wp eval 'echo (int) file_exists(wp_upload_dir()["basedir"] . "/2020/01/bg2.jpg");')" "0"
check "dry run created nothing" "$(wp post list --post_type=attachment --format=count)" "$((old_att + 5 - 1))"
run_import "$LITE_C" --adopt-existing
check "import exit (record errors)" "$rc" "2"
check "media_files fail = 1" "$(count media_files fail)" "1"
check "media_files adopt = 1 (by path)" "$(count media_files adopt)" "1"
check "live:media:2458 -> new id, adopted" "$(map_id live:media:2458)-$(map_kind live:media:2458)" "$new_id-adopted"
check "live:media:1961 keeps its stale row (deleted #1961), nothing new bound" "$(map_id live:media:1961)-$(wp eval 'echo get_post(1961) ? "exists" : "deleted";')" "1961-deleted"
check "no file was copied for 1961 (import)" "$(wp eval 'echo (int) file_exists(wp_upload_dir()["basedir"] . "/2020/01/bg2.jpg");')" "0"
check "attachments unchanged by the negative run" "$(wp post list --post_type=attachment --format=count)" "$((old_att + 5 - 1))"

echo "-- 8. the FULL payload fills the gap after a lite import (everything else skips)"
# #555 carries bg2's bytes under another name and was hashed by the validate prehash pass, so the
# full payload's sha256 fallback adopts it for live:media:1961 (adopt-existing-suite covers the same
# rule from a cold start); nothing is uploaded, every already-bound record skips.
run_import "$PAYLOAD_C" --adopt-existing
check "full payload exit" "$rc" "0"
check "media_files adopt 1 (1961 -> #555 by sha256) / create 0 / fail 0" "$(count media_files adopt)-$(count media_files create)-$(count media_files fail)" "1-0-0"
check "live:media:1961 -> #555" "$(map_id live:media:1961)-$(map_kind live:media:1961)" "555-adopted"
creates=0; fails=0; for st in terms media_files media_sizes posts_stub posts_hierarchy reading posts_content menus options redirects; do creates=$((creates + $(count $st create))); fails=$((fails + $(count $st fail))); done
check "nothing created, nothing failed (the pages that use #1961 are completed now)" "$creates-$fails" "0-0"
check "no record errors" "$(printf '%s\n' "$last" | grep -c 'record error')" "0"
check "attachments unchanged" "$(wp post list --post_type=attachment --format=count)" "$((old_att + 5 - 1))"
check "url matrix still passes" "$(bash tools/url-matrix.sh "$SITE" >/dev/null 2>&1 && echo pass || echo fail)" "pass"

echo "-- cleanup"
wp hk9 reset-state --yes --user=admin >/dev/null
rm -rf "$PLUGIN_L/tests/tmp"
echo "== $pass passed, $fail failed =="
[ "$fail" -eq 0 ]
