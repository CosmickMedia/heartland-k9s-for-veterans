# Lighthouse — summary and interpretation

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
