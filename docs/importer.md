# Importer — payload format, behaviour, CLI and admin screen

The importer (`plugin/heartland-k9s-core/src/Import/`) loads a **payload directory** into WordPress: media, terms, pages and records, menus, settings, reading options and legacy redirects. It is idempotent (re-running creates nothing twice), resumable (cursor state survives timeouts and kills), conflict-aware (edits made on the site are preserved unless you overwrite) and reversible per run (rollback). Since plugin 1.1.0 it also has an **existing-site mode** ("adopt matching content", §2.1) that migrates the live heartlandk9s.org install in place — same post ids, same URLs, no `-2` duplicates — and a **pre-flight** panel/command (§2.2). Plugin 1.1.1 adds the **content-only ("lite") payload** (§1.1): the same manifest without the live media files, small enough to upload on the admin screen, satisfied entirely from the live site's Media Library.

Design source of truth: `discovery/design-validation.json` → `importDesign` + `importJudge` (all judge fixes 1–11 are implemented; see "Where the judge fixes live").

## 1. Payload layout (`hk9-payload/1`)

```
payload/
  manifest.json          { format, generated_at, sources, requires:{theme}, records:[...] }
  content/<key>.html     block markup with {{tokens}}  (key with ':' → '__')
  media/<key>/<file>     original files referenced by attachment records
```

Build it with `node tools/build-payload.mjs` (see §6). Record shapes are exactly those in `docs/ARCHITECTURE.md` §8:

| type | required | notes |
|---|---|---|
| `attachment` | `key, file, sha256, mime` | `size, title, alt, caption, description, date, parent?:{{post:K}}, sensitive?`; in a lite manifest a live record has `file: null` + `basename, live_path, bytes` (§1.1) |
| `term` | `key = term:<tax>:<slug>, taxonomy, slug, name` | `description?, parent?` |
| post-like (`page`, `post`, `hk9_*`) | `key, type, title, slug` | `status, parent, template, content, excerpt, date, menu_order, featured, meta:{k:{type,value}}, terms:{tax:[...]}, sensitive?` |
| `menu` | `key, name` | `locations:[...], items:[{key, kind:post_type\|custom, object?, url?, title?, target?, classes?, parent?:itemKey, order}]` |
| `option` | `key = option:<name>, name, value` | `merge: deep (default) \| replace`; only `hk9_*` options plus a small site-info allow-list (`blogname`, `blogdescription`, `timezone_string`, … filter `hk9/import/allowed_options`) |
| `reading` | `key = reading` | `show_on_front, page_on_front, page_for_posts, posts_per_page` |
| `redirect` | `key, from` | `to: "{{post_url:K}}" \| "/path/" \| {type:"record", slug}`, `status: 301\|302\|410` |

Tokens: `{{media:K}}` → attachment id, `{{media_url:K}}` (scaled/full URL), `{{media_url:K|original}}`, `{{post:K}}` → id, `{{post_url:K}}` → permalink, `{{term:tax:slug}}` → term id. A string that is exactly one id token becomes an integer (typed meta); embedded tokens are replaced textually (block JSON attributes and HTML alike).

### 1.1 Content-only payload (`"lite": true`)

`node tools/build-payload.mjs --lite` (output `payload-lite/`, packaged as `dist/heartland-k9s-payload-lite.zip`, ≈1.5 MB) writes the **same manifest** as the full build with two differences:

- every `live:media:<id>` attachment record carries **`"file": null`** plus `"basename"` (the payload file name, e.g. `Concept-1-rocker-outlined-2.png`), `"live_path"` (the uploads-relative path of the live file derived from the media index `source_url`, e.g. `2023/05/Concept-1-rocker-outlined-2.png` — the *original* upload, not the `-scaled` rendition WordPress serves for big images), `"sha256"` and `"bytes"` (the `size` field is kept too); `media/` holds only the `ref:asset:*` files (6 — `favicon.svg` is rejected by the importer and left out, which also keeps the ZIP free of the SVG entries the admin upload pre-scan refuses); `content/` is identical;
- the top-level flags `"lite": true` and `"lite_message"` (a human sentence saying what the payload is and that it needs existing-site mode).

The build verifies each live record's sha256 against the full media in `--media-from` (default `payload/`) when the files are there and prints the resulting size. A record with `file: null` is **only** accepted when the manifest is flagged lite — a truncated full payload never passes as one.

How the importer treats a lite payload:

| step | file-less record (`file: null`) |
|---|---|
| `validate` | shape: `basename` + uploads-relative `live_path` required; the file existence / size / MIME / sha256 checks are skipped. Fatal unless **`mode.adopt` is on** (*This is a content-only payload: enable 'Existing site: adopt matching content' — it reuses the media already in this Media Library — or use the full payload.*). New item `lite`: counts how many file-less records have their attachment on this site (`Preflight::lite_media()`, the same rule `media_files` applies), logs *Content-only payload: N of M media files found on this site*, writes the first 10 missing paths to the log (`missing on this site: <live_path>`) and is **fatal below 95 %** (`Preflight::LITE_MIN_FOUND`) — the payload is being run somewhere other than the site it came from; with a few missing it only warns. |
| `media_files` | adopt-only: `Adopt::attachment_match()` — the attachment with the live id whose file (or `original_image`) has the basename, or, when the id does not match (a site whose ids were renumbered), the non-trashed attachment whose `_wp_attached_file` **is** `live_path` (or its `-scaled` / `-rotated` rendition with `original_image` = basename); logged `ADOPT #id by id, …` / `by path, …`. No sha256 / shared-bytes fallback, never a copy or sideload: no match → **FAIL** *Attachment not found on this site (expected `<live_path>`); use the full payload* and the record's dependants skip. The 6 `ref:asset:*` records import from `media/` exactly as in the full payload (the logo is byte-identical to `live:media:3028` and shares it). |
| `media_sizes` | unchanged — fills only the sizes `wp_get_missing_image_subsizes()` reports. |
| pre-flight | the *Payload* line says *Content-only payload: …*; a **Media reuse** line reads *Content-only payload: N of M media files found on this site* — pass when N = M, *Note* listing the first 10 missing paths when ≥ 95 %, **Failed** (blocks: `ok = false`, and the run's validate step is fatal too) below; the JSON carries `lite: {total, found, by_path, required, missing[]}`. A record already mapped to an attachment that still exists counts as found, so the line stays N of N after the import. |
| admin / CLI | the upload field explains that the lite ZIP uploads here and only the full payload needs SFTP/CLI; the payload card shows *Content-only payload: 244 media files are not shipped …*; `wp hk9 import` refuses a lite payload without `--adopt-existing` before a run is recorded. |

Dates are ISO-8601 and converted to `post_date_gmt` + site-local `post_date`. Page templates may be given as `about.php` or `page-templates/about.php` (stored as `page-templates/about.php`); a template the active theme does not ship yet is a **warning** — `_wp_page_template` is stored anyway.

Meta values are written through `update_post_meta` (inside `wp_update_post` via `meta_input`) so registered `hk9_sec_*` sanitizers run; unregistered keys are written as-is. Only keys present in the payload are written; nothing is ever deleted.

Records marked `sensitive: true` (BarKode registry) are logged as `sensitive#<hash>` only; their attachments get a generic title and empty alt/caption/description.

## 2. Steps (in order, each cursor-resumable)

| step | what it does |
|---|---|
| `validate` | manifest shape, unique keys, token graph (dangling → fatal), ISO dates, permalinks not "Plain", active theme = `requires.theme` (default `heartland-k9s`), uploads writable, post types/taxonomies registered (per record), templates present (warning); per attachment: containment inside the payload dir, existence, size cap (64 MB, filter `hk9/import/max_file_bytes`), declared + real MIME in the allow-list (jpeg/png/gif/webp/avif/pdf — HEIC/SVG excluded; filter `hk9/import/allowed_mimes`), sha256; finally hashes pre-existing attachments lacking `_hk9_sha256` so manual uploads with identical bytes are adopted. File problems fail only that record. |
| `terms` | `term_exists` adopt or `wp_insert_term`. |
| `media_files` | in existing-site mode a `live:media:<id>` record **first** adopts the attachment with that id when its file name matches (§2.1) — before any byte-identical match, so both halves of a duplicated live upload keep their own attachment; then dedupe by sha256 (map table → `_hk9_sha256` meta → dry-run cache); copy to `wp_tempnam()` and `media_handle_sideload()` with pre-slashed `post_data` + `meta_input`; only the `-scaled`/rotated original is produced here. Every first binding to a pre-existing attachment (by id or by sha256) is reported as **adopt**. **Shared attachments:** when several payload records carry byte-identical files (the real payload has 10 such pairs, e.g. `live:media:3028` + `ref:asset:heartland-k9s-logo`, and 9 `live:media` pairs such as `2149` + `2806`) and the later record has no live attachment of its own, the first record in manifest order creates (or adopts) the attachment and owns its title/alt/caption/description/date; every later record is bound to the same attachment as a *secondary* row that never writes those fields (logged `SKIP shares attachment #id with <owner>`). On the live site every `live:media` half exists, so the 9 live pairs are adopted as 18 separate attachments and only `ref:asset:heartland-k9s-logo` shares (`#3028`). Its map row mirrors the owner's `db` hashes with its own `src` hashes, so both rows hash-match the object, re-runs skip (no conflict) and `{{media:K}}` / `{{media_url:K}}` resolve for either key. Dry runs remember the would-be creator per sha so the second record is reported as `skip`, not `create`. |
| `media_sizes` | `wp_update_image_subsizes()` per image (skips sizes that exist). Dry runs use `wp_get_missing_image_subsizes()` for the verdict, so small images that can never receive every registered size are reported as `skip`, not `update`. |
| `posts_stub` | every post-like record as a **draft** with no parent (`_hk9_import_pending`); in existing-site mode a matching page is adopted instead (and a page matched by a BarKode record is converted in place — a conversion that fails leaves the page a page with its template, no row bound), see §2.1. |
| `posts_hierarchy` | parent-first: parent, slug, final status, template, excerpt, date, menu_order, featured, terms and section meta in **one** `wp_update_post()` (meta via `meta_input`, so the revision carries it). Meta containing `{{post_url}}` is deferred to `posts_content`. Before a page is published or renamed, an **attachment** holding the page's slug at the same level (WordPress checks pages *and* attachments for hierarchical slugs, so an old `about.jpg` upload with slug `about` would publish the About page as `about-2` and hand `/about/` to the file) is renamed to `media-<slug>` (logged; dry runs warn). A slug WordPress still had to change is reported as a warning naming the stored slug. |
| `reading` | asserts published pages, sets `page_on_front`, `page_for_posts`, then `show_on_front`, `posts_per_page`. |
| `posts_content` | bakes tokens into content (block count compared before/after; leftover `{{` fails the record) and writes deferred meta in the same update. |
| `menus` | create/adopt `nav_menu` by name; items parent-first with `menu-item-status=publish`, object ids for post types; locations merged into `nav_menu_locations`. |
| `options` | deep-merge (or replace) per top-level key. |
| `redirects` | merge into `hk9_redirects` (`{version, rules:{<normalized-from>:{to,status,enabled,seed,note,updated,by}}}`); rejects `from == to`, walks the merged table for cycles, warns when `from` matches a published post. |
| `finalize` | attaches media to `parent` posts (a secondary record never re-parents a shared attachment), reports orphans (map rows no longer in the payload — never deleted), soft-flushes rewrites, deletes an **uploaded** payload copy (`uploads/hk9-payload-*` only) after a clean run; a payload given as a server path — including the read-only bind mount `wp-content/hk9-payload` of the dev stack — is never touched (logged "left in place"). |

### 2.1 Existing-site mode (`mode.adopt`)

Admin checkbox **Existing site: adopt matching content** (`#hk9-import-adopt`, pre-ticked when the pre-flight detects existing content) or CLI `--adopt-existing`. Off, the importer behaves exactly as before. On, an **unmapped** record is first matched against what is already on the site (`Import\Adopt`, read-only), and a match is *bound* instead of created. Adoptions get their own action — **adopt** — in the per-step counts (admin table column, CLI `adopt` column) and in the run log as `ADOPT #<id> …` (for a sensitive record the line is `sensitive#<hash> ADOPT #<id>`: the id is the audit trail, everything else is withheld).

Matching rules:

| record | matched against | notes |
|---|---|---|
| `live:page:<id>` (page) | the page with that id, when it is not trashed and its slug equals the record slug | if the id exists but the slug (or post type) differs → warning + fall back to the slug rule |
| any page record (`ref:page:*`, `new:page:*`, fallback for `live:page:*`) | `get_page_by_path()` of the record's path — its slug, or `parent-slug/child-slug` when the record has a parent | root-level slugs only match root-level pages |
| `live:barkode:<id>` (hk9_barkode) | the **page** with that id (or, failing that, the page at the record slug) | converted in place: `_wp_page_template` removed, `post_type` → `hk9_barkode`, `clean_post_cache()`; id + slug survive, the legacy `/<slug>/` redirect resolves to `/barkode/<slug>/`; a non-page is never converted. A conversion `wp_update_post()` rejects puts the template back, drops the reservation and fails the record (the page is untouched) |
| `live:barkode:<id>` whose post **already is** an `hk9_barkode` (at that id, or at the record slug) | a page converted by an earlier run whose map row is gone — a crash between the conversion and `Map::bind()`, a reset map, a plugin re-install | re-adopted **as it is** (`convert = false`; the log detail reads `… (already converted; binding restored)` — withheld for sensitive records, whose `ADOPT #id` line carries only the id), so a lost binding never creates a `<slug>-2` duplicate; the pre-flight counts it as a registry record |
| `live:media:<id>` (attachment) | the attachment with that id when the basename of its `_wp_attached_file` (or of its `original_image`) equals the payload file's basename, case-insensitively, and the file exists on disk; failing that, for a record carrying `live_path` (lite payload), the attachment whose `_wp_attached_file` is that path (or its `-scaled`/`-rotated` rendition) — logged `by path` | no hashing, no re-upload; the sha256 index (`_hk9_sha256` / validate prehash) stays the fallback for records that ship a file (a file-less record fails instead, §1.1). Bytes are not compared for the match, but when the prehash sha256 of the live file differs from the record's (a file replaced in place under the same name) a **warning** says so — the attachment is adopted as-is |
| terms, menus | by slug / by name (unchanged) | now reported as adopt |

Never adopted: trashed posts, posts bound to another payload key in the map (or carrying another key's `_hk9_source_key`), posts of the wrong type. A matched page whose status differs from the record's (a private, pending or draft page holding a payload slug) **is** adopted — the first bind applies the payload status — but a warning (`Page #id is "private" on this site; the payload sets it to "publish" on the first import …`) makes the visibility change visible in the dry run and the log. The core Privacy Policy placeholder adoption (`PostsStub::adopt_core_placeholder`, a pristine draft `privacy-policy`) still applies on fresh installs; in existing-site mode the same page is simply adopted by id/slug first — the two never conflict.

What an adoption writes:

- **Pages**: the row is bound with `created_by_run = NULL`, `adopted_by_run = <run>` and the post is flagged `_hk9_import_pending`, so `posts_hierarchy` / `posts_content` apply title, status, template, excerpt, menu_order, parent, featured image, section meta, terms and content from the payload **unconditionally on the first bind** (the old builder content is the pre-migration state, not an edit); id, slug, publish date and author are kept (`PostFields::for_adopted()` drops `date`). A record without a content file leaves the adopted page with **empty** content (the Avada shortcodes go). Every replaced field is stored as a pre-image under the run, so later runs use the normal hash/conflict logic and a rollback restores it.
- **Converted registry pages**: additionally the pre-image `post_type = page` + the old `_wp_page_template` are recorded; `PostFields` knows the `post_type` field (page ↔ hk9_barkode only) and treats an absent meta key as `null` (restoring `null` deletes the key), so rollback returns the page exactly, without registry meta.
- **Attachments adopted by id**: none of the attachment's fields is written (only the repair markers `_hk9_source_key` and, when absent, `_hk9_sha256` are added as post meta); the row records the database values of title/caption/description/alt/date as-is (`db` = `src` hash semantics: untouched until the payload changes). `media_sizes` then generates only the sizes `wp_get_missing_image_subsizes()` reports. Exception: a `sensitive` attachment record still applies its generic title / empty alt (pre-image kept) — the real payload has none.
- **Dry runs** remember would-be adoptions in `state.dry_adopted` so `posts_hierarchy`, `posts_content`, `media_sizes` and the redirect "shadows a published post" check report the real outcome (adopt / sizes to fill / no shadow warning for a page that will be converted).

**Rollback** of a run that adopted objects (`--run=<id>`): pre-images are restored under the usual "skipped when modified since" rule, then every row with `adopted_by_run = <run>` is **un-adopted** — the map row and the `_hk9_source_key` / `_hk9_import_run` / `_hk9_import_pending` markers are dropped, the object itself is kept. The report lists them under `unadopted` (`un-adopted (kept)` in the admin, `unadopted … (#id, kept)` in the CLI). A later import adopts them afresh.

### 2.2 Pre-flight

`Import\Preflight::run($dir)` — shown on the admin screen (card *2. Pre-flight*, re-fetched over `GET hk9/v1/import/preflight?path=` when the payload path changes and after a run) and printed by `wp hk9 preflight [<dir>] [--format=json]`: PHP ≥ 8.1, WordPress ≥ 6.4, active theme = `requires.theme` (with an Appearance → Themes link when not), pretty permalinks, uploads writable, for a lite payload the **Media reuse** line (§1.1), **Legacy builder plugins** (a *Note* naming any active plugin whose directory is `fusion-builder`, `fusion-core`, `foogallery` or `foobox*` — `Preflight::LEGACY_PLUGIN_SLUGS` — with a Plugins link: they must be deactivated before the import so its `wp_update_post()` / `wp_insert_post()` writes do not run through their `save_post` / content filters; it never blocks the run), payload found + record counts, and **Existing content detected** — computed cheaply from the manifest with the very rules the run applies (`Adopt::post_candidate()` / `Adopt::attachment_candidate()`, excluding keys that are already mapped): pages, registry records (pages to convert *and* already-converted records that only need their binding back) and attachments by live id + file name + file on disk. When the total is > 0 the adopt checkbox is pre-ticked and the panel explains what adoption does; after the import it drops to 0 (everything is mapped). A completed real run shows a **Next steps** checklist: verify front page/menus, make sure the old builder plugins (Avada Builder, Avada Core, FooGallery, FooBox — deactivated before the import) are still inactive and delete them once happy, review `docs/unresolved.md`, delete the payload folder when it was a server path.

## 3. Idempotency, conflicts, overwrite

Table `{$wpdb->prefix}hk9_import_map` (created by `Import\Map::activate()` via dbDelta on activation, lazily if missing or when `hk9_import_map_version` ≠ `Map::DB_VERSION` — 1.1.0 bumped it to `2` to add a column): `source_key` UNIQUE, `object_type`, `object_id`, `sha256`, `created_by_run` (NULL = **adopted**: the object pre-existed — a page/attachment/term/menu matched on the site, a secondary row of a shared attachment, a settings row — and is never deleted by rollback), `adopted_by_run` (the run that bound a pre-existing object; rolling that run back un-adopts it, §2.1), `last_run`, `payload_hash`, `field_hashes` JSON, `before_data` JSON, `status` (`reserved` → `active`). Postmeta mirrors `_hk9_source_key`, `_hk9_import_run`, `_hk9_sha256` exist for repair only.

Per field the row stores `{db: hash(value re-read from the DB after our write), src: hash(resolved payload value)}`:

- no row → **create** (the row is reserved *before* the object is created, so a concurrent tick cannot duplicate it; an orphan created in a crash window is re-found through `_hk9_source_key`);
- `db` unchanged → untouched by editors → apply only if the payload changed;
- `db` changed → an editor edited it → **conflict**: skipped and counted, unless `--overwrite` (then applied and the pre-image kept);
- fields that were skipped as conflicts keep their stored hashes, so the conflict persists until resolved;
- a `meta:` key that does not exist and one stored as an empty string hash the same (`Reconcile::hash()`, plugin 1.3.1 — WordPress reads both back as `''`): an empty payload value therefore never creates an empty meta row, and a record whose empty fields were dropped by a later save is not reported as an editor conflict on the next run (before 1.3.1 every re-saved person / partner / campaign showed 3–4 spurious `meta:` conflicts). Rollback's "modified since import" check and its re-hash after a restore use the same rule.

Hashes are canonical (sorted keys, normalised line endings) and always taken from read-back values, so kses/sanitizer normalisation never produces false conflicts. After a write the `db` hash of **every non-conflicting field** is refreshed from the read-back (not only the applied ones): a write can change another field as a side effect — publishing a page makes WordPress uniquify its slug — and recording what the database actually holds keeps re-runs from reporting a phantom conflict.

One object may be bound to several rows (only attachments: byte-identical payload files). The oldest row is the **owner** (`Map::owner()`; the creating row is reserved before the object exists, so it is always the oldest); the others are secondary rows with `created_by_run = NULL` whose `db` hashes are re-mirrored from the owner on every run. An editor's edit therefore surfaces as a conflict on the owner record only; `--overwrite` re-applies the owner's values and the secondaries re-sync.

## 4. Rollback (`--run=<id>`)

Order: reading/options/redirect pre-images are restored **first**, then menu items → menus (a created menu's theme locations go back to the menus they pointed at before the run — the `location:*` pre-image is the one pre-image a created object keeps) → posts (children first) → attachments → terms. Objects *created* by the run are deleted only when every recorded field hash still matches (else reported "skipped: modified") — `--force` deletes them anyway. Objects only *updated* by the run get the changed fields restored under the same rule. Adopted objects (pre-existing pages, legacy registry pages converted to records, live attachments matched by id, manual uploads matched by sha256, menus adopted by name) are never deleted: their pre-image is restored (a converted record becomes a page again with its old template and content) and, when this run is the one that adopted them, their binding is dropped (`unadopted`).

An object bound to several map rows (shared attachment) is decided **once**, when its owner row is processed: a field counts as modified only when its current value matches none of the rows' `db` hashes (so a legitimately importer-written value is never mistaken for an edit, while an editor's change that no row recorded still protects the object), the deletion is logged `deleted attachment #id (also bound to <keys>)`, and every row bound to the object is dropped from the map — the secondary records simply recreate/re-adopt on the next import. Later rows for the same object are ignored in that rollback.

## 5. State, lock, logs

- `hk9_import_state` (autoload no): `{run_id, payload_dir, mode:{dry_run, overwrite, adopt, batch, budget, until_step}, status: idle|running|paused|failed|done, step, cursor, step_total, totals, counts[step]:{create,adopt,update,skip,conflict,fail}, errors[], warnings[], failed_keys, dry_created, dry_adopted, …}`.
- `hk9_import_runs`: history (last 20) with counts and rollback status.
- `hk9_import_lock`: one atomic `UPDATE … WHERE` on the options row (`token|expires`, 60 s TTL refreshed every 10 s while a tick runs). A killed process leaves the lock until it expires; the CLI waits for it.
- `hk9_import_payload`: the currently selected payload directory.
- Log: `uploads/hk9-import/<run>.log` (dir has `Options -Indexes` + deny rules + `index.html`; run ids are unguessable). Download via `admin-post.php?action=hk9_import_log&_wpnonce=…&run=…`.

Resume semantics: a **paused/interrupted** run continues at its cursor; resuming a **done/failed** run starts a new pass over the same run id (already-imported records skip; failed ones get another chance).

## 6. Building a payload

```
node tools/build-payload.mjs [--src=payload-src] [--out=payload] [--media-index=payload/media-index.json] [--copy-media] [--no-verify]
node tools/build-payload.mjs --lite [--out=payload-lite] [--media-from=payload]        # content-only payload (§1.1)
```

Merges `payload-src/records/**/*.json` (one record object or an array per file) + `payload-src/content/*.html` + `payload/media-index.json` (written by `tools/fetch-media.mjs`: `{items:{key:{file, sha256, bytes, mime, width, height, title, alt, caption, description, date, parent?, sensitive?}}}`; missing → zero media + warning) into `payload/manifest.json` + `payload/content/`. It validates every record shape and every `{{token}}` (dangling → build fails), verifies media sha256 (`--no-verify` to skip) and prints counts. Optional `payload-src/sources.json` (`{sources, requires}`) and `payload-src/known-terms.json` (pre-seeded term keys tokens may reference). `--copy-media` copies `<src>/media/**` into the output (used by the fixture).

Test fixture: `tools/fixtures/payload-mini/` (see its README) → `plugin/heartland-k9s-core/tests/payload-mini/`, which the docker stack sees at `/var/www/html/wp-content/plugins/heartland-k9s-core/tests/payload-mini`.

## 7. CLI

```
wp hk9 preflight [<dir>] [--format=table|json]
wp hk9 import <dir> [--dry-run] [--overwrite] [--adopt-existing] [--resume] [--step=<name>] [--batch=<n>] [--budget=<s>] [--quiet-progress] --user=<admin>
wp hk9 import --resume --user=<admin>
wp hk9 status [--run=<id>] [--format=json]
wp hk9 rollback --run=<id> [--force] [--yes] [--dry-run] --user=<admin>
wp hk9 reset-state --yes --user=<admin>
```

`wp hk9 preflight` prints the same facts as the admin pre-flight panel (exit 1 when a check fails) plus an *Existing content* line when adoption would take something over. `--adopt-existing` turns on existing-site mode (§2.1); the summary table has an `adopt` column. A content-only payload (§1.1) is refused without `--adopt-existing`.

`--user` must be an administrator with `unfiltered_html` (kses would otherwise strip block attributes); the command refuses otherwise. `--step=<name>` runs up to and including that step, then pauses. Exit code 2 = completed with record errors (fix the payload, `--resume`).

## 8. Admin screen

Heartland → **Setup & Import** (`admin.php?page=hk9-import`, `manage_options`): *1. Payload* — upload a payload ZIP (unpacked into `uploads/hk9-payload-<random>/` with deny rules + `index.html`; archives containing dotfiles, traversal paths or script files are refused before extraction (pre-scanned with ZipArchive); the copy is deleted after a clean import) or a server path (`uploads/hk9-payload-*`; bind-mounted dev paths when `HK9_LOCAL_DEV` is defined). *2. Pre-flight* (§2.2). *3. Run* — checkboxes **Existing site: adopt matching content** and **Overwrite conflicts**; buttons Dry run / Import / Pause / Resume / Retry failed / Reset state; progress bar, per-step counts (Create / Adopt / Update / Skip / Conflict / Fail), error list, warnings, log download, Next steps after a completed import. *4. Runs & rollback* — runs table (mode shows `+ adopt existing`) with typed-confirmation rollback (`ROLLBACK`, optional Force).

REST (`manage_options` + `wp_rest` nonce): `GET hk9/v1/import/status`, `GET hk9/v1/import/preflight[?path=]`, `POST hk9/v1/import/{start,step,pause,resume,retry,rollback,reset}` (`start` takes `dry_run`, `overwrite`, `adopt`, `path`, `batch`, `budget`, `resume`). Server paths are accepted only inside `uploads/hk9-payload-*`, `wp-content/hk9-payload` and the plugin `tests/` directory (dev), resolved with `realpath()` containment; filter `hk9/import/allowed_payload_roots`.

### 8.1 Where the payload should live on a production install

The payload carries BarKode registry data and every original image, so it must never be web-readable. Two supported placements:

1. **Upload the ZIP through Heartland → Setup & Import** (recommended). It is unpacked into `uploads/hk9-payload-<32 random chars>/` with `.htaccess` deny rules + `index.html`, archives containing dotfiles, traversal paths or script files are refused before extraction, and the whole directory is deleted by `finalize` after a clean run (`Payload::remove_uploaded()` refuses anything outside `uploads/hk9-payload-*`). On nginx hosts the `.htaccess` is ignored, but the random directory name keeps it unguessable until the run finishes; run the import promptly after uploading.
2. **CLI with a directory outside the web root**, e.g. `wp hk9 import /home/site/hk9-payload --user=admin` after `scp`/`rsync`-ing the payload to a path Apache/nginx never serve. A server-path payload is never deleted by the importer; remove it yourself afterwards.

`wp hk9 import <dir>` prints *"The payload directory is inside the web root without an .htaccess deny rule; do not leave sensitive payloads there."* when `<dir>` sits under `ABSPATH` and has no `.htaccess`. That warning is correct and expected for the dev stack's read-only bind mount `/var/www/html/wp-content/hk9-payload` (Docker mounts `../payload` there `:ro`, so the importer cannot write a deny file and `finalize` never attempts to delete it — verified: no debug.log entries, finalize logs "left in place"); on a real server use placement 1 or 2 instead of copying the payload into `wp-content/`.

## 9. Where the judge fixes live

1. URL baking after hierarchy + reading — `Steps\PostsHierarchy` → `Steps\Reading` → `Steps\PostsContent`; `Validate` rejects empty `permalink_structure`.
2. Draft stubs, no parent — `Steps\PostsStub`.
3. Meta via `meta_input` in the same `wp_update_post` — `PostFields::apply()`.
4. Rollback restores options/reading/redirects first — `Rollback::ORDER`.
5. Hashes re-read from the DB after writes — `Reconcile::readback()` / `fresh()`.
6. ISO dates → `post_date_gmt`/`post_date` — `Manifest::date_pair()`.
7. Protected payload dir + log, random names, deletion on finalize — `Payload`, `Log`, `Steps\Finalize`.
8. Reserve-row idempotency + atomic lock — `Map::reserve()`, `Map::acquire_lock()`.
9. Redirect self/cycle/collision checks — `Steps\Redirects`.
10. Active theme assertion — `Validate::environment()`.
11. Pre-existing attachment hashing — `Validate::prehash()`.

## 10. Verified test plan (local docker stack, mini fixture)

Automated: `bash plugin/heartland-k9s-core/tests/importer-suite.sh` (81 checks), for the existing-site mode `bash plugin/heartland-k9s-core/tests/adopt-existing-suite.sh` (§10.1, 155 checks) and for the content-only payload `bash plugin/heartland-k9s-core/tests/lite-payload-suite.sh` (§10.2). The suite only ever touches `mini:*` objects and tmp copies under `tests/tmp/`, so it can run on a site that already holds the real import: it never truncates the map, rolls back every run that wrote the shared `option:hk9_settings` / `reading` rows, and restores the one settings leaf it edits by hand (`contact.hours`) to its exact previous value. Afterwards a real-payload `--dry-run` must still report `skip` for everything with `conflict 0`.

1. Fresh dry run reports the creates (a byte-identical duplicate — `mini:asset:card-copy` — is reported as `skip`, not `create`); import creates everything (`-scaled` + `original_image` for the 2800×1400 hero, alt set, section meta in the hierarchy revision, `page_on_front` mapped, `page_for_posts` = the news page and `/mini-news/` renders `home.php` (HTTP 200, `body.blog`), menu items with object ids, `grep '{{'` = 0).
2. Re-run: 0 creates/updates/conflicts, uploads unchanged — including the shared attachment: `mini:asset:card` (owner) and `mini:asset:card-copy` (secondary row bound to the same attachment id, owner's title/alt kept, the copy's `{{media:…}}` token baked to the shared id) both `skip`.
3. Edit title + section meta on the site, re-run → `conflict=1`, edits preserved.
4. `--overwrite` → reverted; re-run → skip.
5. Manual page + manual uploads before the import (one byte-identical to a payload image → adopted); edit one imported page; rollback → imported objects gone, edited page skipped, manual content intact, reading/settings restored; `--force` removes the edited page.
6. `timeout -s KILL` mid-run (`tests/interrupt-run.php`) → `status` shows the cursor; `--resume` waits for the stale lock and finishes.
7. Missing media file → per-item failure chain (attachment → pages → reading → menu items); restoring the file + `--resume` imports only those.
8. Rollback of the creating run deletes the shared attachment exactly once (`deleted mini:asset:card (#id)`, log line "also bound to mini:asset:card-copy"), drops both map rows, and brings the attachment count back to the baseline.

Real payload (2026-09-11, 363 records / 250 media, 10 byte-identical pairs): after a clean import a second run and the `--dry-run` report `media_files 0 0 250 0 0` and `skip` everywhere else; `wp hk9 rollback --run=<creating run> --dry-run` reports 0 skipped (before the fix the second record of each pair rewrote the owner's title/alt/date, which produced `conflict=10` on re-runs and "skipped … modified: title, alt" in rollback).

### 10.1 Existing-site simulation (`tests/adopt-existing-suite.sh`, local stack only)

Resets the local site (`tools/reset-local-site.sh` — it empties the site), rebuilds the shape of the live Avada install with `tests/adopt-existing-oldsite.php` (pages with the live ids/slugs 6 home = front page, 16, 2032, 2944, 3, 2483, the registry pages 2910 and 3675 with `[fusion_…]` content and Avada page templates, a slug-only match #9999 `donate`, a renamed page #2072, a trashed #2124; attachments 3028/1942/1963 at their live upload paths with only the thumbnail size, the byte-identical live pair 2149 (2020/03) + 2806 (2022/12), plus #555 with identical bytes under another name; an "Old Menu" on the primary location) and then runs the real payload with `--adopt-existing`:

0. `tests/adopt-existing-convert-fail.php` forces the page → `hk9_barkode` conversion of #2910 to fail (`wp_insert_post_empty_content`) inside a throw-away context: 1 fail / 0 adopt, #2910 stays a page with `100-width.php`, no map row, no marker.
1. `wp hk9 preflight` reports 7 pages + 2 registry pages + 5 attachments and *Legacy builder plugins: none active*; a stub `foogallery/foogallery.php` activated in the container turns that check into a *Note* naming "FooGallery (stub)" with a Plugins link (pre-flight still ok); the dry run reports `adopt` 9 / 9 / 9 (stub / hierarchy / content) and 6 for media (5 by id — 2149 and 2806 each by their own id, never "would share" — 1 by sha256), `ref:asset:heartland-k9s-logo` would share #3028, warns about #2072, writes nothing.
2. The import adopts the same counts; no page slug is a payload slug with a `-N` suffix; pages 6/16/2032/2944/3/2483/9999 keep their ids, carry block content (no `[fusion_`), keep their Avada meta and dates; 6 is the front page with `page-templates/home.php`; 2910/3675 are `hk9_barkode` with the same slugs, `/larry-and-archie-service-k9/` → 301 → `/barkode/larry-and-archie-service-k9/` → 200 (`noindex`); attachments 3028/1942/1963/2149/2806/555 are reused (same ids mapped, files not duplicated, only missing sizes added, alt kept; #2806 is its own owner and `{{media:live:media:2806}}` → 2806), 241 attachments in total (6 old + 235 created), no sha256-mismatch warning; the Old Menu is intact; the log carries one `ADOPT #id` line per adoption (33) and no registry slug; `tools/url-matrix.sh` passes.
3. Re-runs (with and without the flag) create/adopt/update/conflict/fail nothing.
4. Rollback restores every adopted page to its Avada content and template, 2910/3675 back to pages with their old content and no registry meta, un-adopts every adopted row (16), deletes the created objects, keeps the 6 old attachments, restores the Old Menu on the primary location and the front page.
5. A fresh `--adopt-existing` import afterwards adopts and converts again and the URL matrix passes.
6. Crash window: the map row and `_hk9_source_key` of `live:barkode:2910` are deleted while #2910 stays an `hk9_barkode`; the pre-flight counts 1 registry record, the dry run and the import report `posts_stub` adopt 1 / create 0 (the sensitive `ADOPT #2910` line carries the id only), #2910 keeps its type and slug, exactly one record holds the slug, no `-N` duplicate, `/barkode/larry-and-archie-service-k9/` → 200, and the next re-run skips everything.

The suite leaves the local site holding only the fixture; re-populate it with `bash tools/reset-local-site.sh && tools/wp.sh hk9 import /var/www/html/wp-content/hk9-payload --user=admin`.

### 10.2 Content-only payload simulation (`tests/lite-payload-suite.sh`, local stack only)

Builds `payload-lite/` (`--lite`), copies it to the plugin's `tests/tmp/` (bind-mounted) and:

0. asserts the build: 244 file-less records with `basename`/`live_path`/`sha256`/`bytes`, `lite: true`, `media/` = the 6 ref assets, < 4 MB.
1. **Empty site**: `wp hk9 import <lite>` without `--adopt-existing` exits 1 with the content-only message and records no run; a run started without adopt (the admin/REST path) fails in `validate` with the same fatal; the pre-flight *Media reuse* line is **Failed** (*0 of 244*, `ok = false`, the 244 missing paths listed); a `--adopt-existing --dry-run` fails in `validate` (*only 0 of 244 media files were found*) with 10 `missing on this site:` lines + *… and 234 more* in the log, nothing written.
2. **Old live site with every attachment** (`tests/lite-payload-oldsite.php` on top of `tests/adopt-existing-oldsite.php`): the 244 live attachments at their live paths and ids (files copied from the full payload, thumbnail size only, big images get their `-scaled` rendition + `original_image` exactly as on the live site), 245 attachments in total (#555 is an unrelated extra).
3. Pre-flight: ok, *244 of 244 media files found* (pass, 0 by path, 0 missing), existing content 7 pages / 2 registry / 244 media.
4. Dry run: `media_files` adopt 244 / create 5 / skip 1 (the logo shares #3028) / fail 0; 244 `ADOPT #<id> by id` lines, every one to its own id; nothing written.
5. Import: the same counts, 0 record errors, every `live:media:*` map row bound to its own id with `created_by_run NULL`, the `_wp_attached_file` of all 244 unchanged, exactly 5 new original files under `uploads/` (the ref assets; `bytes_copied` = their size), #3028's missing sizes filled, `#2458` still the scaled live file and `{{media:live:media:2458}}` → 2458, the pages adopted and converted, `tools/url-matrix.sh` passes (84 ✅).
6. Re-run: create/adopt/update/conflict/fail 0, `media_files` skip 250, paths and files unchanged; the pre-flight still says 244 of 244 (mapped).
7. **Negative**: #1961 and #2458 deleted with their files, #2458 re-created under a **new id** at the same upload path → pre-flight *243 of 244 found (1 matched by upload path)*, a *Note* naming `2020/01/bg2.jpg`; dry run + import exit 2 with `media_files` fail **1** (`live:media:1961 Attachment not found on this site (expected 2020/01/bg2.jpg); use the full payload`) and adopt 1 (`live:media:2458 ADOPT #<new> by path`), no file copied for 1961, attachment count unchanged; the stale map row of `live:media:1961` (still pointing at the deleted #1961) is kept, nothing new is bound to it.
8. The **full payload** run afterwards (`--adopt-existing`) only fills that gap — `media_files` adopt 1 (the sha256 fallback binds #555, which carries bg2's bytes under another name; on a site without such a twin it would create the attachment), create 0, fail 0, no record errors, the URL matrix still passes — which is the B.7 path of `docs/install.md`.
