# URL matrix

Base http://localhost:8093 · 2026-09-11T17:05:09Z · `HK9_EXPECT_FIXTURES=1 bash tools/url-matrix.sh` · exit 0 · 82 rows, 82 ✅, 0 ❌ (local blog fixtures present)

| URL | Expected | Actual | Header check | Result |
|---|---|---|---|---|
| `/` | 200 | 200 | — | ✅ |
| `/about/` | 200 | 200 | — | ✅ |
| `/program/` | 200 | 200 | — | ✅ |
| `/veterans/` | 200 | 200 | — | ✅ |
| `/get-involved/` | 200 | 200 | — | ✅ |
| `/barkode/` | 200 | 200 | — | ✅ |
| `/stories/` | 200 | 200 | — | ✅ |
| `/contact/` | 200 | 200 | — | ✅ |
| `/5-questions/` | 200 | 200 | — | ✅ |
| `/online-application/` | 200 | 200 | — | ✅ |
| `/service-dogs-and-the-ada/` | 200 | 200 | — | ✅ |
| `/privacy-policy/` | 200 | 200 | — | ✅ |
| `/donate/` | 200 | 200 | — | ✅ |
| `/back-the-pack/` | 200 | 200 | — | ✅ |
| `/volunteer/` | 200 | 200 | — | ✅ |
| `/photos/` | 200 | 200 | — | ✅ |
| `/events/` | 200 | 200 | — | ✅ |
| `/thank-you/` | 200 | 200 | — | ✅ |
| `/campaigns/` | 200 | 200 | — | ✅ |
| `/meet-the-team/` | 200 | 200 | — | ✅ |
| `/the-service-k9-program/` | 200 | 200 | — | ✅ |
| `/how-it-works-veteran-consideration/` | 200 | 200 | — | ✅ |
| `/hk9-current-teams-in-training/` | 200 | 200 | — | ✅ |
| `/our-highlighted-team/` | 200 | 200 | — | ✅ |
| `/heartland-gear/` | 200 | 200 | — | ✅ |
| `/the-hk9-coloring-book/` | 200 | 200 | — | ✅ |
| `/heartland-obedience-training-2/` | 200 | 200 | — | ✅ |
| `/news/` | 200 | 200 | — | ✅ |
| `/larry-and-archie-service-k9/` | 301 → /barkode/larry-and-archie-service-k9/ | 301 → http://localhost:8093/barkode/larry-and-archie-service-k9/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/larry-and-archie-service-k9/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/jimmy-and-riley-service-k9/` | 301 → /barkode/jimmy-and-riley-service-k9/ | 301 → http://localhost:8093/barkode/jimmy-and-riley-service-k9/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/jimmy-and-riley-service-k9/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/vern-and-bella-service-dog/` | 301 → /barkode/vern-and-bella-service-dog/ | 301 → http://localhost:8093/barkode/vern-and-bella-service-dog/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/vern-and-bella-service-dog/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/hk923004/` | 301 → /barkode/hk923004/ | 301 → http://localhost:8093/barkode/hk923004/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/hk923004/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/hk923-005/` | 301 → /barkode/hk923-005/ | 301 → http://localhost:8093/barkode/hk923-005/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/hk923-005/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/madison-and-gunther-service-k9/` | 301 → /barkode/madison-and-gunther-service-k9/ | 301 → http://localhost:8093/barkode/madison-and-gunther-service-k9/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/madison-and-gunther-service-k9/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/scott-and-elke-service-k9/` | 301 → /barkode/scott-and-elke-service-k9/ | 301 → http://localhost:8093/barkode/scott-and-elke-service-k9/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/scott-and-elke-service-k9/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/jeremy-and-nova-service-k9/` | 301 → /barkode/jeremy-and-nova-service-k9/ | 301 → http://localhost:8093/barkode/jeremy-and-nova-service-k9/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/jeremy-and-nova-service-k9/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/paul-and-mj-service-k9/` | 301 → /barkode/paul-and-mj-service-k9/ | 301 → http://localhost:8093/barkode/paul-and-mj-service-k9/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/paul-and-mj-service-k9/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/cody-and-willow-service-k9/` | 301 → /barkode/cody-and-willow-service-k9/ | 301 → http://localhost:8093/barkode/cody-and-willow-service-k9/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/cody-and-willow-service-k9/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/barkode-mosby-hk9t26-01/` | 301 → /barkode/barkode-mosby-hk9t26-01/ | 301 → http://localhost:8093/barkode/barkode-mosby-hk9t26-01/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/barkode-mosby-hk9t26-01/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/kimber_hk92026-01/` | 301 → /barkode/kimber_hk92026-01/ | 301 → http://localhost:8093/barkode/kimber_hk92026-01/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/kimber_hk92026-01/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/caddie-service-k9_hk92026-02/` | 301 → /barkode/caddie-service-k9_hk92026-02/ | 301 → http://localhost:8093/barkode/caddie-service-k9_hk92026-02/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/caddie-service-k9_hk92026-02/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/tex-service-k9-hk926-002/` | 301 → /barkode/tex-service-k9-hk926-002/ | 301 → http://localhost:8093/barkode/tex-service-k9-hk926-002/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/tex-service-k9-hk926-002/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/sandy-therapy-k9t26-02/` | 301 → /barkode/sandy-therapy-k9t26-02/ | 301 → http://localhost:8093/barkode/sandy-therapy-k9t26-02/ | x-redirect-by: hk9-legacy | ✅ |
| `/barkode/sandy-therapy-k9t26-02/` | 200 | 200 | x-robots-tag: noindex | ✅ |
| `/HK923-005/?utm_source=qr` | 301 → /barkode/hk923-005/?utm_source=qr | 301 → http://localhost:8093/barkode/hk923-005/?utm_source=qr | — | ✅ |
| `/hk923-005` | 301 → /barkode/hk923-005/ | 301 → http://localhost:8093/barkode/hk923-005/ | — | ✅ |
| `/barkode/hk923-005/embed/` | 404 | 404 | — | ✅ |
| `/master_template_barkode/` | 301 → /barkode/ | 301 → http://localhost:8093/barkode/ | — | ✅ |
| `/success/` | 301 → /stories/ | 301 → http://localhost:8093/stories/ | — | ✅ |
| `/success/sample-success-story/` | 301 → /stories/ | 301 → http://localhost:8093/stories/ | — | ✅ |
| `/slide/heartland-hero/` | 301 → http://localhost:8093/ | 301 → http://localhost:8093/ | — | ✅ |
| `/slide-page/homepage-fusion/` | 301 → http://localhost:8093/ | 301 → http://localhost:8093/ | — | ✅ |
| `/?foogallery=2468` | 301 → /photos/ | 301 → http://localhost:8093/photos/ | — | ✅ |
| `/?foogallery=back-the-pack-partners` | 301 → /back-the-pack/ | 301 → http://localhost:8093/back-the-pack/ | — | ✅ |
| `/teams/` | 301 → /hk9-current-teams-in-training/ | 301 → http://localhost:8093/hk9-current-teams-in-training/ | — | ✅ |
| `/?post_type=hk9_barkode` | 404 | 404 | — | ✅ |
| `/?post_type=hk9_barkode&feed=rss2` | 404 | 404 | — | ✅ |
| `/wp-json/wp/v2/hk9_barkode` | 404 | 404 | — | ✅ |
| `/wp-json/wp/v2/users` | 401 | 401 | — | ✅ |
| `/tag/poker-run/` | 200 | 200 | — | ✅ |
| `/tag/veterans-day/` | 200 | 200 | — | ✅ |
| `/news/page/2/` | 200 | 200 | — | ✅ |
| `/stories/page/2/` | 200 | 200 | — | ✅ |
| `/?s=dog` | 200 | 200 | — | ✅ |
| `/?s=zzqqxx-no-results` | 200 | 200 | — | ✅ |
| `/this-page-does-not-exist/` | 404 | 404 | — | ✅ |
| `/wp-sitemap.xml` | 200 | 200 | — | ✅ |
| `/feed/` | 200 | 200 | — | ✅ |

## Post-cleanup run

`bash tools/url-matrix.sh` after `tools/wp.sh hk9-dev fixtures delete` (no fixtures, so the `/news/page/2/` row is skipped by the script's guard): exit 0 · 81 rows, 81 ✅, 0 ❌ — including `/tag/poker-run/` and `/tag/veterans-day/` = 200.

Note: the first `fixtures delete` of this session removed those two imported tags (term ids 72/73) because `docker/fixtures/blog-fixtures.php` marked pre-existing terms adopted via `term_exists` as fixtures. The fixture was fixed (only terms it creates are marked), the tags were recreated by slug (`wp term create post_tag "Poker Run" --slug=poker-run` → 89, `"Veterans Day" --slug=veterans-day` → 90), `wp hk9 import … --dry-run` reports `terms 0 create / 0 update / 6 skip` (the importer adopts them by slug and will re-point its map rows from 72/73 on its next real pass), and a second create → delete cycle with the fixed fixture left both tags in place.
