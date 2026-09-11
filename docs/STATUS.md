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
- [ ] SEO/robots/OG · performance

## Phase 5 — Content & import
- [x] payload-src authored (8 reference pages, 19 migrated pages, records, menus, settings, redirects, tags, reading)
- [x] payload built (363 records) · imported locally (0 errors, 2m23s) · re-import 0 creates (10 duplicate-media conflicts → fix in Wave 3) · rollback on real run verified (3 pages + 10 dup media skipped as modified → fixed slug collision, dedupe fix pending) · edit-preserve/overwrite proven on mini fixture (58 checks)

## Phase 6 — Visual comparison loop
- [ ] Screenshots + diffs (8 routes × 390/1440 + representative widths) · fixes · deviations documented

## Phase 7 — Quality
- [ ] axe + keyboard · Lighthouse mobile · network/console/PHP-log audits · cross-engine · editor task matrix · security review

## Phase 8 — Packaging & docs
- [ ] ZIPs built + fresh-site install test · install.md · admin-guide.md · verification.md · visual-comparison.md · licenses
