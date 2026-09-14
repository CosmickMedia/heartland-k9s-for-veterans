# Build status (persistent checklist — update as work completes)

Plan: `~/.claude/plans/heartland-canines-for-veterans-cuddly-marshmallow.md` · Contract: `docs/ARCHITECTURE.md` · Discovery: `discovery/`

Local stack: `docker compose -f docker/docker-compose.yml up -d && docker/setup.sh` → http://localhost:8093 (admin/admin), Mailpit http://localhost:8094, WP-CLI `tools/wp.sh <args>`.

## Phase 0 — Scaffold & environment
- [x] Branch `build/wordpress-conversion`; discovery data copied to `discovery/`
- [x] package.json (bootstrap 5.3.8 pinned) + lockfile; Playwright chromium/webkit/firefox installed
- [x] docker compose (WP 7.1 / PHP 8.3.33 / Imagick / MariaDB 11 / Mailpit) on :8093/:8094; setup.sh; dev mu-plugin
- [x] Theme + plugin skeletons activate cleanly (debug.log empty)
- [x] docs/ARCHITECTURE.md contract

## Phase 1 — Baseline & inventories
- [x] docs/reports/baseline.md (reference screenshots + measurements + interaction checklist)
- [x] docs/migration-map.md · docs/conflict-log.md · docs/media-manifest.md · docs/unresolved.md (written in Wave 1; refreshed after reviews)

## Phase 2 — Media & fonts
- [x] tools/fetch-media.mjs run: 251/251 ok (663 MB), sha256 sidecars, docs/reports/media-fetch-report.md
- [x] Fonts built (Fraunces/Inter WOFF2 + OFL) · lucide sprite built (39 icons)

## Phase 3 — Plugin core
- [x] PostTypes/Capabilities/columns/rewrites/privacy
- [x] Fields framework + Sections registry + meta (revisions/preview validated design)
- [x] Settings page (all tabs)
- [x] Forms + submissions · Events helpers · Redirects module + admin
- [x] Importer + CLI + admin import screen

## Phase 4 — Theme
- [x] Sass/JS build, tokens, components, fonts/icons, theme.json
- [x] Header/footer/menus/mobile menu
- [x] Front page + reference page templates (about/program/veterans/get-involved/barkode/stories/contact)
- [x] Landing/donate/events/campaigns/partners/people/teams/highlighted/gallery/application templates
- [x] CPT singles · blog templates · search/404/comments · block styles
- [x] Client-ready defaults (theme + plugin 1.2.0): default page template = hero band (hideable) + content card + optional CTA; 10 "Heartland" block patterns with matching editor/front-end block styles; footer credit "Built with ♥ for our veterans by Cosmick Media." from settings; TikTok social; search placeholder + "Helpful links (404 & search)" menu location; settings completeness audit (ARCHITECTURE §7)
- [ ] SEO/robots/OG · performance

## Phase 5 — Content & import
- [x] payload-src authored (8 reference pages, 19 migrated pages, records, menus, settings, redirects, tags, reading)
- [x] payload built (363 records) · imported locally + on a fresh stack (0 errors, ~2m25s) · re-import all skips, 0 conflicts · rollback verified · edit-preserve/overwrite/resume proven (importer suite 81 checks)
- [x] Existing-site migration mode (plugin 1.1.0): adopt matching pages/registry pages/media by live id + slug, in-place BarKode conversion, pre-flight panel + `wp hk9 preflight`, un-adopting rollback — simulated on the local stack against an Avada-shaped old site (`tests/adopt-existing-suite.sh`, 155 checks); client runbook in `docs/install.md` §B
- [x] Content-only payload (plugin 1.1.1): `build-payload.mjs --lite` → `payload-lite/` / `dist/heartland-k9s-payload-lite.zip` (≈1.5 MB, uploads on the admin screen); live media records carry `file: null` + `basename`/`live_path`/`sha256`/`bytes` and are adopted from the Media Library (by id + name, or by upload path), never copied; validate/pre-flight "N of M media found" gate (fatal < 95 %); simulated with all 244 live attachments present (`tests/lite-payload-suite.sh`) — install.md §B now runs on the lite ZIP

## Phase 6 — Visual comparison loop
- [x] Screenshots + diffs (8 routes × 390/1440 + representative widths) · fixes · deviations documented

## Phase 7 — Quality
- [x] axe + keyboard · Lighthouse mobile · network/console/PHP-log audits · cross-engine · editor task matrix · security review

## Phase 8 — Packaging & docs
- [x] ZIPs built + fresh-site install test · install.md · admin-guide.md · verification.md · visual-comparison.md · licenses

## Launch — heartlandk9s.org (2026-09-13, theme + plugin 1.3.0)
- [x] Live go-live executed per install.md §B through the client's wp-admin: theme 1.2.2 → 1.3.0 replaced from `build/heartland-k9s.zip`; Heartland K9s Core 1.3.0 activated; Avada Builder, Avada Core, FooGallery, FooBox deactivated; theme activated; `heartland-k9s-payload-lite.zip` uploaded (365 records, 244/244 media matched); dry run then import in adopt mode — run `20260914-001113-cgi5t75s`, 0 errors, 0 conflicts (40 pages adopted in place incl. 16 registry pages → BarKode records, 39 records/pages created, 242 attachments got missing sizes, 31 menu items, reading/options/redirects applied); expected warnings only (master template + two `/success/` stubs set to draft)
- [x] Post-import: Gravity Forms provider auto-adopted (Contact #1, Initial Application Inquiry #2); Classic Editor set to Block editor for all users; curl matrix (24 pages 200, registry/legacy/author/foogallery/slide 301s, registry `X-Robots-Tag: noindex, nofollow`, `X-Redirect-By: hk9-legacy`); Slim SEO `/sitemap.xml` 200; no PHP notices; live markup identical to local (nav, footer, 74 gallery figures, 11 people); Lighthouse mobile on live: / 98, /about/ 96, /contact/ 95 (100/100/100 a11y/BP/SEO); no false self-update offered at 1.3.0
- Note: media_sizes on the Hostinger host ran ~1 image/20 s for the large gallery originals (≈35 min total); a hidden browser tab throttles the admin tick loop (Chrome intensive throttling) — keep the Setup & Import tab in the foreground on future runs

## Post-launch — Thank You page template (theme + plugin 1.3.1)
- [x] Design: judge panel (3 independent designs → 2 judges → synthesis; applicant-first layout won 8.5/8.5): band hero → overlapping **next-steps card** (reassurance, `<ol>` steps with crimson circles, Medical History Form download with type + size, return address from Settings, tip, canvas content inside the card) → settings-driven **help strip** (phone / email / hours, ghost link) → **While You Wait** feature cards → CTA hidden by default
- [x] Plugin: `Sections/definitions/thank-you.php` (sections `next_steps` / `help` / `reading` / `cta`; File field with mime filter; help text against response-time promises); importer `Reconcile::hash()` treats a missing meta key and an empty string as the same value (no more false conflicts after a record is re-saved — 49 → 0 on the DevKinsta rehearsal); block editor "Meta Boxes" pane open by default (`EditorGuidance::open_meta_boxes_pane()`)
- [x] Theme: `page-templates/thank-you.php` (content callback through the new `args` injection of `hk9_rec_render_sections()`, hidden-card fallback, spacer logic), parts `next_steps_card.php` + `contact_strip.php`, `hk9_attachment_meta_label()`, plugin-less defaults, `.hk9-thank-you` / `.hk9-help-strip` styles (+ print) in `records.css`, 8 new lucide icons (circle-help, scale, file-down, lightbulb, printer, stethoscope, send, list-checks)
- [x] Payload: `live:page:2505` re-authored (template, sections incl. `{{media:live:media:2506}}` PDF, page-token links, `hk9_seo_noindex` true, canvas blanked — the copy moved into the fields); full + lite payloads rebuilt; re-import updates only that page (docker stack: hierarchy 1 / content 1 update, 0 conflicts; DevKinsta adopt-mode rehearsal: same)
- [x] Verified: sections-test 47/47 (47 page meta keys), core-fixes 93/93, gravity 34/34, importer suite (mini fixture), ad-hoc editor check 9/9 (pane open, PDF in the file field, 4 steps / 3 rows, new icons, save → frontend, restore), axe 0 violations at 390/1440, hidden-card + canvas fallbacks, `noindex, follow` + sitemap exclusion, screenshots reviewed at 390/1440
- [x] Released v1.3.1 and v1.3.2 (2026-09-14) and rolled out on heartlandk9s.org through the GitHub self-updater (plugin + theme, Dashboard → Updates), then Setup & Import with the lite payload: dry run showed `posts_hierarchy` update 1 / `posts_content` update 1, 0 conflicts; the two legacy `/success/` drafts had been trashed by the client since launch — 1.3.2 makes the importer leave a trashed mapped post alone (skip + warning) instead of re-creating it. Live /thank-you/ now renders the new template (PDF button "PDF, 1.5 MB", address + phone/email from Settings, page-token links) and is `noindex, follow` (Slim SEO owns robots on live, so its own "Hide from search results" was ticked on the page; it also drops it from Slim SEO's sitemap).
