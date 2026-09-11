# Importer — payload format, behaviour, CLI and admin screen

The importer (`plugin/heartland-k9s-core/src/Import/`) loads a **payload directory** into WordPress: media, terms, pages and records, menus, settings, reading options and legacy redirects. It is idempotent (re-running creates nothing twice), resumable (cursor state survives timeouts and kills), conflict-aware (edits made on the site are preserved unless you overwrite) and reversible per run (rollback).

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
| `attachment` | `key, file, sha256, mime` | `size, title, alt, caption, description, date, parent?:{{post:K}}, sensitive?` |
| `term` | `key = term:<tax>:<slug>, taxonomy, slug, name` | `description?, parent?` |
| post-like (`page`, `post`, `hk9_*`) | `key, type, title, slug` | `status, parent, template, content, excerpt, date, menu_order, featured, meta:{k:{type,value}}, terms:{tax:[...]}, sensitive?` |
| `menu` | `key, name` | `locations:[...], items:[{key, kind:post_type\|custom, object?, url?, title?, target?, classes?, parent?:itemKey, order}]` |
| `option` | `key = option:<name>, name, value` | `merge: deep (default) \| replace`; only `hk9_*` options plus a small site-info allow-list (`blogname`, `blogdescription`, `timezone_string`, … filter `hk9/import/allowed_options`) |
| `reading` | `key = reading` | `show_on_front, page_on_front, page_for_posts, posts_per_page` |
| `redirect` | `key, from` | `to: "{{post_url:K}}" \| "/path/" \| {type:"record", slug}`, `status: 301\|302\|410` |

Tokens: `{{media:K}}` → attachment id, `{{media_url:K}}` (scaled/full URL), `{{media_url:K|original}}`, `{{post:K}}` → id, `{{post_url:K}}` → permalink, `{{term:tax:slug}}` → term id. A string that is exactly one id token becomes an integer (typed meta); embedded tokens are replaced textually (block JSON attributes and HTML alike).

Dates are ISO-8601 and converted to `post_date_gmt` + site-local `post_date`. Page templates may be given as `about.php` or `page-templates/about.php` (stored as `page-templates/about.php`); a template the active theme does not ship yet is a **warning** — `_wp_page_template` is stored anyway.

Meta values are written through `update_post_meta` (inside `wp_update_post` via `meta_input`) so registered `hk9_sec_*` sanitizers run; unregistered keys are written as-is. Only keys present in the payload are written; nothing is ever deleted.

Records marked `sensitive: true` (BarKode registry) are logged as `sensitive#<hash>` only; their attachments get a generic title and empty alt/caption/description.

## 2. Steps (in order, each cursor-resumable)

| step | what it does |
|---|---|
| `validate` | manifest shape, unique keys, token graph (dangling → fatal), ISO dates, permalinks not "Plain", active theme = `requires.theme` (default `heartland-k9s`), uploads writable, post types/taxonomies registered (per record), templates present (warning); per attachment: containment inside the payload dir, existence, size cap (64 MB, filter `hk9/import/max_file_bytes`), declared + real MIME in the allow-list (jpeg/png/gif/webp/avif/pdf — HEIC/SVG excluded; filter `hk9/import/allowed_mimes`), sha256; finally hashes pre-existing attachments lacking `_hk9_sha256` so manual uploads with identical bytes are adopted. File problems fail only that record. |
| `terms` | `term_exists` adopt or `wp_insert_term`. |
| `media_files` | dedupe by sha256 (map table → `_hk9_sha256` meta → dry-run cache); copy to `wp_tempnam()` and `media_handle_sideload()` with pre-slashed `post_data` + `meta_input`; only the `-scaled`/rotated original is produced here. **Shared attachments:** when several payload records carry byte-identical files (the real payload has 10 such pairs, e.g. `live:media:3028` + `ref:asset:heartland-k9s-logo`), the first record in manifest order creates the attachment and owns its title/alt/caption/description/date; every later record is bound to the same attachment as a *secondary* row that never writes those fields (logged `SKIP shares attachment #id with <owner>`). Its map row mirrors the owner's `db` hashes with its own `src` hashes, so both rows hash-match the object, re-runs skip (no conflict) and `{{media:K}}` / `{{media_url:K}}` resolve for either key. Dry runs remember the would-be creator per sha so the second record is reported as `skip`, not `create`. |
| `media_sizes` | `wp_update_image_subsizes()` per image (skips sizes that exist). Dry runs use `wp_get_missing_image_subsizes()` for the verdict, so small images that can never receive every registered size are reported as `skip`, not `update`. |
| `posts_stub` | every post-like record as a **draft** with no parent (`_hk9_import_pending`). |
| `posts_hierarchy` | parent-first: parent, slug, final status, template, excerpt, date, menu_order, featured, terms and section meta in **one** `wp_update_post()` (meta via `meta_input`, so the revision carries it). Meta containing `{{post_url}}` is deferred to `posts_content`. |
| `reading` | asserts published pages, sets `page_on_front`, `page_for_posts`, then `show_on_front`, `posts_per_page`. |
| `posts_content` | bakes tokens into content (block count compared before/after; leftover `{{` fails the record) and writes deferred meta in the same update. |
| `menus` | create/adopt `nav_menu` by name; items parent-first with `menu-item-status=publish`, object ids for post types; locations merged into `nav_menu_locations`. |
| `options` | deep-merge (or replace) per top-level key. |
| `redirects` | merge into `hk9_redirects` (`{version, rules:{<normalized-from>:{to,status,enabled,seed,note,updated,by}}}`); rejects `from == to`, walks the merged table for cycles, warns when `from` matches a published post. |
| `finalize` | attaches media to `parent` posts (a secondary record never re-parents a shared attachment), reports orphans (map rows no longer in the payload — never deleted), soft-flushes rewrites, deletes an **uploaded** payload copy (`uploads/hk9-payload-*` only) after a clean run; a payload given as a server path — including the read-only bind mount `wp-content/hk9-payload` of the dev stack — is never touched (logged "left in place"). |

## 3. Idempotency, conflicts, overwrite

Table `{$wpdb->prefix}hk9_import_map` (created by `Import\Map::activate()` via dbDelta on activation, lazily if missing): `source_key` UNIQUE, `object_type`, `object_id`, `sha256`, `created_by_run` (NULL = adopted/pre-existing, never deleted by rollback), `last_run`, `payload_hash`, `field_hashes` JSON, `before_data` JSON, `status` (`reserved` → `active`). Postmeta mirrors `_hk9_source_key`, `_hk9_import_run`, `_hk9_sha256` exist for repair only.

Per field the row stores `{db: hash(value re-read from the DB after our write), src: hash(resolved payload value)}`:

- no row → **create** (the row is reserved *before* the object is created, so a concurrent tick cannot duplicate it; an orphan created in a crash window is re-found through `_hk9_source_key`);
- `db` unchanged → untouched by editors → apply only if the payload changed;
- `db` changed → an editor edited it → **conflict**: skipped and counted, unless `--overwrite` (then applied and the pre-image kept);
- fields that were skipped as conflicts keep their stored hashes, so the conflict persists until resolved.

Hashes are canonical (sorted keys, normalised line endings) and always taken from read-back values, so kses/sanitizer normalisation never produces false conflicts.

One object may be bound to several rows (only attachments: byte-identical payload files). The oldest row is the **owner** (`Map::owner()`; the creating row is reserved before the object exists, so it is always the oldest); the others are secondary rows with `created_by_run = NULL` whose `db` hashes are re-mirrored from the owner on every run. An editor's edit therefore surfaces as a conflict on the owner record only; `--overwrite` re-applies the owner's values and the secondaries re-sync.

## 4. Rollback (`--run=<id>`)

Order: reading/options/redirect pre-images are restored **first**, then menu items → menus → posts (children first) → attachments → terms. Objects *created* by the run are deleted only when every recorded field hash still matches (else reported "skipped: modified") — `--force` deletes them anyway. Objects only *updated* by the run get the changed fields restored under the same rule. Adopted objects (pre-existing pages, manual uploads matched by sha256, menus adopted by name) are never deleted.

An object bound to several map rows (shared attachment) is decided **once**, when its owner row is processed: a field counts as modified only when its current value matches none of the rows' `db` hashes (so a legitimately importer-written value is never mistaken for an edit, while an editor's change that no row recorded still protects the object), the deletion is logged `deleted attachment #id (also bound to <keys>)`, and every row bound to the object is dropped from the map — the secondary records simply recreate/re-adopt on the next import. Later rows for the same object are ignored in that rollback.

## 5. State, lock, logs

- `hk9_import_state` (autoload no): `{run_id, payload_dir, mode:{dry_run, overwrite, batch, budget, until_step}, status: idle|running|paused|failed|done, step, cursor, step_total, totals, counts[step]:{create,update,skip,conflict,fail}, errors[], warnings[], failed_keys, …}`.
- `hk9_import_runs`: history (last 20) with counts and rollback status.
- `hk9_import_lock`: one atomic `UPDATE … WHERE` on the options row (`token|expires`, 60 s TTL refreshed every 10 s while a tick runs). A killed process leaves the lock until it expires; the CLI waits for it.
- `hk9_import_payload`: the currently selected payload directory.
- Log: `uploads/hk9-import/<run>.log` (dir has `Options -Indexes` + deny rules + `index.html`; run ids are unguessable). Download via `admin-post.php?action=hk9_import_log&_wpnonce=…&run=…`.

Resume semantics: a **paused/interrupted** run continues at its cursor; resuming a **done/failed** run starts a new pass over the same run id (already-imported records skip; failed ones get another chance).

## 6. Building a payload

```
node tools/build-payload.mjs [--src=payload-src] [--out=payload] [--media-index=payload/media-index.json] [--copy-media] [--no-verify]
```

Merges `payload-src/records/**/*.json` (one record object or an array per file) + `payload-src/content/*.html` + `payload/media-index.json` (written by `tools/fetch-media.mjs`: `{items:{key:{file, sha256, bytes, mime, width, height, title, alt, caption, description, date, parent?, sensitive?}}}`; missing → zero media + warning) into `payload/manifest.json` + `payload/content/`. It validates every record shape and every `{{token}}` (dangling → build fails), verifies media sha256 (`--no-verify` to skip) and prints counts. Optional `payload-src/sources.json` (`{sources, requires}`) and `payload-src/known-terms.json` (pre-seeded term keys tokens may reference). `--copy-media` copies `<src>/media/**` into the output (used by the fixture).

Test fixture: `tools/fixtures/payload-mini/` (see its README) → `plugin/heartland-k9s-core/tests/payload-mini/`, which the docker stack sees at `/var/www/html/wp-content/plugins/heartland-k9s-core/tests/payload-mini`.

## 7. CLI

```
wp hk9 import <dir> [--dry-run] [--overwrite] [--resume] [--step=<name>] [--batch=<n>] [--budget=<s>] [--quiet-progress] --user=<admin>
wp hk9 import --resume --user=<admin>
wp hk9 status [--run=<id>] [--format=json]
wp hk9 rollback --run=<id> [--force] [--yes] [--dry-run] --user=<admin>
wp hk9 reset-state --yes --user=<admin>
```

`--user` must be an administrator with `unfiltered_html` (kses would otherwise strip block attributes); the command refuses otherwise. `--step=<name>` runs up to and including that step, then pauses. Exit code 2 = completed with record errors (fix the payload, `--resume`).

## 8. Admin screen

Heartland → **Setup & Import** (`admin.php?page=hk9-import`, `manage_options`): upload a payload ZIP (unpacked into `uploads/hk9-payload-<random>/` with deny rules + `index.html`; dotfiles and script-bearing files inside the archive are purged; the copy is deleted after a clean import) or, when `HK9_LOCAL_DEV` is defined, use a bind-mounted server path. Buttons: Dry run / Import (Overwrite checkbox) / Pause / Resume / Retry failed / Reset state; progress bar, per-step counts, error list, warnings, log download, runs table with typed-confirmation rollback (`ROLLBACK`, optional Force).

REST (`manage_options` + `wp_rest` nonce): `GET hk9/v1/import/status`, `POST hk9/v1/import/{start,step,pause,resume,retry,rollback,reset}`. Server paths are accepted only inside `uploads/hk9-payload-*`, `wp-content/hk9-payload` and the plugin `tests/` directory (dev), resolved with `realpath()` containment; filter `hk9/import/allowed_payload_roots`.

### 8.1 Where the payload should live on a production install

The payload carries BarKode registry data and every original image, so it must never be web-readable. Two supported placements:

1. **Upload the ZIP through Heartland → Setup & Import** (recommended). It is unpacked into `uploads/hk9-payload-<32 random chars>/` with `.htaccess` deny rules + `index.html`, dotfiles and script-bearing files are purged, and the whole directory is deleted by `finalize` after a clean run (`Payload::remove_uploaded()` refuses anything outside `uploads/hk9-payload-*`). On nginx hosts the `.htaccess` is ignored, but the random directory name keeps it unguessable until the run finishes; run the import promptly after uploading.
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

Automated: `bash plugin/heartland-k9s-core/tests/importer-suite.sh` (81 checks). The suite only ever touches `mini:*` objects and tmp copies under `tests/tmp/`, so it can run on a site that already holds the real import: it never truncates the map, rolls back every run that wrote the shared `option:hk9_settings` / `reading` rows, and restores the one settings leaf it edits by hand (`contact.hours`) to its exact previous value. Afterwards a real-payload `--dry-run` must still report `skip` for everything with `conflict 0`.

1. Fresh dry run reports the creates (a byte-identical duplicate — `mini:asset:card-copy` — is reported as `skip`, not `create`); import creates everything (`-scaled` + `original_image` for the 2800×1400 hero, alt set, section meta in the hierarchy revision, `page_on_front` mapped, `page_for_posts` = the news page and `/mini-news/` renders `home.php` (HTTP 200, `body.blog`), menu items with object ids, `grep '{{'` = 0).
2. Re-run: 0 creates/updates/conflicts, uploads unchanged — including the shared attachment: `mini:asset:card` (owner) and `mini:asset:card-copy` (secondary row bound to the same attachment id, owner's title/alt kept, the copy's `{{media:…}}` token baked to the shared id) both `skip`.
3. Edit title + section meta on the site, re-run → `conflict=1`, edits preserved.
4. `--overwrite` → reverted; re-run → skip.
5. Manual page + manual uploads before the import (one byte-identical to a payload image → adopted); edit one imported page; rollback → imported objects gone, edited page skipped, manual content intact, reading/settings restored; `--force` removes the edited page.
6. `timeout -s KILL` mid-run (`tests/interrupt-run.php`) → `status` shows the cursor; `--resume` waits for the stale lock and finishes.
7. Missing media file → per-item failure chain (attachment → pages → reading → menu items); restoring the file + `--resume` imports only those.
8. Rollback of the creating run deletes the shared attachment exactly once (`deleted mini:asset:card (#id)`, log line "also bound to mini:asset:card-copy"), drops both map rows, and brings the attachment count back to the baseline.

Real payload (2026-09-11, 363 records / 250 media, 10 byte-identical pairs): after a clean import a second run and the `--dry-run` report `media_files 0 0 250 0 0` and `skip` everywhere else; `wp hk9 rollback --run=<creating run> --dry-run` reports 0 skipped (before the fix the second record of each pair rewrote the owner's title/alt/date, which produced `conflict=10` on re-runs and "skipped … modified: title, alt" in rollback).
