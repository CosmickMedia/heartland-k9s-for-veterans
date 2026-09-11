# Installation & migration guide

Heartland Canines for Veterans ships as **two installable packages** plus a **content payload**:

| Package | What it is | Required |
|---|---|---|
| `heartland-k9s.zip` | The theme (Bootstrap 5.3.8, compiled CSS/JS, self-hosted Fraunces + Inter, lucide icon sprite, all templates). | Yes |
| `heartland-k9s-core.zip` | The companion plugin: content types (stories, teams, people, partners, campaigns, events, BarKode registry records, form submissions), page-section fields, Heartland Settings, forms, legacy redirects, the importer. | Yes — content lives here so it survives theme switches |
| `heartland-k9s-payload.zip` | The migration payload: `manifest.json` (363 records), block content, and every media file (≈663 MB). Packaged separately because of its size. | For a fresh site, or to (re)run the migration |

Tested with **WordPress 7.1** on **PHP 8.3.33** (MariaDB 11, Apache). Minimum: WordPress 6.4, PHP 8.1. No Node.js, Composer, page builder or paid plugin is needed on the server.

## A. Fresh site (recommended path)

1. Install WordPress (any current version ≥ 6.4), set **Settings → Permalinks** to *Post name* (`/%postname%/`) and the timezone to *Chicago*.
2. **Plugins → Add New → Upload** `heartland-k9s-core.zip`, activate it. Activation only registers content types, capabilities, seed redirect rules and creates one small import-map table. It never imports content.
3. **Appearance → Themes → Add New → Upload** `heartland-k9s.zip`, activate it. (If the plugin is missing, the theme still works for pages/posts and shows a notice.)
4. **Heartland → Setup & Import**: upload `heartland-k9s-payload.zip`. The plugin unpacks it into a protected upload folder. Click **Dry run** to see create/update/skip counts, then **Import**. The import is chunked and resumable (progress bar, per-step counts, error list, log download); on a typical host it takes 3–10 minutes, most of it generating image sizes for 250 media files. If the browser tab is closed, reopen the page and click **Resume**.
   - CLI alternative: `wp hk9 import /path/outside/webroot/payload --user=<admin>` (`--dry-run`, `--resume`, `--overwrite`, `--step=`, `--batch=` available; `wp hk9 status`; `wp hk9 rollback --run=<id> [--force]`).
5. The import sets the static front page (Home), the posts page (News), menus (Primary, Footer Quick Links, Footer Get Involved, Legal), all Heartland Settings (contact details, destinations, header/footer text, form recipients) and the legacy redirects.
6. Check **Heartland → Settings → Forms**: confirm the recipient addresses (defaults: contact → info@heartlandk9s.org, application inquiries → director@heartlandk9s.org) and that the host can send mail (`wp_mail`); the Submissions screen shows whether each notification was sent.
7. Optional: **Heartland → Settings → Analytics** for the Fathom site id (the live site uses Fathom; the id is not reproduced in the payload). **Settings → General** for the site icon if not set (the imported logo crop is available in the Media Library).

## B. Existing WordPress installation

Take a full backup (database + `wp-content/uploads`) first. Then follow A.2–A.4. Notes:

- The importer is **idempotent and keyed to source identities**: re-running it never duplicates pages, records, terms, menus, options or attachments. Records you have edited after import are **preserved** (reported as *conflict* and skipped) unless you tick **Overwrite**.
- Attachments are de-duplicated by SHA-256, so an image that already exists in your Media Library is adopted rather than uploaded twice.
- Slug collisions with pre-existing pages are resolved by WordPress (`-2` suffix) and reported in the log; review them before launch.
- Menus with the same names are adopted (items added, nothing deleted). The Reading settings (front page / posts page) are set by the payload's `reading` record.
- **Rollback**: Heartland → Setup & Import → *Roll back run* (or `wp hk9 rollback --run=<id>`) removes only objects created by that run, restores the options it changed, and skips anything modified since (use *Force* to remove those too). Pre-existing content is never touched.

## C. Local development stack (what this repo uses)

```
docker compose -f docker/docker-compose.yml up -d
docker/setup.sh            # installs WordPress, activates theme + plugin
docker/setup.sh --import   # …and imports ./payload
npm install && npm run build   # theme CSS/JS (sass + esbuild); fonts/icons: npm run build:fonts, npm run build:icons
npm run media:fetch        # (re)downloads the 251 media originals into payload/media (resumable)
npm run payload:build      # rebuilds payload/manifest.json from payload-src/
npm run package            # builds dist/*.zip + SHA256SUMS
```

Site: http://localhost:8093 (admin/admin) · Mailpit (captured email): http://localhost:8094 · WP-CLI: `tools/wp.sh <args>`.

## D. Going live checklist

1. Point DNS only after the imported site has been reviewed (see `docs/unresolved.md` for the business decisions that need sign-off: office days, imagery, PayPal retirement, registry privacy scope, form recipients).
2. Keep the 16 legacy BarKode URLs working: they are printed on physical QR patches. The plugin serves each as a one-hop 301 to `/barkode/<same-slug>/` (see **Heartland → Redirects**; `wp hk9 redirects test`).
3. Registry records are excluded from search, sitemaps, feeds, REST and embeds and carry `noindex`. Do not add them to menus.
4. Remove the uploaded payload folder after a successful import (the finalize step deletes it automatically when it was uploaded through the admin screen).
5. Ensure outbound mail works (SMTP plugin of your choice) and test the contact and application forms once in production.

## E. Backup / restore

The import writes a log to `wp-content/uploads/hk9-import/<run>.log` (admin-only download) and records every created/updated object in the `wp_hk9_import_map` table with content hashes. Together with your pre-import backup, that gives three recovery levels: rollback of a run, restore of the database backup, or a fresh install + import.
