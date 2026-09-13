# Page speed — what the theme does and how it was measured

Scope: the front end of the Heartland Canines for Veterans theme (1.2.x) on the local stack (WordPress 7.1, PHP 8.3, Apache, no page cache, HTTP/1.1) measured with `tools/lighthouse.mjs` — Lighthouse 12.6 mobile preset, simulated Slow-4G throttling (150 ms RTT, 1.6 Mbps, 4× CPU), 412×823 @ 1.75, **median of 3 runs**. The live host (LiteSpeed + page cache + HTTP/2/3) can only be faster: TTFB drops from ≈450 ms (PHP on every request here) to a cached response, and the six-connection HTTP/1.1 limit that queues requests in these numbers does not apply. Hosting settings that keep it that way are in `docs/install.md` §D.

## 1. Results

Mobile, median of 3 (per-metric medians over the runs):

| URL | Performance before → **after** | FCP s | LCP s | LCP element (after) | CLS | TBT ms |
|---|---|---|---|---|---|---|
| `/` | 94 → **99** | 1.36 → 0.91 | 3.08 → **2.25** | hero `<img>` `hero-home-768x768.webp` (75 kB) | 0 | 0 |
| `/about/` | 96 → **99** | 1.36 → 0.78 | 2.64 → 2.10 | legacy card `<img>` `about-dog-768x768.webp` (53 kB) | 0 | 0 |
| `/program/` | 96 → **99** | 1.36 → 0.86 | 2.78 → 2.10 | hero `<img>` `training-768x768.webp` (59 kB) | 0 | 0 |
| `/contact/` | 93 → **94** | 1.50 → 1.35 | 3.16 → 3.00 | band hero `<p class="hk9-hero__text">` (text; Gravity Form on the page) | 0 | 0 |
| `/news/` | 99 → **100** | 1.36 → 0.78 | 2.18 → 1.73 | listing hero `<p>` (text) | 0 | 0 |
| `/events/` | 98 → **99** | 1.36 → 0.92 | 2.41 → 1.95 | band hero `<p class="hk9-hero__text">` (text) | 0 | 0 |
| `/meet-the-team/` | 95 → **99** | 1.50 → 0.92 | 2.93 → 2.25 | band hero `<p class="hk9-hero__text">` (text) | 0 | 0 |
| `/photos/` | 75 → **95** | 1.65 → 0.95 | 8.03 → 2.93 | band hero `<p class="hk9-hero__text">` (text; 72-image gallery below) | 0 | 0 |

Accessibility, Best practices and SEO stay 100 on every URL. Before = `docs/reports/lighthouse/report-baseline-2026-09-13.md` (2026-09-13 14:48, same stack, before any change); after = `docs/reports/lighthouse/report.md` (2026-09-13 15:30; the raw `*-run{1,2,3}.json` sit alongside, git-ignored). `/photos/` was re-measured (3 runs) after the `hk9-tile` size was added.

Targets: Performance ≥ 95 on every listed page — met on 7 of 8 (`/contact/` 94, runs 94/95/94 — §3). LCP < 2.5 s on the home page — met (2.25 s, was 3.08 s).

Note on `/program/`: it is the **reference-template page** (page 290, Program template with an image hero — the LCP-image row above). The **migrated live page** for the same content is `/the-service-k9-program/` (page 282, the URL in `docs/reports/url-coverage.md`), which renders the **band hero** with no hero image (`hero_image.image` = 0), so its LCP is the hero text and its numbers are those of the band-hero rows (`/events/`, `/meet-the-team/`), not the `/program/` row. Assign a hero image on that page (Heartland → Hero) to get the image-LCP behaviour measured here.

### Bytes per image (the LCP hero and the images near the fold)

| Image (as the phone downloads it, 412 px @ 1.75) | Before (JPEG/PNG) | After (WebP) |
|---|---|---|
| Home hero `hero-home-768x768` | 108.3 kB | **75.2 kB** (−31 %) |
| Home hero on desktop (1024 original / full copy) | 177.8 kB | **125.8 kB** (−29 %) |
| Home BarKode feature `barkode-768x768` | 118.9 kB | **86.4 kB** (−27 %) |
| Home testimonial portrait `…-600x800` | 59.0 kB | **30.4 kB** (−48 %) |
| About legacy card `about-dog-768x768` | 86.7 kB | **52.9 kB** (−39 %) |
| Program hero `training-768x768` | 99.1 kB | **59.5 kB** (−40 %) |
| Events card `were-back-2-800x600` (flyer uploaded as an opaque PNG) | 627.9 kB | **70.6 kB** (−89 %) |
| Meet-the-team portraits `…-600x800` (5) | 62 / 101 / 74 / 116 / 30 kB | **36 / 77 / 45 / 89 / 30 kB** |
| Photos gallery, one tile (157 CSS px wide at 412 @ 1.75) | 60–337 kB (`-600x800` / `-683x1024` / `-768x1024` JPEG, or the original) | **12–38 kB** (`-300x400` / `-455x683` WebP, `-300x200` JPEG) — the 53 tiles Chrome fetches eagerly: 2 567 → 1 055 kB; all 71 tiles: 1.5 MB at 412 @ 1.75, 2.3 MB on a 3× phone (512-wide WebP) |

Fonts on the critical path: 190.5 kB → **108.6 kB** (the 81.9 kB Fraunces italic now loads after the page).

Page weight (mobile, Lighthouse network log): `/` 465 → 402 kB (then the italic after load); `/photos/` 2 819 → 1 306 kB with the same 53 tiles Chrome fetches eagerly; `/events/` 874 → 315 kB.

## 2. What changed (theme 1.2.x)

1. **WebP sub-sizes** — `inc/setup.php` `hk9_image_output_format()` on `image_editor_output_format`: JPEG sources → WebP sub-sizes; PNG sources → WebP only for opaque truecolor/greyscale PNGs — no alpha channel, no `tRNS` chunk, not palette-based (`hk9_png_webp_safe()` reads the chunk headers; GD cannot encode a palette image as WebP unresized) — so logos with transparency and flat palette graphics stay lossless PNG while a photo or flyer saved as PNG (the 3.4 MB `were-back-2.png`) gets WebP sizes. The uploaded original is never converted in place; WordPress keeps it as `original_image` and, for a *new* upload, writes a WebP "full" copy next to it (the adopted live media keeps its JPEG full size — only the sizes generated for it are WebP). WebP quality 80 (`hk9_webp_quality()`, filter `hk9/theme/webp_quality`; core's 86 gave only −10 %, see the docblock). Falls back to the source format automatically where the PHP image library cannot write WebP (`WP_Image_Editor::get_output_format()`); check with `wp eval 'var_dump( wp_image_editor_supports( [ "mime_type" => "image/webp" ] ) );'` or Site Health → Info → Media handling. The importer's `media_sizes` step (`wp_update_image_subsizes()`) and `hk9_ensure_image_size()` run through the same editor, so on the live site the theme sizes it generates for the 244 adopted files come out as WebP with no further step; the core sizes that already exist there (`thumbnail`, `medium`, `medium_large`, `large`, …) stay JPEG until `wp media regenerate` is run (optional, §4). **Mixed-format renditions are therefore expected for adopted media**: because the importer (and `hk9_ensure_image_size()`) only generate the sizes that are *missing*, an adopted JPEG keeps its old JPEG core sizes next to the new WebP theme sizes, and an opaque PNG — the only PNG kind that qualifies for WebP — keeps its PNG core sizes next to WebP theme sizes, so one `srcset` can list `.png`/`.jpg` and `.webp` candidates side by side (on the local stack after the import: 12 of the 37 PNG attachments; none of the alpha/palette PNGs got a WebP rendition). That is valid HTML, browsers pick a candidate by width exactly as before and the layout is pixel-identical (partner logo tiles: 0.09 % diff at 390 px from the lossy re-encode) — it only means the bytes per breakpoint are not uniform and the Media Library shows two formats for one image until the one-off regenerate in §4 is run. Filter `hk9/theme/webp_subsizes` (false) switches the conversion off.
2. **Two tile sizes** — `inc/setup.php`: `hk9-tile` (512×683, uncropped) is the candidate every aspect ratio was missing between `medium` (a portrait is 200–225 wide) and `large`/`medium_large` (683–768 wide, 100–250 kB): 2:3 photos get 455×683, 3:4 512×683, 4:3 512×384. `hk9-portrait-sm` (300×400 crop) is the smaller 3:4 candidate that 1.75–2× phones pick for portrait photos. Both are WebP; the importer generates them for the adopted media like every other theme size.
3. **Gallery tile `sizes`** — `inc/setup.php` `hk9_gallery_block_sizes()` (`render_block_core/gallery`) gives every tile of a core gallery block a `sizes` attribute computed from the column count and the real container (measured for the Photo Gallery template card: `100vw − 82px` below 768, `100vw − 130px` to 1088, then 958 px; the content column elsewhere). Block-content galleries get srcset+sizes through core's own `wp_img_tag_add_srcset_and_sizes_attr()` with the gallery value active in `wp_calculate_image_sizes`; core's later pass still adds `loading`, `decoding`, `fetchpriority` and the `auto` prefix. Before, every tile carried the content-column width (`calc(100vw - 32px)`) and phones fetched 600–1024 px candidates for 157 px tiles.
4. **Mobile hero variant** — `hk9_the_hero()` renders `hero_image.image_mobile` (when set and different) as a `<picture><source media="(max-width: 767px)" srcset="…" sizes="100vw">` with the full candidate list of the mobile image (`hk9_hero_mobile_source()`), and `hk9_preload_lcp_image()` preloads it with byte-identical `imagesrcset`/`imagesizes` + `media`, the desktop image with `media="(min-width: 768px)`. Verified with Playwright: exactly one hero request per breakpoint (412 @ 1.75 → the mobile image's 768w candidate, 768 @ 1 → the desktop 768w, 1440 → the desktop 1024w). Without a mobile image the single `<img sizes="100vw">` already makes Chrome pick the 768w candidate at 412 @ 1.75 (721 device px), never the 1024 square.
5. **Deferred italic** — the 81.9 kB `Fraunces` italic is only used below the fold (footer tagline, testimonial quotes) but was fetched at "VeryHigh" priority alongside the LCP image and the two preloaded roman faces. `tools/fonts/build-fonts.py` now marks it `deferred`: it is left out of `_fonts.scss` (so out of `theme.css`) and listed in the generated `assets/fonts/fonts.json`; `inc/assets.php` `hk9_deferred_fonts()` passes it to `theme.js`, which adds it through the Font Loading API after `load`, the first contentful paint and `document.fonts.ready` (`FontFace` with `display: swap` → the same swap the CSS face did, just later). `<noscript>` gets the plain `@font-face`; the block editor canvas declares it directly (nothing is deferred there). In isolation this took the home page from 94 to 97.
6. **Inline CSS** — `hk9_enqueue_theme_style()`: `theme.css` and the template bundles are printed inline (`<style id="hk9-theme-inline-css">` …) instead of as `<link>`s (filter `hk9/theme/inline_css`, constant `HK9_INLINE_CSS`). Measured on the three-bundle pages: `/meet-the-team/` 94 → 99, `/news/` 99 → 100, `/contact/` 93 → 94, FCP −0.3 to −0.5 s; the home page (one stylesheet) was unchanged. Cost: 10–25 kB gzip of CSS per HTML response that is no longer cached across pages (the whole `/contact/` document is 34 kB gzip). No flash of unstyled content is possible because every rule is present before the body is parsed — which is why a "critical CSS + async stylesheet" split was **not** implemented: it saves nothing over full inlining here (the whole core is 9.6 kB gzip) and would render the below-hero content unstyled for a few hundred milliseconds on band-hero pages.
7. **Gravity Forms** — its assets load only on pages that render a form (verified: none on `/`, `/about/`); the empty `gravity-forms-orbital-theme.min.css` that Gravity Forms 3.0.1 still enqueues (0 bytes, one render-blocking round trip) is dropped by `hk9_drop_empty_gravity_styles()` while the file on disk is empty.
8. Unchanged and confirmed: font preloads (the two roman faces, exact `@font-face` URLs, `font-display: swap`); no third-party hosts, no resource hints needed (`tools/network-audit.mjs` PASS on 16 URLs); `theme.js` 3 kB deferred; emoji script/DNS-prefetch removed; every theme image carries `width`/`height` (CLS 0 everywhere).

## 3. `/contact/` — why it stays below 95 in this setup

The page's LCP is the hero text, and the simulation charges everything fetched before it: the Gravity Forms stack (3 stylesheets 38 kB gzip render-blocking, 6 deferred scripts 75 kB + jQuery/migrate 36 kB + 4 `wp-*` helpers 7 kB) plus the two fonts (109 kB). Experiments (single runs): blocking Gravity Forms' JavaScript → 96, blocking its CSS → 94, blocking the fonts → 96; nothing in the theme is left on that path. Options, in order of safety, if the number matters more than the form's out-of-the-box behaviour: (a) LiteSpeed Cache → Page Optimization → *Load JS Deferred: Delayed* for the Gravity Forms handles only (test a submission afterwards); (b) render the forms non-AJAX (`gravity_form(…, $ajax = false)` in `Support/FormProviders.php`) to drop `jquery.json` and the spinner iframe; (c) the built-in provider (no jQuery at all) — a product decision, see `docs/ARCHITECTURE.md` §10. On the live host with a page cache and HTTP/2 the same page measures higher because the ≈450 ms PHP TTFB and the connection queueing disappear.

## 4. Remaining opportunities

- **Hero source resolution.** The design heroes are 1024×1024; `hk9-hero` (1920 wide) cannot be produced, so desktops ≥ 1024 px stretch the 1024 copy (126 kB WebP). Uploading 1920–2560 px sources (Heartland → the page's Hero image) enables the 1536/1920 candidates.
- **Core sizes of the adopted media** stay JPEG — PNG for the opaque PNGs — (`medium` 300, `medium_large` 768, `large` 1024 …), so their `srcset`s mix formats (§2.1): a one-off `wp media regenerate --yes` on the live site after the import (≈244 images, minutes) rewrites every size as WebP and makes each image's renditions one format again; the theme sizes are already WebP after the import. To limit the pass to the PNGs only (37 attachments): `wp media regenerate $(wp post list --post_type=attachment --post_mime_type=image/png --format=ids) --yes` (whole attachments, never `--image_size` — see below). Note that with the output-format filter active core also writes a WebP copy of each full-size file and makes it the attachment file (the JPEG original stays as `original_image`) — that is normal WordPress behaviour and the importer's adoption logic accepts either name. Regenerate whole attachments only: `wp media regenerate --image_size=<one>` combines core's re-pointed attachment file with WP-CLI's kept metadata and leaves the two inconsistent (found and repaired on the local stack during this pass).
- **Header/footer logo** `Concept-1-rocker-outlined-2-140x160.png` (30 kB RGBA, shown at 56–70 px): kept PNG by design (transparency). A re-exported, quantised PNG or an SVG crest would save ≈20 kB on every page.
- **Gravity Forms CSS** — 38 kB gzip render-blocking on form pages (`gravity-forms-theme-framework.min.css` alone is 544 kB raw / 25 kB gzip); the theme's Orbital overrides need it. Nothing to do in the theme.
- **Social image format** — `hk9-og` (1200×630) is generated as WebP like the other sizes; Facebook, X, Slack, iMessage and WhatsApp render WebP `og:image`; if a network is found not to, add `'hk9-og'` handling in `Seo/Image.php` (the original JPEG is still on disk as `original_image`).
- `jquery-migrate` (5 kB) is loaded by Gravity Forms' `jquery` dependency; left in place for add-on compatibility.

## 5. How to re-measure

```
# before/after, mobile, median of 3 (≈10 min); --desktop for the desktop preset
node tools/lighthouse.mjs --urls=/,/about/,/program/,/contact/,/news/,/events/,/meet-the-team/,/photos/ --runs=3 --out=docs/reports/lighthouse
node tools/network-audit.mjs        # no third-party hosts, no failed requests, no console errors
node tools/axe.mjs                  # no serious/critical violations
node tools/screenshot.mjs --out=<dir> && node tools/compare.mjs --wp=<dir>/chromium --out=<dir>-diff   # pixel diff against docs/reports/screenshots/wp/chromium
```

To regenerate the sizes as WebP on an existing local stack: `tools/wp.sh media regenerate --yes` (whole attachments — never `--image_size`, see §4). What the live import does for the missing theme sizes is `wp_update_image_subsizes()`, which leaves the full-size file alone.
