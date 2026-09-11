# Verification report

All results below were measured on the local Docker stack described in `docs/install.md` §C unless stated otherwise. Outcomes are classified **passed / failed / blocked**; nothing is reported as passed without the evidence file named in the row.

## Tested versions

| Component | Version |
|---|---|
| WordPress | 7.1 (`wordpress:php8.3-apache`, Apache 2.4.68) — fresh-install test also on 7.1 |
| PHP | 8.3.33 (runtime); all PHP also linted with PHP 8.5.2 |
| Database | MariaDB 11 (11.4 LTS) |
| Theme / plugin | heartland-k9s 1.0.2 · heartland-k9s-core 1.0.2 |
| Bootstrap | 5.3.8 (pinned in `package-lock.json`; verified latest stable on npm/GitHub/getbootstrap.com on 2026-09-10) |
| Fonts | Fraunces (google/fonts OFL variable, instanced SOFT=0/WONK=0, opsz 9–144, wght 300–800, roman + italic) · Inter (opsz 14, wght 400–700), WOFF2 built with fontTools 4.60 |
| Icons | lucide-static 1.44.0 (ISC) + 5 brand glyphs (Simple Icons, CC0) |
| Browsers | Playwright 1.63.0: Chromium 153.0.8010.12, WebKit 26.6, Firefox 155.0 · Lighthouse 12.8.2 with HeadlessChrome 152 · axe-core 4.13.0 |
| Node (build only) | v22.18.0, npm 10.9.3, sass (dart) 1.93, esbuild 0.25 |

## 1. Install, activation, packaging — passed

| Check | Result | Evidence |
|---|---|---|
| Plugin + theme activate on a fresh WordPress 7.1 with no notices | passed | `docker/fresh/test.sh` (separate `hk9fresh` stack, port 8095): `Success: Installed 1 of 1 plugins/themes`, `debug.log` empty after import + 20 page loads |
| Theme runs without the plugin | passed | Fresh stack with plugin deactivated: `/`, `/about/`, `/5-questions/`, `/news/`, `/contact/`, `/barkode/` → 200, `/nonexistent/` → 404, hero renders from title, `debug.log` empty; admin notice explains the missing plugin |
| Packages from the built ZIPs | passed | `npm run package` → `dist/heartland-k9s.zip` (0.7 MB, 174 files, screenshot.png, fonts, licenses), `dist/heartland-k9s-core.zip` (0.3 MB, no tests/), `dist/heartland-k9s-payload.zip` (649 MB), `dist/SHA256SUMS` |
| No PHP notices attributable to the project | passed | `wp-content/debug.log` empty after every wave's audits (WP_DEBUG + WP_DEBUG_LOG on); the only lines ever logged were the plugin's own redacted mail-failure warnings during the deliberate SMTP-down test |

## 2. Import, idempotency, rollback — passed

| Check | Result | Evidence |
|---|---|---|
| Fresh import of the real payload (363 records: 250 media, 30 pages, 49 records, 6 terms, 4 menus, settings, reading, 22 redirects) | passed | Local stack run `20260911-153815`: 0 errors / 0 warnings, 2 m 23 s; fresh stack run `20260911-194115`: identical counts, 2 m 27 s |
| Second run creates nothing and reports no conflicts | passed | Fresh stack re-run: `media_files 0/0/250/0/0`, `posts_hierarchy 0/0/79/0/0`, `posts_content 0/0/79/0/0`, `menus 0/0/31`, `options 0/0/1`, `redirects 0/0/22` (5 s) |
| Manual edit preserved on re-run; `--overwrite` reverts; rollback removes only imported objects and restores options; interrupted run resumes; missing file → per-item failure | passed | `plugin/heartland-k9s-core/tests/importer-suite.sh` (mini fixture): **81 passed, 0 failed** (incl. template change on re-import, per-leaf settings reconciliation, shared-file attachments, unbakeable content stays draft, `--step` pause + `--resume`) |
| Rollback of the real import | passed | Local stack: run `20260911-153124` rolled back (352 objects; the 13 skipped-as-modified were the slug-collision/duplicate-media defects fixed afterwards; a dry-run rollback of the corrected run reports 352 deleted / 7 restored / 0 skipped) |
| Fresh-install slug collision with WordPress' placeholder Privacy Policy page | passed (fixed) | First fresh test produced `privacy-policy-2`; the importer now adopts the pristine core placeholder; second fresh test: `/privacy-policy/` → 200, URL matrix 81/81 |
| Content contains no leftover tokens / builder markup | passed | `grep -c '{{'` = 0 over all post_content; no `fusion-`/`[fusion_`/`[ccf_form` strings in rendered pages (content review) |

## 3. URLs, redirects, WordPress states — passed

| Check | Result | Evidence |
|---|---|---|
| URL matrix (8 reference routes, 20 migrated pages, 15 legacy registry paths → one-hop 301 with `X-Redirect-By: hk9-legacy`, 15 record pages with `X-Robots-Tag: noindex`, case/UTM/trailing-slash variants, embed 404, master template/success/slide/foogallery/teams redirects, `?post_type=hk9_barkode` 404, REST/users/sitemap exclusions, empty tag archives 200, pagination, search, 404, sitemap, feed) | passed | `docs/reports/url-matrix.md` (82/82 with fixtures), `docs/reports/url-matrix-fresh.md` (81/81 on the fresh stack) |
| Blog states: index, /page/2/, category/tag/author/date archives, empty tag, search with/without results, 404, single with all core blocks, paginated post, password-protected post, comments + threading + form, attachment page, feeds | passed | Wave 2 T3 report (local fixtures `tools/wp.sh hk9-dev fixtures create`), `docs/reports/screenshots/wp/` and audit run 2026-09-11 17:04 UTC |
| Registry privacy: not in search, sitemap, feeds, REST, oEmbed/embed; review notes never rendered; generic meta description | passed | URL matrix rows; `curl /barkode/<slug>/?embed=true` → 404; content review: no street-address pattern on the Mosby record page, no handler surname in the Tex record title |

## 4. Editor tasks — passed (14/14)

`node tools/editor-matrix.mjs` (Playwright, wp-admin as administrator) — `docs/reports/editor-matrix.md`, screenshots `docs/reports/screenshots/admin/em-*.png`: hero heading/image/CTA changes, repeater reorder (mouse + keyboard), hide/show a section, **Preview of unsaved section changes on a published page**, **Revisions restore of section fields**, add Story/Event/Person/Partner via the UI, menu change, global phone + logo + header CTA changes, Quick Edit leaves section meta untouched, anonymous contact-form submission (no-JS and JS) → Submissions + Mailpit. Site state verified identical afterwards.

Plugin test suites: `tests/sections-test.php` **39/39** (schema-valid defaults, one revision per save, restore, preview, template switch, capability checks), `tests/core-fixes-test.php` **93/93**, `tests/wave4b-editor.mjs` 15/15.

## 5. Forms — passed

Validation errors re-rendered inline with an error summary (focus moved), honeypot / time-trap / single-use token (atomic claim: 4 parallel submits → 1×200 + 3×409) / per-IP rate limit (429 + Retry-After) / stateless failure codes, no-JS path via admin-post.php, JS path via `hk9/v1/forms/{id}` with a cache-safe token endpoint, `wp_mail` delivery captured in Mailpit (headers: From site, Reply-To submitter, subject prefixes; HTML + text parts), submissions stored privately with `Sent`/`Not sent` badges, admin notice + `hk9/forms/mail_failed` on failure, retention cron. Evidence: Wave 1 forms report, Wave 4a/5 plugin reports, editor matrix task 14. **Not verified:** final inbox delivery on the production host (Mailpit is a local sink) — see docs/unresolved.md.

## 6. Accessibility — passed (automated + keyboard); manual AA judgement documented

| Check | Result | Evidence |
|---|---|---|
| axe-core (wcag2a/aa/21a/21aa/22aa + best-practice) on 23 URLs × 390/1440 | passed | `docs/reports/axe.md`: 0 critical / serious / moderate / minor on every first-party page (the only hits were inside the YouTube iframe of a local-only fixture post) |
| Keyboard walk (skip link, header, mobile menu open/Escape/focus return, form error summary focus, lightbox open/close) | passed 19/19 | `docs/reports/keyboard.md`, `docs/reports/screenshots/keyboard/` |
| Contrast | passed with one documented deviation | Footer tagline crimson-on-navy (reference 2.26:1) rendered as `#dd7788` (4.55:1); focus rings changed from the reference's 1 px navy ring to 2 px outlines + halo (`docs/visual-comparison.md` §6) |
| Reduced motion, zoom (no `maximum-scale`), heading hierarchy, landmarks, one `<h1>` per page | passed | Wave 2/3 theme reports; `docs/reports/network-audit.md` |

Automated checks are not proof of full WCAG 2.2 AA compliance; a human audit of the live site with assistive technology is still recommended.

## 7. Performance — passed (measured goals)

Lighthouse 12.8.2, mobile preset (412×823 @1.75, simulated Slow-4G, 4× CPU), median of 3 (`docs/reports/lighthouse/report.md`):

| URL | Performance | Accessibility | Best practices | SEO | LCP | CLS |
|---|---|---|---|---|---|---|
| `/` | **94** (94/94/94) | 100 | 100 | 100 | 3.08 s | 0.000 |
| `/about/` | **97** | 100 | 100 | 100 | 2.63 s | 0.000 |
| `/program/` | **96** | 100 | 100 | 100 | 2.78 s | 0.000 |
| `/contact/` | **98** | 100 | 100 | 100 | 2.26 s | 0.000 |
| `/news/` | **99** | 100 | 100 | 100 | 2.18 s | 0.000 |
| `/barkode/hk923-005/` (single run) | 94 | 100 | 100 | 69 (intentional noindex) | — | — |

Desktop preset: `/` 99, `/about/` 100 (`report-desktop.md`). LCP on the home page (3.08 s simulated Slow 4G) is above the 2.5 s lab target: the LCP element is the 1024×1024 reference hero JPEG (178 KB); remaining opportunities are media-level (modern image formats, hosting cache headers) — see `docs/reports/lighthouse/summary.md`. CSS: core stylesheet 47 KB raw / 9.2 KB gzip plus per-template bundles (`docs/reports/css-coverage.md`). No field INP result exists (lab only).

## 8. Network / dependencies — passed

`docs/reports/network-audit.md`: every first-party page loads only from the site origin (no requests to replit.app, heartlandk9s.org, Google Fonts or any CDN); no failed sub-requests; no console errors. Remaining **external destinations are links only** (Zeffy donation form, PayPal hosted button, Amazon wishlist, SimpleTix tickets, Links Ink shop, Candid/GuideStar profile, Facebook, ADA/HUD resources) plus the optional Fathom analytics script when a site id is configured — listed in `docs/media-manifest.md` and `docs/conflict-log.md`.

## 9. Visual fidelity — passed with documented content-driven deviations

`docs/visual-comparison.md`: every measured component (header 81 px, logo 64 px, nav geometry, hero heights 765/540/630/373 @1440 and 717/506/591/386 @390, h1 72/60/36 px, cards 378.66 px, BarKode tile 448 px, testimonial split, overlap cards −64 px/1024 px/r16, timeline 2×488 px, contact form controls, footer 4×268 px, open mobile menu 476 px) matches `discovery/measure/results.json`; region-aligned diffs of identical-content areas are 0–3 %. Full-page pixel diffs (`docs/reports/diffs/report.md`) remain 8–64 % because every route is taller for content reasons (live 5 questions, five program steps, longer verified copy, more footer links, teams block, address rows). Cross-engine heights differ by ≤ 6 px (`docs/reports/cross-engine.md`).

## 10. Security — passed (review), residual items low

Final read-only security review (Wave 4b): no critical/high findings; the one medium (unbounded error-state transients on the no-JS form path) and four lows (ZIP pre-scan, uninstall purge of payload/log dirs, `sslverify`, DOMParser) were fixed in plugin 1.0.2 and re-verified (`tests/core-fixes-test.php`). User enumeration blocked (`/wp-json/wp/v2/users` → 401, `?author=N` → 404).

## Blocked / not verifiable here

- Original `[ccf_form id="2132"]` / `[ccf_form id="2156"]` definitions — inquiry forms built instead (see `docs/unresolved.md`).
- Production mail delivery, hosting cache headers, LiteSpeed behaviour on the 301s — verify on the target host.
- Physical QR patches were not scanned; the 15 legacy paths were verified with curl only.
