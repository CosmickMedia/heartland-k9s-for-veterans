#!/usr/bin/env bash
# Importer regression suite against the local docker stack (mini fixture).
#
#   bash plugin/heartland-k9s-core/tests/importer-suite.sh            # run everything
#   HK9_WP_FLAGS=--skip-themes bash .../importer-suite.sh             # when the theme is mid-edit
#
# Covers docs/importer.md §10 (fresh dry run, import, idempotent re-run, conflict, --overwrite,
# rollback, --step pause + --resume) plus the review fixes: template change on re-import (E),
# per-leaf option reconciliation (G/M), unbakeable content keeps the stub a draft (F), the
# redirect key convention (H), shared attachments (two records with byte-identical files ->
# one attachment, re-run skips, rollback deletes once) and the posts page (page_for_posts ->
# home.php). Leaves the site as it found it (rollback + reset-state).
#
# Requires: tests/payload-mini built (see tools/fixtures/payload-mini/README.md), an admin user
# "admin", the plugin active. Uses only mini:* keys and tmp-* copies under tests/tmp/, so it can
# run on a site that already holds the real import: it never truncates the map, rolls back every
# run that wrote shared keys (option:hk9_settings, reading) and restores the one settings leaf it
# edits by hand (contact.hours) to its exact previous value.
set -uo pipefail
cd "$(dirname "$0")/../../.."
WP="tools/wp.sh ${HK9_WP_FLAGS:-}"
PLUGIN_C=/var/www/html/wp-content/plugins/heartland-k9s-core   # plugin dir inside the container
PLUGIN_L=plugin/heartland-k9s-core
MINI_C="$PLUGIN_C/tests/payload-mini"
TMP_L="$PLUGIN_L/tests/tmp"
TMP_C="$PLUGIN_C/tests/tmp"
pass=0; fail=0; last=""
ok()   { pass=$((pass+1)); printf '  PASS  %s\n' "$1"; }
bad()  { fail=$((fail+1)); printf '  FAIL  %s\n' "$1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 (got '$2', want '$3')"; fi; }
wp()   { $WP "$@" 2>/dev/null; }
# run_import <dir|--resume> [flags...] -> stdout captured in $last, exit code in $rc
run_import() { last=$($WP hk9 import "$@" --user=admin --quiet-progress 2>&1); rc=$?; }
# count <step> <create|adopt|update|skip|conflict|fail> from the last summary table
count() { # the summary is a tab-separated table when stdout is not a TTY; columns are looked up by header name
  local v; v=$(printf '%s\n' "$last" | awk -F'\t' -v s="$1" -v name="$2" '
    $1 == "step" { for (i = 1; i <= NF; i++) if ($i == name) col = i; next }
    $1 == s && col { print $col; exit }')
  printf '%s' "${v:-?}"
}
run_id() { printf '%s\n' "$last" | sed -n 's/.*Run \([0-9a-z-]*\) status.*/\1/p' | head -1; }
# (WP_Query, not `wp post list`: the latter prepends sticky posts to any home-like query.)
post_id_by_key() { wp eval '$q = new WP_Query(["post_type"=>"any","post_status"=>"any","meta_key"=>"_hk9_source_key","meta_value"=>"'"$1"'","fields"=>"ids","posts_per_page"=>1,"ignore_sticky_posts"=>true,"no_found_rows"=>true]); echo (int) ($q->posts[0] ?? 0);'; }
tmp_payload() { # tmp_payload <name> -> copies tests/payload-mini to tests/tmp/<name>
  rm -rf "$TMP_L/$1"; mkdir -p "$TMP_L"; cp -R "$PLUGIN_L/tests/payload-mini" "$TMP_L/$1"
}

echo "== importer suite =="
echo "-- precondition: idle state, no mini:* objects"
wp hk9 reset-state --yes --user=admin >/dev/null
# Only fixture objects count: the site may carry the real import (never touched by this suite).
mapped_count() { wp eval '$q = new WP_Query(["post_type"=>"any","post_status"=>"any","meta_query"=>[["key"=>"_hk9_source_key","value"=>"^mini:","compare"=>"REGEXP"]],"fields"=>"ids","posts_per_page"=>-1,"ignore_sticky_posts"=>true,"no_found_rows"=>true]); echo count($q->posts);'; }
mini_rows() { wp eval 'global $wpdb; HK9\Core\Import\Map::ensure(); $t = HK9\Core\Import\Map::table(); echo (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE source_key LIKE \"mini:%\" OR source_key LIKE \"menu:mini-primary%\" OR source_key LIKE \"term:post_tag:mini-%\" OR source_key IN (\"redirect:/old-mini-about/\", \"redirect:/hk9-mini-001/\")");'; }
check "no mapped mini posts before start" "$(mapped_count)" "0"
if [ "$(mapped_count)" != "0" ]; then echo "Refusing to run: mini fixture objects exist (roll them back first)."; exit 1; fi
# Stale fixture rows (left by an interrupted earlier suite) would make the first import adopt instead of create.
wp eval 'global $wpdb; HK9\Core\Import\Map::ensure(); $t = HK9\Core\Import\Map::table(); $wpdb->query("DELETE FROM $t WHERE source_key LIKE \"mini:%\" OR source_key LIKE \"menu:mini-primary%\" OR source_key LIKE \"term:post_tag:mini-%\" OR source_key IN (\"redirect:/old-mini-about/\", \"redirect:/hk9-mini-001/\")");' >/dev/null
check "no stale mini rows in the map" "$(mini_rows)" "0"
base_posts=$(wp post list --post_type=any --post_status=any --format=count)
base_att=$(wp post list --post_type=attachment --format=count)
base_front=$(wp option get page_on_front)
base_posts_page=$(wp option get page_for_posts)
base_hours=$(wp eval 'echo json_encode(hk9_option("contact.hours", null));')
base_line2=$(wp eval 'echo json_encode(hk9_option("branding.wordmark_line2", null));')

echo "-- 1. fresh dry run"
run_import "$MINI_C" --dry-run
check "dry run exit" "$rc" "0"
check "dry run posts_stub create" "$(count posts_stub create)" "5"
check "dry run media_files create" "$(count media_files create)" "3"
check "dry run media_files skip (card-copy shares the card's file)" "$(count media_files skip)" "1"
check "dry run media_sizes create" "$(count media_sizes create)" "3"
check "dry run posts_hierarchy fail" "$(count posts_hierarchy fail)" "0"
check "nothing written by dry run" "$(wp post list --post_type=any --post_status=any --format=count)" "$base_posts"

echo "-- 2. import"
run_import "$MINI_C"
check "import exit" "$rc" "0"
run1=$(run_id)
check "posts_hierarchy create" "$(count posts_hierarchy create)" "5"
check "posts_content fail" "$(count posts_content fail)" "0"
about=$(post_id_by_key mini:page:about); home=$(post_id_by_key mini:page:home)
check "about template stored" "$(wp post meta get "$about" _wp_page_template)" "page-templates/about.php"
check "home template stored" "$(wp post meta get "$home" _wp_page_template)" "page-templates/home.php"
check "page_on_front = mini home" "$(wp option get page_on_front)" "$home"
news=$(post_id_by_key mini:page:news)
check "page_for_posts = mini news" "$(wp option get page_for_posts)" "$news"
check "posts page renders home.php (HTTP 200, body.blog)" "$(curl -s -o /dev/null -w '%{http_code}' "${HK9_SITE_URL:-http://localhost:8093}/mini-news/")-$(curl -s "${HK9_SITE_URL:-http://localhost:8093}/mini-news/" | grep -o '<body class="[^"]*"' | grep -c ' blog \|"blog ')" "200-1"
# shared attachment: card-copy is byte-identical to card -> one attachment, two map rows, owner's fields kept
card=$(post_id_by_key mini:asset:card)
check "media_files create (3 distinct files)" "$(count media_files create)" "3"
check "media_files skip (card-copy shares)" "$(count media_files skip)" "1"
check "attachments created = 3" "$(wp post list --post_type=attachment --format=count)" "$((base_att + 3))"
check "card-copy row bound to the card attachment" "$(wp eval 'echo (int) (HK9\Core\Import\Map::get("mini:asset:card-copy")["object_id"] ?? 0);')" "$card"
check "card-copy row is adopted (never deleted on its own)" "$(wp eval '$r = HK9\Core\Import\Map::get("mini:asset:card-copy"); echo $r && null === $r["created_by_run"] ? "adopted" : "created";')" "adopted"
check "owner title kept" "$(wp post get "$card" --field=post_title)" "Mini card"
check "owner alt kept" "$(wp post meta get "$card" _wp_attachment_image_alt)" "Crimson card image"
check "card-copy token baked to the shared id" "$(wp eval 'echo substr_count((string) get_post('"$(post_id_by_key mini:page:history)"')->post_content, "wp-image-'"$card"'");')" "1"
check "no unbaked tokens" "$(wp db query "SELECT COUNT(*) FROM $(wp db prefix)posts WHERE post_content LIKE '%{{%'" --skip-column-names | tr -d '[:space:]')" "0"
check "published mini posts with content files have content" "$(wp eval 'foreach (["mini:page:home","mini:page:about","mini:page:history","mini:story:sample"] as $k) { $p = get_posts(["post_type"=>"any","post_status"=>"any","meta_key"=>"_hk9_source_key","meta_value"=>$k,"numberposts"=>1]); if (!$p || "publish" !== $p[0]->post_status || "" === trim($p[0]->post_content)) { echo "BAD:$k "; } } echo "ok";')" "ok"
check "wordmark deep-merged" "$(wp eval 'echo hk9_option("branding.wordmark_line1");')" "Mini K9s"

echo "-- 3. re-run: zero creates / updates / conflicts"
run_import "$MINI_C"
check "re-run exit" "$rc" "0"
total=0; for s in terms media_files media_sizes posts_stub posts_hierarchy reading posts_content menus options redirects; do for k in create update conflict fail; do total=$((total + $(count $s $k))); done; done
check "re-run create+update+conflict+fail across steps" "$total" "0"
check "re-run media_files conflict (shared attachment stays stable)" "$(count media_files conflict)" "0"
check "re-run media_files skip" "$(count media_files skip)" "4"

echo "-- 4. edit on site -> conflict preserved; --overwrite reverts"
wp post update "$about" --post_title="Edited About" >/dev/null
wp post meta update "$about" hk9_sec_hero_band '{"eyebrow":"Who we are","heading":"Edited heading","text":"Hero band text.","pattern":"stars"}' --format=json >/dev/null
run_import "$MINI_C"
check "conflict reported" "$(count posts_hierarchy conflict)" "1"
check "edited title preserved" "$(wp post get "$about" --field=post_title)" "Edited About"
run_import "$MINI_C" --overwrite
check "overwrite update" "$(count posts_hierarchy update)" "1"
check "title reverted" "$(wp post get "$about" --field=post_title)" "About Mini"
run_import "$MINI_C"
check "post-overwrite re-run conflict" "$(count posts_hierarchy conflict)" "0"

echo "-- 5. (E) template change on re-import lands"
tmp_payload tpl
python3 - "$TMP_L/tpl/manifest.json" <<'PY'
import json,sys
p=sys.argv[1]; d=json.load(open(p))
for r in d['records']:
    if r['key']=='mini:page:about': r['template']='landing.php'
json.dump(d,open(p,'w'),indent=2)
PY
run_import "$TMP_C/tpl"
check "template update counted" "$(count posts_hierarchy update)" "1"
check "template changed to landing" "$(wp post meta get "$about" _wp_page_template)" "page-templates/landing.php"
run_import "$MINI_C"
check "template back to about" "$(wp post meta get "$about" _wp_page_template)" "page-templates/about.php"
# editor changes the template -> conflict, kept; --overwrite reverts
wp post meta update "$about" _wp_page_template "page-templates/landing.php" >/dev/null
run_import "$MINI_C"
check "editor template change = conflict" "$(count posts_hierarchy conflict)" "1"
check "editor template kept" "$(wp post meta get "$about" _wp_page_template)" "page-templates/landing.php"
run_import "$MINI_C" --overwrite
check "editor template overwritten" "$(wp post meta get "$about" _wp_page_template)" "page-templates/about.php"

echo "-- 6. (G) per-leaf options: sibling edits are not conflicts, payload leaves are"
wp eval '$o=get_option("hk9_settings"); $o["contact"]["hours"]="9-5 edited"; update_option("hk9_settings",$o);' >/dev/null
run_import "$MINI_C"
check "sibling edit -> options conflict" "$(count options conflict)" "0"
check "sibling edit -> options update" "$(count options update)" "0"
check "sibling edit survives" "$(wp eval 'echo hk9_option("contact.hours");')" "9-5 edited"
wp eval '$o=get_option("hk9_settings"); $o["branding"]["wordmark_line1"]="Edited mark"; update_option("hk9_settings",$o);' >/dev/null
run_import "$MINI_C"
check "payload leaf edit -> options conflict" "$(count options conflict)" "1"
check "payload leaf edit kept" "$(wp eval 'echo hk9_option("branding.wordmark_line1");')" "Edited mark"
run_import "$MINI_C" --overwrite
check "payload leaf overwritten" "$(wp eval 'echo hk9_option("branding.wordmark_line1");')" "Mini K9s"
check "sibling edit still intact after overwrite" "$(wp eval 'echo hk9_option("contact.hours");')" "9-5 edited"

echo "-- 7. (M) unresolvable link token in settings: warn + drop, record still imports"
tmp_payload opt
python3 - "$TMP_L/opt/manifest.json" <<'PY'
import json,sys
p=sys.argv[1]; d=json.load(open(p))
for r in d['records']:
    if r['key']=='mini:asset:card': r['file']='media/missing/nope.png'   # attachment fails validation
    if r['type']=='option':
        r['value'].setdefault('links',{})['donate']={'label':'Give','url':'','post_id':'{{post:mini:page:history}}','target':'_self','rel':''}
        r['value']['branding']['wordmark_line2']='Still imported'
    if r['key']=='mini:page:history':
        r['featured']='{{media:mini:asset:card}}'   # depends on the failed attachment
json.dump(d,open(p,'w'),indent=2)
PY
run_import "$TMP_C/opt"
check "opt: validate fails the attachment" "$(count validate fail)" "1"
check "opt: options step does not fail" "$(count options fail)" "0"
check "opt: surviving leaf imported" "$(wp eval 'echo hk9_option("branding.wordmark_line2");')" "Still imported"
check "opt: dropped leaf warned" "$(printf '%s\n' "$last" | grep -c 'warning(s)')" "1"
run_opt=$(run_id)
# restore the fixture values, then roll the opt run back so the leaf it wrote (wordmark_line2) returns to its pre-image
run_import "$MINI_C" --overwrite
wp hk9 rollback --run="$run_opt" --yes --user=admin >/dev/null 2>&1
check "opt run rolled back: wordmark_line2 restored" "$(wp eval 'echo json_encode(hk9_option("branding.wordmark_line2", null));')" "$base_line2"

echo "-- 8. (F) unbakeable content keeps the stub a draft"
tmp_payload draft
python3 - "$TMP_L/draft/manifest.json" <<'PY'
import json,sys
p=sys.argv[1]; d=json.load(open(p))
recs=d['records']
recs.append({'key':'mini:asset:ghost','type':'attachment','file':'media/mini__asset__ghost/ghost.png','sha256':'0'*64,'mime':'image/png','size':1,'title':'Ghost'})
recs.append({'key':'mini:page:orphan','type':'page','status':'publish','slug':'mini-orphan','title':'Mini Orphan','content':'content/mini__page__orphan.html'})
json.dump(d,open(p,'w'),indent=2)
PY
cat > "$TMP_L/draft/content/mini__page__orphan.html" <<'HTML'
<!-- wp:image {"id":{{media:mini:asset:ghost}}} --><figure class="wp-block-image"><img src="{{media_url:mini:asset:ghost}}" alt=""/></figure><!-- /wp:image -->
HTML
run_import "$TMP_C/draft"
check "orphan: attachment fails validation" "$(count validate fail)" "1"
check "orphan: hierarchy fails the page" "$(count posts_hierarchy fail)" "1"
orphan=$(post_id_by_key mini:page:orphan)
check "orphan stays draft" "$(wp post get "$orphan" --field=post_status)" "draft"
check "orphan content empty" "$(wp eval 'echo strlen((string) get_post('"$orphan"')->post_content);')" "0"
check "orphan still pending" "$(wp post meta get "$orphan" _hk9_import_pending)" "1"
run_orphan=$(run_id)
# fix the payload (file appears; its sha is the card image's, so the attachment is adopted) and resume
mkdir -p "$TMP_L/draft/media/mini__asset__ghost"; cp "$PLUGIN_L/tests/payload-mini/media/mini__asset__card/mini-card.png" "$TMP_L/draft/media/mini__asset__ghost/ghost.png"
python3 - "$TMP_L/draft/manifest.json" "$(shasum -a 256 "$TMP_L/draft/media/mini__asset__ghost/ghost.png" | cut -d' ' -f1)" <<'PY'
import json,sys
p=sys.argv[1]; d=json.load(open(p))
for r in d['records']:
    if r['key']=='mini:asset:ghost': r['sha256']=sys.argv[2]; r['size']=0
json.dump(d,open(p,'w'),indent=2)
PY
run_import --resume
check "resume after fix: orphan published" "$(wp post get "$orphan" --field=post_status)" "publish"
check "resume after fix: orphan has content" "$(wp eval 'echo (int) (strlen((string) get_post('"$orphan"')->post_content) > 50);')" "1"
check "resume after fix: no pending flag" "$(wp post meta get "$orphan" _hk9_import_pending 2>&1 | grep -c '^1$')" "0"

echo "-- 9. (H) redirect key convention is enforced"
tmp_payload rk
python3 - "$TMP_L/rk/manifest.json" <<'PY'
import json,sys
p=sys.argv[1]; d=json.load(open(p))
for r in d['records']:
    if r['type']=='redirect' and r['from']=='/old-mini-about/': r['key']='redirect:/some-other-key/'
json.dump(d,open(p,'w'),indent=2)
PY
run_import "$TMP_C/rk" --dry-run
check "bad redirect key -> fatal" "$(printf '%s\n' "$last" | grep -c 'redirect key must be')" "1"

echo "-- 10. --step pause + --resume"
wp hk9 reset-state --yes --user=admin >/dev/null
run_import "$MINI_C" --step=media_files
check "paused after media_files" "$(printf '%s\n' "$last" | grep -c 'status: paused')" "1"
run_import --resume
check "resume completes" "$(printf '%s\n' "$last" | grep -c 'Import complete')" "1"

echo "-- 11. rollback"
# roll back the orphan run first (it created the orphan stub), then the first creating run
wp hk9 rollback --run="$run_orphan" --yes --user=admin >/dev/null 2>&1
check "orphan removed" "$(post_id_by_key mini:page:orphan)" "0"
out=$($WP hk9 rollback --run="$run1" --yes --user=admin 2>&1)
check "rollback ran" "$(printf '%s\n' "$out" | grep -c 'Success: Rollback:')" "1"
check "mini objects (posts + attachments) gone" "$(mapped_count)" "0"
check "shared card attachment deleted once" "$(wp eval 'echo get_post('"$card"') ? "present" : "gone";')" "gone"
check "attachment count back to baseline" "$(wp post list --post_type=attachment --format=count)" "$base_att"
check "both card rows dropped from the map" "$(wp eval 'echo (int) (bool) HK9\Core\Import\Map::get("mini:asset:card") + (int) (bool) HK9\Core\Import\Map::get("mini:asset:card-copy");')" "0"
check "rollback deleted the card with its secondary rows" "$(printf '%s\n' "$out" | grep -c 'deleted   mini:asset:card (#')" "1"
check "page_for_posts restored" "$(wp option get page_for_posts)" "$base_posts_page"
check "mini menu gone" "$(wp eval 'echo wp_get_nav_menu_object("Mini Primary") ? "present" : "gone";')" "gone"
check "mini term gone" "$(wp eval 'echo term_exists("mini-poker-run","post_tag") ? "present" : "gone";')" "gone"
check "page_on_front restored" "$(wp option get page_on_front)" "$base_front"
check "wordmark restored" "$(wp eval 'echo hk9_option("branding.wordmark_line1");')" "Heartland K9s"
check "mini redirects removed" "$(wp eval '$o=get_option("hk9_redirects"); echo count(array_filter(array_keys($o["rules"]??[]), fn($k)=>str_contains($k,"mini")));')" "0"

echo "-- cleanup"
wp hk9 reset-state --yes --user=admin >/dev/null
# contact.hours was edited by hand in section 6 (no run wrote it): put the exact previous value back.
restore_php=$(printf '$o = get_option("hk9_settings"); $o = is_array($o) ? $o : []; $v = json_decode(%s, true); if (null === $v) { unset($o["contact"]["hours"]); } else { $o["contact"]["hours"] = $v; } update_option("hk9_settings", $o);' "'$base_hours'")
wp eval "$restore_php" >/dev/null
check "contact.hours restored" "$(wp eval 'echo json_encode(hk9_option("contact.hours", null));')" "$base_hours"
rm -rf "$TMP_L"
# The ghost record adopted the card attachment by sha; deleting the card dropped every row bound to it
# (ghost included), so this is a no-op safety net.
wp eval 'HK9\Core\Import\Map::delete("mini:asset:ghost");' >/dev/null
check "no mini rows remain in the map" "$(mini_rows)" "0"
echo "== $pass passed, $fail failed =="
[ "$fail" -eq 0 ]
