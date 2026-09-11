# Lighthouse — summary and interpretation

## Theme 1.0.2 re-run (2026-09-11, same tool and settings)

`node tools/lighthouse.mjs --urls=/,/about/,/program/,/contact/,/news/ --runs=3 --out=docs/reports/lighthouse` after the theme performance work (font preload URLs, CSS core + template bundles, LCP image preload, small logo size). `report.md` and the `*-run{1,2,3}.json` files for these five URLs are from this run; the three HTML diagnostics from the 1.0.1 run (home/about/program) were removed because every page now scores ≥ 90 and the tool no longer writes them. The `fixture-rich-blocks-*` and `barkode-hk923-005-run*.json` files are still the 1.0.1 numbers (the fixture post no longer exists; the registry record was re-audited once, see below).

| URL | Perf 1.0.1 → **1.0.2** (runs) | A11y | BP | SEO | FCP | LCP | TBT | CLS |
|---|---|---|---|---|---|---|---|---|
| `/` | 84 → **94** (94/94/94) | 100 | 100 | 100 | 2.11 → 1.36 s | 4.21 → 3.08 s | 0 | 0.003 → 0.000 |
| `/about/` | 87 → **97** (97/96/97) | 100 | 100 | 100 | 2.26 → 1.36 s | 3.76 → 2.63 s | 0 | 0.027 → 0.000 |
| `/program/` | 87 → **96** (96/96/96) | 100 | 100 | 100 | 2.26 → 1.36 s | 3.76 → 2.78 s | 0 | 0.003 → 0.000 |
| `/contact/` | 91 → **98** (98/98/98) | 100 | 100 | 100 | 2.26 → 1.66 s | 3.16 → 2.26 s | 0 | 0.002 → 0.000 |
| `/news/` | 90 → **99** (99/99/98) | 100 | 100 | 100 | 2.26 → 1.36 s | 3.30 → 2.18 s | 0 | 0.003 → 0.000 |

Single extra runs (not in `report.md`, `--runs=1 --no-html`): `/barkode/hk923-005/` 87 → **94** (SEO 69 = intentional noindex), `/donate/` **98**, `/stories/madison-and-gunther/` **96**.

What changed (theme only, `theme/heartland-k9s` 1.0.2):

1. **Fonts download once.** `hk9_preload_fonts()` now preloads `assets/fonts/<file>.woff2` with the exact URL the compiled `@font-face` resolves to (the `?v=` query was dropped). Playwright request logging on `/`, `/about/`, `/program/`, `/contact/`, `/news/`, `/donate/` and a registry record, mobile and desktop: 3 font requests per page (Fraunces roman, Inter, Fraunces italic for the footer tagline), each URL exactly once — was 5 requests / 106 KB of duplicates.
2. **Render-blocking CSS 173 KB → 47 KB core (28 → 9.2 KB gzip)** plus one or two small per-template bundles: `forms.css` 16 KB / 3.1 KB gz, `content.css` 12 KB / 2.5 KB gz, `blog.css` 26 KB / 4.4 KB gz, `pages.css` 14 KB / 2.5 KB gz, `records.css` 30 KB / 4.8 KB gz (`tools/build-css.mjs` prints raw + gzip sizes; `hk9_style_bundles()` in `inc/assets.php` picks them per template). `/` loads the core only (9.2 KB gz); `/about/` and `/program/` core + pages (11.7 KB gz); `/contact/` core + forms + pages; `/news/` core + blog. Playwright CSS coverage across every template (29 URLs × 2 widths) drove the split; unused Bootstrap layers were dropped outright (buttons, type, grid `:root` breakpoints, transitions, labels/form-text, validation, pagination, the utilities API, the palette `:root` variables). `unused-css-rules` now passes on every page.
3. **LCP image preload.** `hk9_preload_lcp_image()` prints `<link rel="preload" as="image" imagesrcset imagesizes fetchpriority="high">` for the image hero (home/program/barkode), the About split-card photo and the blog hero, using the same srcset/sizes as the `<img>` (Chrome picks the same candidate — verified one hero request per page). LCP load delay on `/` 2 218 → 1 248 ms.
4. **Logo 68 KB → 35 KB.** New `hk9-logo-sm` size (140×160, generated on demand for already-uploaded logos by `hk9_ensure_image_size()`), `sizes` = the rendered width derived from the configured logo height (56 px header / 70 px footer) so 1×–2× screens pick the 140 w candidate; header and footer share the one file. Still a PNG (core generates no WebP for PNG uploads).

What remains (all outside the theme's control, and the reason the pages are 94–99 rather than 100): `modern-image-formats` (hero/feature JPEGs and the crest PNG — media conversion), `uses-responsive-images` for the below-the-fold `barkode-768x768.jpg` tile on `/` and the About photo (needs additional intermediate sizes closer to the rendered width), the missing `Cache-Control` on `/wp-content/uploads/*` from the local Apache, and the render-blocking cost of the two remaining stylesheet requests on the reference templates (400–730 ms estimated on Slow 4G; inlining `pages.css` would remove one request at the cost of caching it).

---

## Theme 1.0.1 audit (original)

Generated tables: `report.md` (mobile, 7 URLs × 3 runs) and `report-desktop.md` (desktop preset, `/` and `/about/`, 1 run). Raw JSON for every run (`<slug>-run<N>.json`, `<slug>-desktop-run1.json`) and the HTML diagnostics (`<slug>-diagnostic.report.html`) sit next to this file. Registry privacy: the `/barkode/hk923-005/` run files are scrubbed by the tool (no screenshots, DOM snippets/labels or page text — `hk9Scrubbed` marker in the JSON) and no HTML diagnostic is written for it (an HTML report embeds the page); the tool applies this to any URL matching `--private` (default `/barkode/`). Re-render the tables without re-auditing with `node tools/lighthouse.mjs --reuse …`.

## Environment

| | |
|---|---|
| Lighthouse | 12.8.2 (`node_modules/lighthouse`, run via `npx lighthouse`) |
| Chrome | Google Chrome 152.0.7977.84 (system install, `--headless=new --no-sandbox`; UA reports `HeadlessChrome/152.0.0.0`) |
| Mobile settings | `--form-factor=mobile --screenEmulation.mobile` → 412×823 @ 1.75 DPR; `--throttling-method=simulate` (Lantern) with Lighthouse's default mobile "Slow 4G" profile: 150 ms RTT, 1 638 Kbps throughput, 4× CPU slowdown; categories performance, accessibility, best-practices, seo; `--preset=perf` is passed but every setting it would change is overridden by the explicit flags above (verified in `configSettings` of each JSON) |
| Desktop settings | `--preset=desktop` → 1350×940 @ 1, simulate, 40 ms RTT, 10 240 Kbps, 1× CPU |
| Site state | local blog fixtures present (14 posts); `/fixture-rich-blocks/` is a LOCAL-ONLY fixture post carrying a YouTube embed |

## Mobile scores (median of 3; every run in brackets)

| URL | Perf | A11y | BP | SEO | FCP | LCP | TBT | CLS |
|---|---|---|---|---|---|---|---|---|
| `/` | **84** (84/84/84) | 100 | 100 | 100 | 2.11 s | 4.21 s | 0 ms | 0.003 |
| `/about/` | **87** (87/87/85) | 100 | 100 | 100 | 2.26 s | 3.76 s | 0 ms | 0.027 |
| `/program/` | **87** (87/87/87) | 100 | 100 | 100 | 2.26 s | 3.76 s | 0 ms | 0.003 |
| `/contact/` | **91** (90/91/91) | 100 | 100 | 100 | 2.26 s | 3.16 s | 0 ms | 0.002 |
| `/news/` | **90** (90/90/90) | 100 | 100 | 100 | 2.26 s | 3.30 s | 0 ms | 0.003 |
| `/fixture-rich-blocks/` | **87** (86/88/87) | 100 | 96 | 69 | 2.26 s | 3.61 s | 0 ms | 0.050 |
| `/barkode/hk923-005/` | **87** (87/87/87) | 100 | 100 | 69 | 2.26 s | 3.76 s | 0 ms | 0.000 |

Run-to-run spread is ≤ 2 points on every URL (simulated throttling makes the numbers deterministic); TBT is 0 ms everywhere (1.4 KB of first-party JS, no long tasks).

## Desktop scores (1 run)

| URL | Perf | A11y | BP | SEO | FCP | LCP | TBT | CLS |
|---|---|---|---|---|---|---|---|---|
| `/` | **99** | 100 | 100 | 100 | 0.49 s | 0.89 s | 0 ms | 0.003 |
| `/about/` | **100** | 100 | 100 | 100 | 0.49 s | 0.73 s | 0 ms | 0.004 |

## Non-performance scores that are not 100 — all intentional or embed-related

- **SEO 69 on `/barkode/hk923-005/` and `/fixture-rich-blocks/`** — only `is-crawlable` fails. Both are deliberate: `hk9_robots()` in `theme/heartland-k9s/inc/compat.php:181-190` sets `noindex,nofollow` (+ `X-Robots-Tag`) on registry singles and `noindex` on posts carrying the `_hk9_local_fixture` meta. Real posts/pages are indexable (`/news/`, `/stories/…`, pages: `max-image-preview:large` only).
- **Best Practices 96 on `/fixture-rich-blocks/`** — `inspector-issues`: a third-party cookie set by `https://www.youtube.com/embed/…` (the fixture's YouTube embed; known exception).
- CLS 0.050 on the fixture post comes from the YouTube iframe; 0.027 on `/about/` is the largest first-party shift (still "good", < 0.1).

## Why mobile Performance is 84–91 (five URLs < 90) — diagnostic runs

The four HTML diagnostics (`/`, `/about/`, `/program/`, `/fixture-rich-blocks/`) and the scrubbed `/barkode/hk923-005/` run JSON tell the same story; the page-specific numbers are in `report.md`. Nothing is script-related (TBT 0). The score is entirely FCP/LCP/Speed Index under simulated Slow 4G, and the same three causes appear on every page (theme/server level — reported, **not edited**):

1. **Render-blocking `theme.css` — est. 1.46–1.62 s on every page.** `assets/dist/theme.css` is 173 KB (28 KB gzipped) and blocks first paint; `unused-css-rules` says ~23 KB of the transfer is unused on any given page. With 150 ms RTT this alone costs the connection RTTs + ~140 ms of bandwidth before anything paints. Options: inline the above-the-fold CSS (or a small critical slice) and load the rest non-blocking; split the Bootstrap/prose/blog-states/registry parts that most pages never use; preload the stylesheet.
2. **Fonts download twice — 106 KB wasted per page, competing with the LCP image.** `hk9_preload_fonts()` (`theme/heartland-k9s/inc/assets.php:79-81`) emits `<link rel="preload" href="…/fraunces-var.woff2?v=1789128458">` and `…/inter-var.woff2?v=…`, but the compiled `@font-face` rules request `../fonts/fraunces-var.woff2` / `inter-var.woff2` **without** the `?v=` query, so the browser never matches the preloaded responses and fetches both fonts again (`network-requests` on every audited page: 5 font requests — `fraunces-var.woff2?v` 66 KB, `inter-var.woff2?v` 40 KB, then `fraunces-var.woff2` 66 KB, `inter-var.woff2` 40 KB, plus `fraunces-italic-var.woff2` 80 KB). On the hero pages the two preloads (High) start in parallel with the LCP image and the stylesheet, so they directly lengthen the LCP "load delay" phase (2.2 s on `/`). Fix: use the same URL in both places (drop the query from the preload, or add the version to the `@font-face` `src`).
3. **Image delivery.** `Concept-1-rocker-outlined-2-263x300.png` (the round crest logo, 68 KB PNG, used as `img.hk9-header__logo` and `img.hk9-footer__logo` with `sizes="80px"` but the smallest candidate in its `srcset` is 263 w — `uses-responsive-images` + `modern-image-formats` on every page, ~50 KB avoidable; an 80/160 px WebP/AVIF or an inline SVG crest would remove it); hero/feature JPEGs `hero-home-768x768.jpg` (108 KB), `about-dog-768x768.jpg`, `training-768x768.jpg`, `barkode-768x768.jpg` (119 KB, below the fold on `/`) flagged for WebP/AVIF (`modern-image-formats`, 81–128 KB per page). LCP is the hero `<img>` on `/`, `/program/`; it is discoverable in HTML, `fetchpriority="high"`, `loading="eager"`, not lazy — the LCP element itself is set up correctly.
4. **Caching (`cache-insight`, informational here):** the local Apache sends no `Cache-Control` for `/wp-content/uploads/*` (612 KB "wasted" on `/`). Hosting-level; verify on the production server.

LCP phase breakdown on `/` (diagnostic run): TTFB 453 ms · load delay 2 218 ms · load time 121 ms · render delay 1 417 ms → 4.21 s. The load delay is the render-blocking CSS + the duplicated font fetches queued ahead of the image; the render delay is the CSS finishing after the image has arrived.

Expected effect of 1 + 2 alone (Lighthouse's own estimates): FCP −1.4 s and LCP −1.5 to −2 s on Slow 4G, which puts every audited page ≥ 90 on mobile. Desktop is already 99–100 because the 40 ms RTT / 10 Mbps profile hides the same costs.
