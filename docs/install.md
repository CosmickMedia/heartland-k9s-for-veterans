# Installation & migration guide

Heartland Canines for Veterans ships as **two installable packages** plus a **content payload**:

| Package | What it is | Required |
|---|---|---|
| `heartland-k9s.zip` | The theme (Bootstrap 5.3.8, compiled CSS/JS, self-hosted Fraunces + Inter, lucide icon sprite, all templates). | Yes |
| `heartland-k9s-core.zip` | The companion plugin: content types (stories, teams, people, partners, campaigns, events, BarKode registry records, form submissions), page-section fields, Heartland Settings, forms, legacy redirects, the importer. | Yes — content lives here so it survives theme switches |
| `heartland-k9s-payload-lite.zip` | The **content-only** migration payload for the live site (≈1.5 MB): the same `manifest.json` (363 records) and block content, but the 244 live media records carry no file — they are reused from the site's own Media Library — and only the 6 new design images ship. Uploads on the admin screen. | For migrating heartlandk9s.org in place (§B) |
| `heartland-k9s-payload.zip` | The **full** migration payload: `manifest.json` (363 records), block content, and every media file (≈663 MB). Packaged separately because of its size. | For a fresh site (§A), or when the Media Library of the target is not the live one |

Tested with **WordPress 7.1** on **PHP 8.3.33** (MariaDB 11, Apache). Minimum: WordPress 6.4, PHP 8.1. No Node.js, Composer, page builder or paid plugin is needed on the server.

## A. Fresh site (recommended path)

1. Install WordPress (any current version ≥ 6.4), set **Settings → Permalinks** to *Post name* (`/%postname%/`) and the timezone to *Chicago*.
2. **Plugins → Add New → Upload** `heartland-k9s-core.zip`, activate it. Activation only registers content types, capabilities, seed redirect rules and creates one small import-map table. It never imports content.
3. **Appearance → Themes → Add New → Upload** `heartland-k9s.zip`, activate it. (If the plugin is missing, the theme still works for pages/posts and shows a notice.)
4. **Heartland → Setup & Import**. A fresh site needs the **full** payload (`heartland-k9s-payload.zip` — the content-only ZIP of §B only works on the live site, whose Media Library holds the files). It is ≈663 MB, larger than most hosts' upload limit, so use one of: (a) unzip `heartland-k9s-payload.zip` locally and upload its contents with SFTP into `wp-content/uploads/hk9-payload-2026/` (any `hk9-payload-*` folder name works), then enter that path under *Full payload: use a directory on the server* and click *Use this path*; (b) WP-CLI (step 4 CLI note); (c) on hosts whose upload limit allows it, upload the ZIP directly on the same screen (it is unpacked into a protected upload folder). Leave *Existing site: adopt matching content* unticked. Click **Dry run** to see create/update/skip counts, then **Import**. The import is chunked and resumable (progress bar, per-step counts, error list, log download); on a typical host it takes 3–10 minutes, most of it generating image sizes for 250 media files. If the browser tab is closed, reopen the page and click **Resume**.
   - CLI alternative: `wp hk9 import /path/outside/webroot/payload --user=<admin>` (`--dry-run`, `--resume`, `--overwrite`, `--step=`, `--batch=` available; `wp hk9 status`; `wp hk9 rollback --run=<id> [--force]`).
5. The import sets the static front page (Home), the posts page (News), menus (Primary, Footer Quick Links, Footer Get Involved, Legal), all Heartland Settings (contact details, destinations, header/footer text, form recipients) and the legacy redirects.
6. Check **Heartland → Settings → Forms**: confirm the recipient addresses (defaults: contact → info@heartlandk9s.org, application inquiries → director@heartlandk9s.org) and that the host can send mail (`wp_mail`); the Submissions screen shows whether each notification was sent.
7. Optional: **Heartland → Settings → Analytics** for the Fathom site id (the live site uses Fathom; the id is not reproduced in the payload). **Settings → General** for the site icon if not set (the imported logo crop is available in the Media Library).

## B. Existing WordPress installation — migrating heartlandk9s.org in place

This is the path for the **live site** (WordPress 7.1, Avada). The payload was extracted from that site, so it references the pages, registry pages and media files that are already there — by their existing post ids, slugs and upload paths. Plugin 1.1.1 migrates them **in place**: the same pages keep their ids and URLs (`/5-questions/`, `/contact/`, `/privacy-policy/`, …), the 16 legacy BarKode pages become BarKode records with the same slugs (the printed QR paths keep working), and not one image is uploaded — the **content-only payload** (`heartland-k9s-payload-lite.zip`, ≈1.5 MB) reuses the 244 files already in the Media Library and ships only the 6 new design images. Nothing is duplicated as a "-2" page. The whole migration is: upload the plugin ZIP, upload the theme ZIP, activate both, upload one small content ZIP on **Heartland → Setup & Import**, tick *Existing site*, **Dry run**, **Import**.

### B.1 Before you start

1. **Backup.** Take a full backup (database + `wp-content/uploads`) — the host's backup tool or a plugin such as UpdraftPlus. The importer can roll itself back (B.6), but a backup is the safety net for everything else.
2. Make sure you have the three files: `heartland-k9s-core.zip` (plugin), `heartland-k9s.zip` (theme) and `heartland-k9s-payload-lite.zip` (the content-only payload, ≈1.5 MB). The full payload (`heartland-k9s-payload.zip`, ≈663 MB) is **not** needed for the live site; keep it for §A or for the case in B.7.
3. **Settings → Permalinks** must be *Post name* (it already is on the live site). The timezone stays *Chicago*.
4. **Content freeze.** The payload is a snapshot of heartlandk9s.org taken on **2026-09-11**. From that date until the import has run, do not edit pages, registry pages or media on the live site: the import applies the payload to every matched page and attachment on its first pass without asking (that is what makes the migration a single click), so an edit made after the snapshot is replaced and can only come back through a rollback of the whole run. Do not delete or replace media files either — the content-only payload expects them at their current paths. Note any change you had to make in the meantime and re-apply it in the new site after the import.
5. Plan 15–20 minutes of low traffic; the site stays online, but the front page changes look as soon as the theme is activated.

### B.2 Install and activate (theme first is required by the importer)

1. **Plugins → Add New → Upload Plugin** → `heartland-k9s-core.zip` → **Activate**. Activation only registers the content types, capabilities, seed redirect rules and one small table; it changes nothing visible.
2. **Appearance → Themes → Add New → Upload Theme** → `heartland-k9s.zip` → **Activate**. From this moment the site renders with the new theme (Avada pages show their raw content until the import runs — that is expected and takes only the minutes below). The importer refuses to run until this theme is active because menu locations and page templates are theme-scoped.
3. **Plugins → deactivate** *Avada Builder*, *Avada Core*, *FooGallery* and *FooBox* now, before the import. Nothing needs them once the Heartland theme is active, and the importer's writes to the existing pages and media must not run through their save hooks and content filters. Leave them **installed** (deactivated) until the migration is signed off; the pre-flight (B.4) shows a *Note* naming any of them that is still active. The Avada theme itself is simply no longer active; Slim SEO can stay active.

### B.3 Upload the content-only payload

**Heartland → Setup & Import** → *1. Payload* → **Upload a payload ZIP** → choose `heartland-k9s-payload-lite.zip` → **Upload & unpack**. At ≈1.5 MB it is far below any host's upload limit (the screen shows the limit next to the field). It is unpacked into a protected folder under `wp-content/uploads/` and deleted automatically after a clean import. The success notice reads *Content-only payload unpacked: 363 records; 244 media files are reused from this site's Media Library*.

You never need SFTP or WP-CLI for this path. (The *Full payload: use a directory on the server* box further down the screen is only for the 663 MB full payload; ignore it here.)

### B.4 Run the import — Heartland → Setup & Import

1. Read *2. Pre-flight*. Everything must say **OK**: PHP ≥ 8.1, WordPress ≥ 6.4, active theme *Heartland Canines for Veterans*, pretty permalinks, uploads writable, **Legacy builder plugins: none active** (a *Note* here names a plugin from B.2 step 3 that is still active — deactivate it first), the payload (*Content-only payload: 363 records …*). Then two lines about this site:
   - **Existing content detected** — how many pages, legacy BarKode pages and attachments the payload matches on this site: on heartlandk9s.org that is all of them, *24 pages, 16 legacy BarKode pages and 244 attachments*.
   - **Media reuse** — *Content-only payload: 244 of 244 media files found on this site.* Every live media file the payload needs is in the Media Library (matched by attachment id + file name, or by its upload path if the id differs). A *Note* here lists files that are missing — those records fail in the run and the pages using them are skipped; a **Failed** line (fewer than 95 % found) means this is not the site the payload was extracted from, the run refuses to start, and the full payload is the right tool (B.7).
   A failed check has a link to the screen that fixes it.
2. Under *3. Run* the checkbox **Existing site: adopt matching content** is already ticked because existing content was detected. **Leave it ticked** — the content-only payload cannot run without it (the run stops in *validate* with *This is a content-only payload: enable 'Existing site: adopt matching content' …* otherwise). Leave *Overwrite conflicts* unticked.
3. Click **Dry run**. It walks every step without writing anything and fills the counts table. Check the **Adopt** column: `media_files` shows **244** (every live image reused, none uploaded), `posts_stub` / `posts_hierarchy` / `posts_content` show the pages and the 16 registry pages; **Create** holds only what is genuinely new (the 6 design images — one of them is byte-identical to a live logo and is listed as *Skip*, shared —, the reference pages that never existed on the old site, records, menus, redirects). **Fail** must be 0 everywhere. Warnings, if any, are listed under the table. Nothing has changed on the site yet.
4. Click **Import**. Progress is shown per step; on a typical host it takes 3–10 minutes, mostly generating the theme's image sizes for the reused files (only the sizes that do not exist yet). If the browser tab closes, reopen the page and click **Resume**. When it finishes, the status shows *Complete* with 0 errors and the **Next steps** list appears (B.5). The uploaded payload folder is deleted automatically.

CLI equivalent (as an administrator, after unzipping the lite ZIP anywhere the CLI can read):

```
wp hk9 preflight /home/<site>/hk9-payload-lite
wp hk9 import /home/<site>/hk9-payload-lite --adopt-existing --dry-run --user=<admin>
wp hk9 import /home/<site>/hk9-payload-lite --adopt-existing --user=<admin>
```

(`wp hk9 import` refuses a content-only payload without `--adopt-existing`, with the same message as the admin screen.)

What happens to what is already there:

| Existing content | What the import does |
|---|---|
| The pages (Home, 5 Questions, Contact, Donate, Events, Privacy Policy, …) | **Converted in place.** Same post id, same slug, same URL, same publish date and author. Title, page template, sections and content come from the payload; the old Avada shortcode content is replaced (and kept in the import map so a rollback can put it back). |
| The 16 legacy BarKode registry pages (`/larry-and-archie-service-k9/` …) | **Converted into BarKode records** with the same id and slug. They now live at `/barkode/<slug>/`, are `noindex` and excluded from search/feeds/REST, and the old root path (the one printed on QR patches) answers with a single 301 to the record. |
| The media library (244 files) | **Reused as they are** — same attachment ids, same files at the same paths, nothing is uploaded, titles/alt texts/captions are kept, only the image sizes the new theme needs and that do not exist yet are generated (a file that was uploaded twice keeps both copies, each under its own id; an attachment whose id changed but whose file is still at the same upload path is matched by that path). The content-only payload carries no copy of these files, so a file that is missing from the Media Library makes its record **fail** (*Attachment not found on this site (expected 2023/05/…png); use the full payload*) and the pages using it are skipped — see B.7. The 6 new images the design needs are added (one is identical to a live logo and shares it). |
| Menus | The old Avada menu is left untouched; the four new menus (Primary, Footer Quick Links, Footer Get Involved, Legal) are created and assigned to the theme's locations. |
| Front page / posts page (Reading settings) | Home (page 6) stays the static front page; a new *News* page becomes the posts page. |
| Settings | All Heartland Settings (contact details, destinations, header/footer text, form recipients) and the legacy redirects are written. Existing WordPress options are not touched. |

### B.5 After the import

1. **Verify**: open the front page, the primary and footer menus, `/barkode/`, one migrated page (e.g. `/5-questions/`) and one QR path (e.g. `/hk923-005/` → record page). **Heartland → Redirects → Test** lists every legacy rule with its result.
2. **Old builder plugins**: *Avada Builder*, *Avada Core*, *FooGallery* and *FooBox* were deactivated in B.2; if one is still active, deactivate it now (the *Next steps* box links to Plugins). Delete them — and the Avada theme — once you are happy with the migrated site.
3. **Slim SEO** can stay if you want its sitemap/meta features: the theme detects it and does not print duplicate meta tags. It can also be removed — the theme ships its own titles, descriptions, Open Graph tags and `noindex` rules.
4. Review the open decisions in `docs/unresolved.md` (office days, imagery, PayPal retirement, registry privacy scope, form recipients) and set them under **Heartland → Settings**; confirm the form recipient addresses (**Heartland → Settings → Forms**) and send one test message through the contact form.
5. **Payload folder**: the ZIP uploaded on the admin screen was removed automatically after the clean run (the *Next steps* box says so). If you used the CLI with a directory instead, delete that directory now — it contains registry data.
6. Optional: Fathom site id under **Heartland → Settings → Analytics**; site icon under **Settings → General**.
7. **After sign-off — purge the pre-migration copies of the registry pages.** Two places still hold the old registry page content (handler and veteran details as the Avada pages showed them): the **revisions** WordPress kept of those 16 pages over the years (they now hang off the BarKode records), and the import map's **pre-images** (`wp_hk9_import_map.before_data`), which exist so a rollback can restore the old pages. Once you are sure you will not roll the import back, remove both (WP-CLI, as an administrator):

   ```
   # revisions of the 16 records (keeps the records themselves)
   wp post delete $(wp post list --post_type=revision --post_parent__in=$(wp post list --post_type=hk9_barkode --post_status=any --format=ids | tr ' ' ',') --format=ids) --force
   # pre-images of every import run (the import map keeps its bindings, so re-runs stay idempotent)
   wp db query "UPDATE $(wp db prefix)hk9_import_map SET before_data = NULL"
   ```

   (The first command reports "Please specify one or more ids" when no revisions are left — that is fine.) After that, *Roll back* on the import run can no longer restore the old pages (it would only drop the bindings); your B.1 backup remains the way back.

### B.6 Rollback (if you need to go back)

**Heartland → Setup & Import → 4. Runs & rollback** → *Roll back…* on the import run → type `ROLLBACK` → confirm (or `wp hk9 rollback --run=<id> --user=<admin>`). It removes everything the run created (new pages, records, menus, the new images, settings/redirects it wrote) and **restores every adopted object to its pre-import state**: pages get their Avada content and template back, the registry records become the old pages again, media stay as they were, the Reading settings and menu locations return, and the import map forgets the adoptions. Anything edited on the site *after* the import is reported as *skipped* and left alone unless you tick *Force*. Then re-activate Avada and its plugins. Running the import again later adopts everything afresh — also after a run that was interrupted between converting a registry page and recording it: an already-converted record is simply bound again, never duplicated. A rollback is only possible while the pre-images exist (B.5 step 7).

### B.7 When the content-only payload is not enough

The lite ZIP only works where the live files are. Use the **full** payload (`heartland-k9s-payload.zip`, ≈663 MB — put on the server by SFTP under `wp-content/uploads/hk9-payload-2026/` or given to the CLI, exactly as in §A step 4) with *Existing site: adopt matching content* **ticked** when:

- the pre-flight *Media reuse* line **fails** (fewer than 95 % of the 244 files found — a staging copy without uploads, a different site, an emptied Media Library);
- it shows a *Note* with a few missing files and you want those records imported rather than skipped: the full payload creates exactly the missing attachments (everything that exists is still adopted, nothing is uploaded twice) — running it after a lite import is safe and only fills the gaps;
- you are migrating into any other existing installation (not the live site).

Notes for other existing installations: the same rules apply — pages are adopted by id/slug, attachments by id + file name, by upload path, or (full payload only) by identical bytes (SHA-256), menus and terms by name/slug; records you have edited after an import are preserved on re-runs (*conflict*) unless you tick *Overwrite*; with the adopt checkbox unticked the importer never touches pre-existing content and resolves slug collisions the WordPress way (`-2`), reported in the log — and refuses the content-only payload.

## C. Local development stack (what this repo uses)

```
docker compose -f docker/docker-compose.yml up -d
docker/setup.sh            # installs WordPress, activates theme + plugin
docker/setup.sh --import   # …and imports ./payload
npm install && npm run build   # theme CSS/JS (sass + esbuild); fonts/icons: npm run build:fonts, npm run build:icons
npm run media:fetch        # (re)downloads the 251 media originals into payload/media (resumable)
npm run payload:build      # rebuilds payload/manifest.json from payload-src/
node tools/build-payload.mjs --lite   # builds payload-lite/ (content-only payload for the live site)
npm run package            # builds dist/*.zip + SHA256SUMS (theme, plugin, full payload, lite payload)
```

Site: http://localhost:8093 (admin/admin) · Mailpit (captured email): http://localhost:8094 · WP-CLI: `tools/wp.sh <args>`.

## D. Going live checklist

1. Point DNS only after the imported site has been reviewed (see `docs/unresolved.md` for the business decisions that need sign-off: office days, imagery, PayPal retirement, registry privacy scope, form recipients).
2. Keep the 16 legacy BarKode URLs working: they are printed on physical QR patches. The plugin serves each as a one-hop 301 to `/barkode/<same-slug>/` (see **Heartland → Redirects**; `wp hk9 redirects test`).
3. Registry records are excluded from search, sitemaps, feeds, REST and embeds and carry `noindex`. Do not add them to menus.
4. Remove the payload folder after a successful import when it was placed by hand (the finalize step deletes an admin-uploaded copy — the lite ZIP of §B — automatically).
5. Ensure outbound mail works (SMTP plugin of your choice) and test the contact and application forms once in production.

## E. Backup / restore

The import writes a log to `wp-content/uploads/hk9-import/<run>.log` (admin-only download) and records every created/updated object in the `wp_hk9_import_map` table with content hashes. Together with your pre-import backup, that gives three recovery levels: rollback of a run, restore of the database backup, or a fresh install + import.
