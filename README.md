# Heartland Canines for Veterans — WordPress theme + companion plugin

A production WordPress implementation of the Heartland Canines for Veterans reference design (https://heartland-canines-for-veterans.replit.app/) with a complete migration of the organization's live website content (https://heartlandk9s.org/).

**Deliverables (see `dist/` after `npm run package`):**

- `heartland-k9s.zip` — theme "Heartland Canines for Veterans" (Bootstrap 5.3.8, compiled CSS/JS, self-hosted Fraunces + Inter, lucide icon sprite, complete template hierarchy, 19 named page templates).
- `heartland-k9s-core.zip` — companion plugin "Heartland K9s Core" (stories, teams, people, partners, campaigns, events, BarKode registry records, form submissions; page-section fields with revisions/preview; Heartland Settings; contact + application-inquiry forms; legacy redirects; chunked idempotent importer with rollback; WP-CLI).
- `heartland-k9s-payload-lite.zip` — **content-only** payload for migrating the live site in place (363 records, ≈1.5 MB): the 244 live media records ship no file and are reused from the site's Media Library (existing-site mode), only the 6 new design images are included; uploads on Heartland → Setup & Import.
- `heartland-k9s-payload.zip` — full content/media payload (363 records incl. 250 media originals, ≈663 MB) for a fresh site.

**Documentation:** `docs/install.md` (install + migration, fresh vs existing site, backup/rollback) · `docs/admin-guide.md` (where staff edit everything) · `docs/migration-map.md` (every live URL → disposition/redirect) · `docs/conflict-log.md` (reference vs live decisions, content-driven deviations) · `docs/media-manifest.md` + `docs/reports/media-fetch-report.md` · `docs/visual-comparison.md` · `docs/verification.md` · `docs/unresolved.md` (genuine open items) · `docs/importer.md` · `docs/ARCHITECTURE.md` (technical contract) · `docs/licenses/`.

**Repository layout:** `theme/` and `plugin/` (sources = installable packages), `payload-src/` (content source of truth; `tools/build-payload.mjs` builds `payload/`), `tools/` (build, media fetch, fonts/icons, payload, screenshot/diff, axe, Lighthouse, network audit, URL matrix, packaging), `docker/` (local WordPress 7.1 + Mailpit stack), `discovery/` (reference measurements, screenshots, live inventory, design validation), `docs/`.

**Build (development machine only — the server needs no Node):**

```
npm install
npm run build          # CSS (sass) + JS (esbuild) + icon sprite
npm run build:fonts    # Fraunces/Inter WOFF2 from the OFL sources (python3 + fontTools)
npm run media:fetch    # download the 251 media originals into payload/media (resumable)
npm run payload:build  # payload/manifest.json from payload-src/
node tools/build-payload.mjs --lite   # payload-lite/ (content-only payload for the live site)
npm run package        # dist/*.zip + SHA256SUMS (theme, plugin, full + lite payload)
```

Local stack: `docker compose -f docker/docker-compose.yml up -d && docker/setup.sh --import` → http://localhost:8093 (admin/admin), Mailpit http://localhost:8094, WP-CLI `tools/wp.sh`.

Tested with WordPress 7.1 / PHP 8.3.33 / MariaDB 11; Bootstrap 5.3.8 pinned in `package-lock.json`.
