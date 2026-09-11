#!/usr/bin/env bash
# Curl status/Location matrix for every migrated URL, legacy redirect and WordPress state.
#   tools/url-matrix.sh [base]   -> prints a markdown table and exits non-zero on mismatch
BASE="${1:-http://localhost:8093}"
fail=0
check() { # path expected_status [expected_location_suffix] [expected_header_regex]
  local p="$1" exp="$2" loc="${3:-}" hdr="${4:-}"
  local out; out=$(curl -s -o /dev/null -D - "$BASE$p" -A "hk9-url-matrix" --max-time 30)
  local code; code=$(printf '%s' "$out" | head -1 | awk '{print $2}')
  local location; location=$(printf '%s' "$out" | grep -i '^location:' | head -1 | sed 's/^[Ll]ocation: *//' | tr -d '\r')
  local ok="✅"
  [ "$code" = "$exp" ] || ok="❌"
  if [ -n "$loc" ] && [[ "$location" != *"$loc" ]]; then ok="❌"; fi
  if [ -n "$hdr" ] && ! printf '%s' "$out" | grep -qiE "$hdr"; then ok="❌"; fi
  [ "$ok" = "✅" ] || fail=1
  printf '| `%s` | %s | %s | %s | %s |\n' "$p" "$exp${loc:+ → $loc}" "$code${location:+ → $location}" "${hdr:-—}" "$ok"
}
echo "| URL | Expected | Actual | Header check | Result |"
echo "|---|---|---|---|---|"
# Reference routes
for p in / /about/ /program/ /veterans/ /get-involved/ /barkode/ /stories/ /contact/; do check "$p" 200; done
# Migrated live pages (slugs preserved)
for p in /5-questions/ /online-application/ /service-dogs-and-the-ada/ /privacy-policy/ /donate/ /back-the-pack/ /volunteer/ /photos/ /events/ /thank-you/ /campaigns/ /meet-the-team/ /the-service-k9-program/ /how-it-works-veteran-consideration/ /hk9-current-teams-in-training/ /our-highlighted-team/ /heartland-gear/ /the-hk9-coloring-book/ /heartland-obedience-training-2/ /news/; do check "$p" 200; done
# Registry legacy paths -> canonical record URLs (one hop)
for s in larry-and-archie-service-k9 jimmy-and-riley-service-k9 vern-and-bella-service-dog hk923004 hk923-005 madison-and-gunther-service-k9 scott-and-elke-service-k9 jeremy-and-nova-service-k9 paul-and-mj-service-k9 cody-and-willow-service-k9 barkode-mosby-hk9t26-01 kimber_hk92026-01 caddie-service-k9_hk92026-02 tex-service-k9-hk926-002 sandy-therapy-k9t26-02; do
  check "/$s/" 301 "/barkode/$s/" "x-redirect-by: hk9-legacy"
  check "/barkode/$s/" 200 "" "x-robots-tag: noindex"
done
check "/HK923-005/?utm_source=qr" 301 "/barkode/hk923-005/?utm_source=qr"
check "/hk923-005" 301 "/barkode/hk923-005/"
check "/barkode/hk923-005/embed/" 404
check "/master_template_barkode/" 301 "/barkode/"
check "/success/" 301 "/stories/"
check "/success/sample-success-story/" 301 "/stories/"
check "/slide/heartland-hero/" 301 "$BASE/"
check "/slide-page/homepage-fusion/" 301 "$BASE/"
check "/?foogallery=2468" 301 "/photos/"
check "/?foogallery=back-the-pack-partners" 301 "/back-the-pack/"
check "/teams/" 301 "/hk9-current-teams-in-training/"
check "/?post_type=hk9_barkode" 404
check "/?post_type=hk9_barkode&feed=rss2" 404
check "/wp-json/wp/v2/hk9_barkode" 404
check "/wp-json/wp/v2/users" 401
check "/tag/poker-run/" 200
check "/tag/veterans-day/" 200
check "/news/page/2/" 200
check "/stories/page/2/" 200
check "/?s=dog" 200
check "/?s=zzqqxx-no-results" 200
check "/this-page-does-not-exist/" 404
check "/wp-sitemap.xml" 200
check "/feed/" 200
exit $fail
